<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Task;
use Carbon\CarbonInterface;

class PracticeMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = today();
        $nextWeek = $today->copy()->addDays(7);
        $closedMatterStatuses = ['closed', 'cancelled'];
        $completedTaskStatuses = ['completed', 'cancelled'];

        return [
            'metrics' => [
                'open_matters' => Matter::query()->whereNotIn('status', $closedMatterStatuses)->count(),
                'overdue_deadlines' => Matter::query()->whereNotIn('status', $closedMatterStatuses)->whereDate('deadline_at', '<', $today)->count(),
                'unpaid_invoices' => Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->where('balance_due', '>', 0)->count(),
                'outstanding_balance' => $this->decimal(Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->sum('balance_due')),
                'open_tasks' => Task::query()->whereNotIn('status', $completedTaskStatuses)->count(),
                'overdue_tasks' => Task::query()->whereNotIn('status', $completedTaskStatuses)->whereDate('due_at', '<', $today)->count(),
            ],
            'deadlines' => Matter::query()
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
                ])->all(),
            'tasks' => Task::query()
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
                ])->all(),
            'events' => Appointment::query()
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
                ])->all(),
            'payments' => Payment::query()
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
                ])->all(),
            'charts' => [
                'matter_activity' => $this->activityTrend(now()->subMonths(5)->startOfMonth(), now()->endOfMonth()),
                'matter_distribution' => $this->matterDistribution(),
            ],
            'activity' => $this->activity(8),
        ];
    }

    /** @return array<string, mixed> */
    public function reports(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $invoices = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
            ->whereBetween('issued_at', [$startDate->toDateString(), $endDate->toDateString()]);

        return [
            'summary' => [
                'matters_opened' => Matter::query()->whereBetween('opened_at', [$startDate->toDateString(), $endDate->toDateString()])->count(),
                'invoices_issued' => (clone $invoices)->count(),
                'amount_billed' => $this->decimal((clone $invoices)->sum('total_amount')),
                'payments_collected' => $this->decimal(Payment::query()->whereBetween('received_at', [$startDate->toDateString(), $endDate->toDateString()])->sum('amount')),
                'outstanding_balance' => $this->decimal(Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->sum('balance_due')),
                'overdue_tasks' => Task::query()->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_at', '<', today())->count(),
            ],
            'matters_by_status' => Matter::query()
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
            'tasks_by_status' => Task::query()
                ->whereBetween('due_at', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn (Task $task): array => ['label' => $task->status, 'value' => (int) $task->total])
                ->all(),
            'activity_trend' => $this->activityTrend($startDate, $endDate),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function activity(int $limit): array
    {
        return ActivityLog::query()
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
    private function activityTrend(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $startMonth = $startDate->copy()->startOfMonth();
        $endMonth = $endDate->copy()->startOfMonth();
        $matterCounts = Matter::query()
            ->whereBetween('opened_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['opened_at'])
            ->countBy(fn (Matter $matter): string => $matter->opened_at->format('Y-m'));
        $completedTaskCounts = Task::query()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get(['completed_at'])
            ->countBy(fn (Task $task): string => $task->completed_at?->format('Y-m') ?? '');
        $invoiceCounts = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid', 'paid'])
            ->whereBetween('issued_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['issued_at'])
            ->countBy(fn (Invoice $invoice): string => $invoice->issued_at?->format('Y-m') ?? '');
        $paymentCounts = Payment::query()
            ->whereBetween('received_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['received_at'])
            ->countBy(fn (Payment $payment): string => $payment->received_at->format('Y-m'));
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
    private function matterDistribution(): array
    {
        return Matter::query()
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
}
