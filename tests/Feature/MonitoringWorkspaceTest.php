<?php

use App\Actions\Notifications\SendPracticeAlert;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PracticeAlert;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function monitoringAdministrator(): User
{
    $permission = Permission::query()->create([
        'key' => 'administration.access',
        'name' => 'Access administration',
        'group' => 'Administration',
    ]);
    $role = Role::query()->create(['name' => 'Monitoring Administrator']);
    $role->assignedPermissions()->attach($permission);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('administrators see live dashboard and reporting metrics', function () {
    Carbon::setTestNow('2026-08-02 10:00:00');
    $administrator = monitoringAdministrator();
    $client = Client::factory()->create();
    $openMattersBefore = Matter::query()->whereNotIn('status', ['closed', 'cancelled'])->count();
    $overdueDeadlinesBefore = Matter::query()->whereNotIn('status', ['closed', 'cancelled'])->whereDate('deadline_at', '<', today())->count();
    $unpaidInvoicesBefore = Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->where('balance_due', '>', 0)->count();
    $outstandingBefore = (float) Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->sum('balance_due');
    $openTasksBefore = Task::query()->whereNotIn('status', ['completed', 'cancelled'])->count();
    $overdueTasksBefore = Task::query()->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_at', '<', today())->count();
    $periodMattersBefore = Matter::query()->whereBetween('opened_at', ['2026-08-01', '2026-08-31'])->count();
    $periodInvoicesBefore = Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'paid'])->whereBetween('issued_at', ['2026-08-01', '2026-08-31'])->count();
    $periodBilledBefore = (float) Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'paid'])->whereBetween('issued_at', ['2026-08-01', '2026-08-31'])->sum('total_amount');
    $periodPaymentsBefore = (float) Payment::query()->whereBetween('received_at', ['2026-08-01', '2026-08-31'])->sum('amount');
    $periodCompletedTasksBefore = Task::query()->whereBetween('completed_at', ['2026-08-01', '2026-08-31'])->count();
    $matter = Matter::factory()->create([
        'client_id' => $client->id,
        'responsible_user_id' => $administrator->id,
        'opened_at' => '2026-08-01',
        'deadline_at' => '2026-08-01',
    ]);
    Task::query()->create([
        'matter_id' => $matter->id,
        'created_by_user_id' => $administrator->id,
        'title' => 'Prepare the hearing bundle',
        'status' => 'pending',
        'priority' => 'high',
        'due_at' => '2026-08-01',
    ]);
    Task::query()->create([
        'matter_id' => $matter->id,
        'created_by_user_id' => $administrator->id,
        'title' => 'Complete the document review',
        'status' => 'completed',
        'priority' => 'normal',
        'due_at' => '2026-08-01',
        'completed_at' => '2026-08-01 09:00:00',
    ]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'matter_id' => $matter->id,
        'created_by_user_id' => $administrator->id,
        'status' => 'partially_paid',
        'issued_at' => '2026-08-01',
        'total_amount' => 1000,
        'paid_amount' => 400,
        'balance_due' => 600,
    ]);
    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'recorded_by_user_id' => $administrator->id,
        'payment_number' => 'PAY-TEST-001',
        'method' => 'Cash',
        'currency' => 'MWK',
        'amount' => 400,
        'received_at' => '2026-08-01',
    ]);
    ActivityLog::query()->create([
        'user_id' => $administrator->id,
        'client_id' => $client->id,
        'matter_id' => $matter->id,
        'subject_type' => Matter::class,
        'subject_id' => $matter->id,
        'event' => 'matter.updated',
        'description' => 'Updated the matter.',
    ]);

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.open_matters', $openMattersBefore + 1)
            ->where('metrics.overdue_deadlines', $overdueDeadlinesBefore + 1)
            ->where('metrics.unpaid_invoices', $unpaidInvoicesBefore + 1)
            ->where('metrics.outstanding_balance', number_format($outstandingBefore + 600, 2, '.', ''))
            ->where('metrics.open_tasks', $openTasksBefore + 1)
            ->where('metrics.overdue_tasks', $overdueTasksBefore + 1)
            ->has('payments.0')
            ->has('charts.matter_activity', 6)
            ->where('charts.matter_activity.5.matters', $periodMattersBefore + 1)
            ->where('charts.matter_activity.5.tasks_completed', $periodCompletedTasksBefore + 1)
            ->where('charts.matter_activity.5.invoices', $periodInvoicesBefore + 1)
            ->where('charts.matter_activity.5.payments', (int) $periodPaymentsBefore + 1)
            ->has('activity.0'));

    $this->get(route('reports.index', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]))->assertInertia(fn (Assert $page) => $page
        ->component('reports/index')
        ->where('summary.matters_opened', $periodMattersBefore + 1)
        ->where('summary.invoices_issued', $periodInvoicesBefore + 1)
        ->where('summary.amount_billed', number_format($periodBilledBefore + 1000, 2, '.', ''))
        ->where('summary.payments_collected', number_format($periodPaymentsBefore + 400, 2, '.', ''))
        ->where('activity_trend.0.tasks_completed', $periodCompletedTasksBefore + 1)
        ->has('activity_trend'));
});

test('audit logs remain restricted to administrators and can be filtered', function () {
    $administrator = monitoringAdministrator();
    $client = Client::factory()->create();
    ActivityLog::query()->create([
        'user_id' => $administrator->id,
        'client_id' => $client->id,
        'event' => 'payment.recorded',
        'description' => 'Recorded payment PAY-TEST-002.',
    ]);
    ActivityLog::query()->create([
        'user_id' => $administrator->id,
        'client_id' => $client->id,
        'event' => 'invoice.created',
        'description' => 'Created an invoice.',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($administrator)
        ->get(route('audit-logs.index', ['event' => 'payment.recorded']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'payment.recorded'));
});

test('users can read their own persisted notifications only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $user->notify(new PracticeAlert('Task assigned', 'You were assigned a task.', '/matters/1', 'task'));
    $otherUser->notify(new PracticeAlert('Payment recorded', 'A payment was recorded.', '/payments', 'payment'));
    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notificationHistory.data', 1)
            ->where('notificationHistory.data.0.title', 'Task assigned')
            ->has('notifications.recent', 1)
            ->where('counts.unread', 1));

    $this->patch(route('notifications.read', $notification->id))->assertRedirect();
    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('practice alerts exclude the actor and de-duplicate recipients', function () {
    $actor = User::factory()->create();
    $recipient = User::factory()->create();

    app(SendPracticeAlert::class)->handle(
        collect([$actor, $recipient, $recipient]),
        $actor,
        [
            'title' => 'Document added',
            'body' => 'A document is ready to review.',
            'url' => '/matters/1',
            'type' => 'document',
        ],
    );

    expect($actor->notifications()->count())->toBe(0);
    expect($recipient->notifications()->count())->toBe(1);
});
