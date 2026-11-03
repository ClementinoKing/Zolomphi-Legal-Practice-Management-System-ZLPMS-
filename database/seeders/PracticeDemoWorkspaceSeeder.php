<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\BillingItem;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Matter;
use App\Models\MatterCharge;
use App\Models\MatterEmail;
use App\Models\MatterNote;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class PracticeDemoWorkspaceSeeder extends Seeder
{
    /** @var list<array{title: string, description: string, priority: string, due_in_days: int, completed: bool}> */
    private const Tasks = [
        ['title' => 'Review and confirm client instructions', 'description' => 'Confirm the scope, supporting documents, and immediate legal objectives with the client.', 'priority' => 'high', 'due_in_days' => 3, 'completed' => true],
        ['title' => 'Prepare legal analysis and recommended next steps', 'description' => 'Assess the key legal issues and prepare a concise advice note for internal review.', 'priority' => 'high', 'due_in_days' => 7, 'completed' => false],
        ['title' => 'Schedule client progress follow-up', 'description' => 'Arrange a progress update and capture any additional instructions from the client.', 'priority' => 'normal', 'due_in_days' => 12, 'completed' => false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $matters = Matter::query()
                ->where('metadata->seed_source', 'practice_demo_2026')
                ->with('client')
                ->orderBy('id')
                ->get();

            if ($matters->isEmpty()) {
                throw new LogicException('Run PracticeDemoSeeder before seeding demo matter workspaces.');
            }

            $billingItem = $this->billingItem();
            $expenseCategory = $this->expenseCategory();

            foreach ($matters as $index => $matter) {
                $this->seedWorkspace($matter, $index, $billingItem, $expenseCategory);
            }
        });
    }

    private function seedWorkspace(Matter $matter, int $index, BillingItem $billingItem, ExpenseCategory $expenseCategory): void
    {
        $tasks = $this->seedTasks($matter, $index);
        $this->seedTimeEntries($matter, $index);
        $document = $this->seedDocument($matter);
        $this->seedNote($matter);
        $this->seedEmail($matter, $document);
        $this->seedCalendarEvent($matter, $index);
        $this->seedExpense($matter, $expenseCategory, $index);
        $this->seedCharge($matter, $billingItem, $index);

        if ($tasks !== []) {
            ActivityLog::query()->firstOrCreate(
                [
                    'matter_id' => $matter->id,
                    'event' => 'practice-demo.workspace_populated',
                ],
                [
                    'user_id' => $matter->responsible_user_id,
                    'client_id' => $matter->client_id,
                    'subject_type' => Task::class,
                    'subject_id' => $tasks[0]->id,
                    'description' => 'Populated the demo workspace with tasks, records, and financial activity.',
                    'properties' => ['seed_source' => 'practice_demo_workspace_2026'],
                ],
            );
        }
    }

    /**
     * @return list<Task>
     */
    private function seedTasks(Matter $matter, int $matterIndex): array
    {
        return array_map(function (array $definition, int $taskIndex) use ($matter, $matterIndex): Task {
            $task = Task::query()->firstOrCreate(
                [
                    'matter_id' => $matter->id,
                    'title' => $definition['title'],
                ],
                [
                    'created_by_user_id' => $matter->responsible_user_id,
                    'description' => $definition['description'],
                    'status' => $definition['completed'] ? 'completed' : 'pending',
                    'priority' => $definition['priority'],
                    'due_at' => now()->addDays($definition['due_in_days'] + $matterIndex)->toDateString(),
                    'completed_at' => $definition['completed'] ? now()->subDay() : null,
                ],
            );
            $task->assignees()->syncWithoutDetaching([$matter->responsible_user_id]);

            return $task;
        }, self::Tasks, array_keys(self::Tasks));
    }

    private function seedTimeEntries(Matter $matter, int $matterIndex): void
    {
        $hourlyRate = $matter->default_hourly_rate ?? 75000;

        foreach ([
            ['description' => 'Reviewed client instructions and supporting material.', 'hours' => 1.50, 'days_ago' => 4, 'status' => 'approved'],
            ['description' => 'Prepared legal analysis and matter action plan.', 'hours' => 2.25, 'days_ago' => 1, 'status' => 'submitted'],
        ] as $entry) {
            TimeEntry::query()->firstOrCreate(
                [
                    'matter_id' => $matter->id,
                    'description' => $entry['description'],
                ],
                [
                    'user_id' => $matter->responsible_user_id,
                    'entry_date' => now()->subDays($entry['days_ago'] + $matterIndex)->toDateString(),
                    'hours' => $entry['hours'],
                    'hourly_rate' => $hourlyRate,
                    'is_billable' => true,
                    'status' => $entry['status'],
                ],
            );
        }
    }

    private function seedDocument(Matter $matter): Document
    {
        $path = 'matters/'.$matter->id.'/practice-demo/'.Str::slug($matter->matter_number).'-workspace-summary.txt';
        $content = "Matter: {$matter->matter_number}\nTitle: {$matter->title}\n\nThis workspace summary records the current instructions, initial legal analysis, and agreed next actions for the matter team.\n";

        Storage::disk('local')->put($path, $content);

        return Document::query()->firstOrCreate(
            ['path' => $path],
            [
                'matter_id' => $matter->id,
                'client_id' => $matter->client_id,
                'uploaded_by_user_id' => $matter->responsible_user_id,
                'name' => 'Matter workspace summary',
                'category' => 'correspondence',
                'visibility' => 'internal',
                'disk' => 'local',
                'original_filename' => Str::slug($matter->matter_number).'-workspace-summary.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($content),
                'version' => 1,
                'metadata' => ['seed_source' => 'practice_demo_workspace_2026'],
            ],
        );
    }

    private function seedNote(Matter $matter): void
    {
        MatterNote::query()->firstOrCreate(
            [
                'matter_id' => $matter->id,
                'body' => 'Initial workspace review completed. The key documents have been identified, the matter plan is in progress, and the next client update has been scheduled.',
            ],
            [
                'author_user_id' => $matter->responsible_user_id,
                'visibility' => 'internal',
            ],
        );
    }

    private function seedEmail(Matter $matter, Document $document): void
    {
        $recipient = $matter->client->email;

        if ($recipient === null || $recipient === '') {
            return;
        }

        $email = MatterEmail::query()->firstOrCreate(
            [
                'matter_id' => $matter->id,
                'subject' => 'Matter update: '.$matter->title,
            ],
            [
                'sent_by_user_id' => $matter->responsible_user_id,
                'from_email' => 'info@zolomphi.com',
                'to_recipients' => [$recipient],
                'cc_recipients' => [],
                'body' => 'We have completed the initial review and attached a short workspace summary. We will provide the next update after the scheduled matter review.',
                'status' => 'sent',
                'sent_at' => now()->subDay(),
            ],
        );
        $email->documents()->syncWithoutDetaching([$document->id]);
    }

    private function seedCalendarEvent(Matter $matter, int $matterIndex): void
    {
        $startsAt = now()->addDays(5 + $matterIndex)->setTime(10, 0);
        $event = Appointment::query()->firstOrCreate(
            [
                'matter_id' => $matter->id,
                'title' => 'Matter workspace progress review',
            ],
            [
                'client_id' => $matter->client_id,
                'organizer_user_id' => $matter->responsible_user_id,
                'description' => 'Review progress against the matter plan and prepare the next client update.',
                'type' => 'meeting',
                'status' => 'scheduled',
                'location' => 'Zolomphi Boardroom',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
                'is_all_day' => false,
            ],
        );
        $event->attendees()->syncWithoutDetaching([$matter->responsible_user_id]);
    }

    private function seedExpense(Matter $matter, ExpenseCategory $expenseCategory, int $matterIndex): void
    {
        Expense::query()->firstOrCreate(
            ['expense_number' => 'EXP-DEMO-WS-'.$matter->id],
            [
                'matter_id' => $matter->id,
                'expense_category_id' => $expenseCategory->id,
                'recorded_by_user_id' => $matter->responsible_user_id,
                'category' => $expenseCategory->name,
                'vendor' => 'Document and registry services',
                'currency' => 'MWK',
                'amount' => 25000 + ($matterIndex * 5000),
                'incurred_at' => now()->subDays(2 + $matterIndex)->toDateString(),
                'is_billable' => true,
                'status' => 'recorded',
                'description' => 'Initial matter administration and document handling disbursement.',
            ],
        );
    }

    private function seedCharge(Matter $matter, BillingItem $billingItem, int $matterIndex): void
    {
        MatterCharge::query()->firstOrCreate(
            [
                'matter_id' => $matter->id,
                'description' => 'Initial workspace legal services',
            ],
            [
                'billing_item_id' => $billingItem->id,
                'created_by_user_id' => $matter->responsible_user_id,
                'quantity' => 1,
                'unit_price' => 150000 + ($matterIndex * 25000),
                'tax_rate' => 0,
                'amount' => 150000 + ($matterIndex * 25000),
                'status' => 'billable',
                'charged_at' => now()->subDays($matterIndex)->toDateString(),
            ],
        );
    }

    private function billingItem(): BillingItem
    {
        return BillingItem::query()->where('is_active', true)->orderBy('sort_order')->first()
            ?? BillingItem::query()->create([
                'name' => 'Professional legal services',
                'code' => 'practice-demo-workspace-services',
                'description' => 'Demo workspace legal services charge.',
                'unit' => 'matter',
                'default_amount' => 150000,
                'tax_rate' => 0,
                'is_active' => true,
                'sort_order' => 999,
            ]);
    }

    private function expenseCategory(): ExpenseCategory
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('sort_order')->first()
            ?? ExpenseCategory::query()->create([
                'name' => 'Matter administration',
                'code' => 'practice-demo-workspace-administration',
                'description' => 'Demo workspace matter administration costs.',
                'is_billable_by_default' => true,
                'is_active' => true,
                'sort_order' => 999,
            ]);
    }
}
