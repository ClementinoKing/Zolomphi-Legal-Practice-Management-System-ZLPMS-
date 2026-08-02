<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PracticeMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $today = today();
        $nextWeek = $today->copy()->addDays(7);
        $closedMatterStatuses = ['closed', 'cancelled'];
        $completedTaskStatuses = ['completed', 'cancelled'];
        $canViewMatters = $user->hasAnyPermission([
            'matters.view.all',
            'matters.view.department',
            'matters.view.assigned',
        ]);
        $canViewInvoices = $user->hasPermission('invoices.view');
        $canViewPayments = $user->hasPermission('payments.view');
        $canManageTasks = $user->hasPermission('tasks.manage');
        $canManageCalendar = $user->hasPermission('calendar.manage');
        $canViewAudit = $user->hasPermission('audit-logs.view');
        $hasBillingAccess = $canViewInvoices || $canViewPayments;
        $hasMatterActivity = $canViewMatters || $canManageTasks;

        return [
            'sections' => [
                'matters' => $canViewMatters,
                'tasks' => $canManageTasks,
                'calendar' => $canManageCalendar,
                'billing' => $canViewInvoices && $canViewPayments,
                'invoices' => $canViewInvoices,
                'payments' => $canViewPayments,
                'activity' => $canViewAudit,
            ],
            'metrics' => [
                'open_matters' => $canViewMatters ? Matter::query()->visibleTo($user)->whereNotIn('status', $closedMatterStatuses)->count() : null,
                'overdue_deadlines' => $canViewMatters ? Matter::query()->visibleTo($user)->whereNotIn('status', $closedMatterStatuses)->whereDate('deadline_at', '<', $today)->count() : null,
                'unpaid_invoices' => $canViewInvoices ? $this->visibleInvoices($user)->whereIn('status', ['issued', 'partially_paid'])->where('balance_due', '>', 0)->count() : null,
                'outstanding_balance' => $canViewInvoices ? $this->decimal($this->visibleInvoices($user)->whereIn('status', ['issued', 'partially_paid'])->sum('balance_due')) : null,
                'open_tasks' => $canManageTasks ? $this->visibleTasks($user)->whereNotIn('status', $completedTaskStatuses)->count() : null,
                'overdue_tasks' => $canManageTasks ? $this->visibleTasks($user)->whereNotIn('status', $completedTaskStatuses)->whereDate('due_at', '<', $today)->count() : null,
            ],
            'deadlines' => $canViewMatters ? Matter::query()->visibleTo($user)
                ->with('client:id,full_name,organization_name')
                ->whereNotIn('status', $closedMatterStatuses)
                ->whereNotNull('deadline_at')
                ->whereDate('deadline_at', '<=', $nextWeek)
                ->orderBy('deadline_at')
                ->limit(8)
                ->get(['id', 'client_id', 'matter_number', 'title', 'deadline_at'])
                ->map(fn (Matter $matter): array => [
                    'id' => $matter->id,
                    'matter_number' => $matter->matter_number,
                    'title' => $matter->title,
                    'client' => $this->clientName($matter->client),
                    'deadline_at' => $matter->deadline_at?->toDateString(),
                    'is_overdue' => $matter->deadline_at?->isBefore($today) ?? false,
                ])->all() : [],
            'tasks' => $canManageTasks ? $this->visibleTasks($user)
                ->with(['matter:id,client_id,matter_number,title', 'matter.client:id,full_name,organization_name'])
                ->whereNotIn('status', $completedTaskStatuses)
                ->whereNotNull('due_at')
                ->whereDate('due_at', '<=', $nextWeek)
                ->orderBy('due_at')
                ->limit(8)
                ->get(['id', 'matter_id', 'title', 'priority', 'status', 'due_at'])
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'due_at' => $task->due_at?->toDateString(),
                    'matter_id' => $task->matter_id,
                    'matter' => $task->matter?->matter_number,
                    'client' => $task->matter === null ? null : $this->clientName($task->matter->client),
                    'is_overdue' => $task->due_at?->isBefore($today) ?? false,
                ])->all() : [],
            'events' => $canManageCalendar ? Appointment::query()
                ->whereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')))
                ->with('matter:id,matter_number,title')
                ->whereBetween('starts_at', [now(), now()->addDays(7)])
                ->orderBy('starts_at')
                ->limit(6)
                ->get(['id', 'matter_id', 'title', 'starts_at', 'is_all_day'])
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'title' => $appointment->title,
                    'starts_at' => $appointment->starts_at->toIso8601String(),
                    'is_all_day' => $appointment->is_all_day,
                    'matter_id' => $appointment->matter_id,
                    'matter' => $appointment->matter?->matter_number,
                ])->all() : [],
            'payments' => $canViewPayments ? Payment::query()
                ->whereHas('invoice', fn (Builder $invoice): Builder => $invoice
                    ->whereNull('matter_id')
                    ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))
                ->with(['invoice:id,client_id,invoice_number', 'invoice.client:id,full_name,organization_name'])
                ->latest('received_at')
                ->limit(6)
                ->get(['id', 'invoice_id', 'payment_number', 'amount', 'currency', 'received_at'])
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'invoice_number' => $payment->invoice->invoice_number,
                    'client' => $this->clientName($payment->invoice->client),
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'received_at' => $payment->received_at->toDateString(),
                ])->all() : [],
            'activity' => $canViewAudit ? $this->activity($user, 8) : [],
            'charts' => [
                'matter_activity' => $hasMatterActivity || $hasBillingAccess
                    ? $this->activityTrend(
                        $user,
                        now()->subMonths(5)->startOfMonth(),
                        now()->endOfMonth(),
                        $canViewMatters,
                        $canManageTasks,
                        $canViewInvoices,
                        $canViewPayments,
                    )
                    : [],
                'matter_distribution' => $canViewMatters ? $this->matterDistribution($user) : [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function reports(User $user, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $invoices = $this->visibleInvoices($user)
            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
            ->whereBetween('issued_at', [$startDate->toDateString(), $endDate->toDateString()]);

        return [
            'summary' => [
                'matters_opened' => Matter::query()->visibleTo($user)->whereBetween('opened_at', [$startDate->toDateString(), $endDate->toDateString()])->count(),
                'invoices_issued' => (clone $invoices)->count(),
                'amount_billed' => $this->decimal((clone $invoices)->sum('total_amount')),
                'payments_collected' => $this->decimal(Payment::query()->whereHas('invoice', fn (Builder $invoice): Builder => $invoice->whereNull('matter_id')->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))->whereBetween('received_at', [$startDate->toDateString(), $endDate->toDateString()])->sum('amount')),
                'outstanding_balance' => $this->decimal($this->visibleInvoices($user)->whereIn('status', ['issued', 'partially_paid'])->sum('balance_due')),
                'overdue_tasks' => $this->visibleTasks($user)->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_at', '<', today())->count(),
            ],
            'matters_by_status' => Matter::query()->visibleTo($user)
                ->whereBetween('opened_at', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (Matter $matter): array => ['label' => $matter->status, 'value' => (int) $matter->total])
                ->all(),
            'billing_by_status' => (clone $invoices)
                ->selectRaw('status, count(*) as total, coalesce(sum(total_amount), 0) as amount')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (Invoice $invoice): array => ['label' => $invoice->status, 'value' => (int) $invoice->total, 'amount' => $invoice->amount])
                ->all(),
            'tasks_by_status' => $this->visibleTasks($user)
                ->whereBetween('due_at', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (Task $task): array => ['label' => $task->status, 'value' => (int) $task->total])
                ->all(),
            'activity_trend' => $this->activityTrend($user, $startDate, $endDate),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function activity(User $user, int $limit): array
    {
        return ActivityLog::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('matter_id')
                    ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
            })
            ->with(['user:id,name', 'matter:id,matter_number,title', 'client:id,full_name,organization_name'])
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'user_id', 'matter_id', 'client_id', 'event', 'description', 'created_at'])
            ->map(fn (ActivityLog $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'actor' => $activity->user->name,
                'matter_id' => $activity->matter_id,
                'matter' => $activity->matter?->matter_number,
                'client' => $this->clientName($activity->client),
                'created_at' => $activity->created_at->toIso8601String(),
            ])->all();
    }

    private function clientName(?Client $client): ?string
    {
        if ($client === null) {
            return null;
        }

        return $client->organization_name ?? $client->full_name;
    }

    private function decimal(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** @return array<int, array{label: string, matters: int, tasks_completed: int, invoices: int, payments: int}> */
    private function activityTrend(
        User $user,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        bool $includeMatters = true,
        bool $includeTasks = true,
        bool $includeInvoices = true,
        bool $includePayments = true,
    ): array {
        $startMonth = $startDate->copy()->startOfMonth();
        $endMonth = $endDate->copy()->startOfMonth();
        $matterCounts = $includeMatters ? Matter::query()->visibleTo($user)
            ->whereBetween('opened_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['opened_at'])
            ->countBy(fn (Matter $matter): string => $matter->opened_at->format('Y-m')) : collect();
        $completedTaskCounts = $includeTasks ? $this->visibleTasks($user)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get(['completed_at'])
            ->countBy(fn (Task $task): string => $task->completed_at?->format('Y-m') ?? '') : collect();
        $invoiceCounts = $includeInvoices ? $this->visibleInvoices($user)
            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
            ->whereBetween('issued_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['issued_at'])
            ->countBy(fn (Invoice $invoice): string => $invoice->issued_at?->format('Y-m') ?? '') : collect();
        $paymentCounts = $includePayments ? Payment::query()
            ->whereHas('invoice', fn (Builder $invoice): Builder => $invoice->whereNull('matter_id')->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))
            ->whereBetween('received_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['received_at'])
            ->countBy(fn (Payment $payment): string => $payment->received_at->format('Y-m')) : collect();
        $trend = [];

        for ($month = $startMonth; $month->lte($endMonth); $month = $month->copy()->addMonth()) {
            $key = $month->format('Y-m');
            $trend[] = [
                'label' => $month->format('M'),
                'matters' => (int) ($matterCounts[$key] ?? 0),
                'tasks_completed' => (int) ($completedTaskCounts[$key] ?? 0),
                'invoices' => (int) ($invoiceCounts[$key] ?? 0),
                'payments' => (int) ($paymentCounts[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /** @return array<int, array{label: string, value: int}> */
    private function matterDistribution(User $user): array
    {
        return Matter::query()->visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (Matter $matter): array => [
                'label' => $matter->status,
                'value' => (int) $matter->total,
            ])
            ->all();
    }

    /** @return Builder<Invoice> */
    private function visibleInvoices(User $user): Builder
    {
        return Invoice::query()->where(function ($query) use ($user): void {
            $query->whereNull('matter_id')
                ->orWhereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
        });
    }

    /** @return Builder<Task> */
    private function visibleTasks(User $user): Builder
    {
        return Task::query()->whereHas('matter', fn (Builder $matter): Builder => $matter->whereIn('id', Matter::query()->visibleTo($user)->select('id')));
    }
}
