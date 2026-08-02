<?php

use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $billingItem = $this->ensureBillingItem($timestamp);
        $expenseCategory = $this->ensureExpenseCategory($timestamp);

        DB::transaction(function () use ($billingItem, $expenseCategory, $timestamp): void {
            DB::table('matters')
                ->whereJsonContains('metadata->source', 'practice-sample-data')
                ->orderBy('id')
                ->get([
                    'id',
                    'client_id',
                    'responsible_user_id',
                    'default_hourly_rate',
                ])
                ->map(fn (object $matter): array => [
                    'id' => (int) data_get($matter, 'id'),
                    'client_id' => (int) data_get($matter, 'client_id'),
                    'responsible_user_id' => (int) data_get($matter, 'responsible_user_id'),
                    'default_hourly_rate' => data_get($matter, 'default_hourly_rate'),
                ])
                ->each(function (array $matter) use ($billingItem, $expenseCategory, $timestamp): void {
                    $workItemCount = 2 + ($matter['id'] % 9);

                    for ($workItemIndex = 0; $workItemIndex < $workItemCount; $workItemIndex++) {
                        $record = match ($workItemIndex % 6) {
                            0 => $this->createTask($matter, $workItemIndex, $timestamp),
                            1 => $this->createTimeEntry($matter, $workItemIndex, $timestamp),
                            2 => $this->createNote($matter, $workItemIndex, $timestamp),
                            3 => $this->createAppointment($matter, $workItemIndex, $timestamp),
                            4 => $this->createExpense($matter, $workItemIndex, $expenseCategory, $timestamp),
                            default => $this->createFlatFee($matter, $workItemIndex, $billingItem, $timestamp),
                        };

                        $this->recordActivity($matter, $record['event'], $record['id'], $record['description'], $timestamp);
                    }
                });
        });
    }

    public function down(): void
    {
        // This demonstration-data migration is intentionally irreversible.
    }

    private function ensureBillingItem(CarbonInterface $timestamp): int
    {
        $code = 'sample-matter-work-fee';
        $existingId = DB::table('billing_items')->where('code', $code)->value('id');

        if (is_int($existingId)) {
            return $existingId;
        }

        return DB::table('billing_items')->insertGetId([
            'name' => 'Matter legal work fee',
            'code' => $code,
            'description' => 'Sample flat-fee work used in the practice workspace demonstration data.',
            'unit' => 'item',
            'default_amount' => 250000,
            'tax_rate' => 0,
            'is_active' => true,
            'sort_order' => 999,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function ensureExpenseCategory(CarbonInterface $timestamp): array
    {
        $code = 'sample-legal-disbursement';
        $category = DB::table('expense_categories')
            ->where('code', $code)
            ->first(['id', 'name']);

        if ($category !== null) {
            return [
                'id' => (int) data_get($category, 'id'),
                'name' => (string) data_get($category, 'name'),
            ];
        }

        return [
            'id' => DB::table('expense_categories')->insertGetId([
                'name' => 'Legal disbursements',
                'code' => $code,
                'description' => 'Court, registry, transport, and service disbursements for legal matters.',
                'is_billable_by_default' => true,
                'is_active' => true,
                'sort_order' => 999,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
            'name' => 'Legal disbursements',
        ];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @return array{event: string, id: int, description: string}
     */
    private function createTask(array $matter, int $index, CarbonInterface $timestamp): array
    {
        $isCompleted = ($matter['id'] + $index) % 4 === 0;
        $taskId = DB::table('tasks')->insertGetId([
            'matter_id' => $matter['id'],
            'created_by_user_id' => $matter['responsible_user_id'],
            'title' => ['Review file instructions', 'Prepare legal analysis', 'Follow up on outstanding information'][$matter['id'] % 3],
            'description' => 'Sample legal-work task prepared for this matter workspace.',
            'status' => $isCompleted ? 'completed' : 'pending',
            'priority' => ['normal', 'high', 'urgent'][$matter['id'] % 3],
            'due_at' => $timestamp->copy()->addDays(($matter['id'] % 18) - 6)->toDateString(),
            'completed_at' => $isCompleted ? $timestamp->copy()->subDays($matter['id'] % 5) : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('task_user')->insert([
            'task_id' => $taskId,
            'user_id' => $matter['responsible_user_id'],
            'assigned_at' => $timestamp,
        ]);

        return ['event' => 'tasks.created', 'id' => $taskId, 'description' => 'Added sample task: review file instructions.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @return array{event: string, id: int, description: string}
     */
    private function createTimeEntry(array $matter, int $index, CarbonInterface $timestamp): array
    {
        $timeEntryId = DB::table('time_entries')->insertGetId([
            'matter_id' => $matter['id'],
            'user_id' => $matter['responsible_user_id'],
            'entry_date' => $timestamp->copy()->subDays(($matter['id'] + $index) % 20)->toDateString(),
            'description' => ['Reviewed instructions and supporting documents.', 'Prepared correspondence and legal advice.', 'Conducted legal research and matter planning.'][$matter['id'] % 3],
            'hours' => [1.25, 1.5, 2.0, 2.25][$matter['id'] % 4],
            'hourly_rate' => $matter['default_hourly_rate'] ?? 75000,
            'is_billable' => true,
            'status' => ['draft', 'submitted', 'approved'][$matter['id'] % 3],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return ['event' => 'time-entries.created', 'id' => $timeEntryId, 'description' => 'Recorded sample billable time.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @return array{event: string, id: int, description: string}
     */
    private function createNote(array $matter, int $index, CarbonInterface $timestamp): array
    {
        $noteId = DB::table('matter_notes')->insertGetId([
            'matter_id' => $matter['id'],
            'author_user_id' => $matter['responsible_user_id'],
            'body' => 'Internal update: the file has been reviewed and the next legal steps are being coordinated with the responsible attorney.',
            'visibility' => 'internal',
            'created_at' => $timestamp->copy()->subDays(($matter['id'] + $index) % 15),
            'updated_at' => $timestamp,
        ]);

        return ['event' => 'notes.created', 'id' => $noteId, 'description' => 'Added a sample internal note.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @return array{event: string, id: int, description: string}
     */
    private function createAppointment(array $matter, int $index, CarbonInterface $timestamp): array
    {
        $startsAt = $timestamp->copy()
            ->addDays(($matter['id'] + $index) % 24)
            ->setTime(9 + ($matter['id'] % 5), 0);
        $appointmentId = DB::table('appointments')->insertGetId([
            'matter_id' => $matter['id'],
            'client_id' => $matter['client_id'],
            'organizer_user_id' => $matter['responsible_user_id'],
            'title' => ['Client progress meeting', 'Matter strategy conference', 'Document review meeting'][$matter['id'] % 3],
            'description' => 'Sample calendar activity for the matter team.',
            'type' => ['meeting', 'hearing', 'reminder'][$matter['id'] % 3],
            'status' => 'scheduled',
            'location' => ['Zolomphi & Co. offices', 'Virtual meeting', 'Client premises'][$matter['id'] % 3],
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'is_all_day' => false,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('appointment_user')->insert([
            'appointment_id' => $appointmentId,
            'user_id' => $matter['responsible_user_id'],
        ]);

        return ['event' => 'calendar-events.created', 'id' => $appointmentId, 'description' => 'Scheduled a sample matter event.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @param  array{id: int, name: string}  $expenseCategory
     * @return array{event: string, id: int, description: string}
     */
    private function createExpense(array $matter, int $index, array $expenseCategory, CarbonInterface $timestamp): array
    {
        $expenseId = DB::table('expenses')->insertGetId([
            'matter_id' => $matter['id'],
            'expense_category_id' => $expenseCategory['id'],
            'recorded_by_user_id' => $matter['responsible_user_id'],
            'expense_number' => sprintf('EXP-SMP-%04d-%02d', $matter['id'], $index + 1),
            'category' => $expenseCategory['name'],
            'vendor' => ['High Court Registry', 'Malawi Revenue Authority', 'Courier services'][$matter['id'] % 3],
            'currency' => 'MWK',
            'amount' => [25000, 40000, 60000][$matter['id'] % 3],
            'incurred_at' => $timestamp->copy()->subDays(($matter['id'] + $index) % 12)->toDateString(),
            'is_billable' => true,
            'status' => 'recorded',
            'description' => 'Sample legal disbursement for the matter.',
            'receipt_path' => null,
            'receipt_disk' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return ['event' => 'expenses.created', 'id' => $expenseId, 'description' => 'Recorded a sample legal disbursement.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     * @return array{event: string, id: int, description: string}
     */
    private function createFlatFee(array $matter, int $index, int $billingItemId, CarbonInterface $timestamp): array
    {
        $unitPrice = [150000, 200000, 250000][$matter['id'] % 3];
        $quantity = $matter['id'] % 2 === 0 ? 1 : 2;
        $chargeId = DB::table('matter_charges')->insertGetId([
            'matter_id' => $matter['id'],
            'billing_item_id' => $billingItemId,
            'created_by_user_id' => $matter['responsible_user_id'],
            'description' => 'Sample fixed fee for legal work completed on the matter.',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => 0,
            'amount' => $quantity * $unitPrice,
            'status' => 'billable',
            'charged_at' => $timestamp->copy()->subDays(($matter['id'] + $index) % 10)->toDateString(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return ['event' => 'flat-fees.created', 'id' => $chargeId, 'description' => 'Added a sample fixed-fee charge.'];
    }

    /**
     * @param  array{id: int, client_id: int, responsible_user_id: int, default_hourly_rate: mixed}  $matter
     */
    private function recordActivity(array $matter, string $event, int $subjectId, string $description, CarbonInterface $timestamp): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => $matter['responsible_user_id'],
            'matter_id' => $matter['id'],
            'client_id' => $matter['client_id'],
            'subject_type' => $event,
            'subject_id' => $subjectId,
            'event' => $event,
            'description' => $description,
            'properties' => json_encode(['source' => 'practice-sample-data']),
            'created_at' => $timestamp,
        ]);
    }
};
