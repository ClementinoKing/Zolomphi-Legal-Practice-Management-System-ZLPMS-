<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\CompanyDrive\EnsureClientDriveFolder;
use App\Actions\Notifications\SendPracticeAlert;
use App\Http\Requests\MatterWorkRequest;
use App\Mail\MatterEmailMessage;
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
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatterWorkspaceController extends Controller
{
    public function __construct(
        private readonly EnsureClientDriveFolder $ensureClientDriveFolder,
        private readonly RecordAuditEvent $audit,
        private readonly SendPracticeAlert $alerts,
    ) {}

    /** @var list<string> */
    private const WORK_AREAS = ['tasks', 'time-entries', 'documents', 'notes', 'emails', 'calendar-events', 'expenses', 'flat-fees'];

    public function show(Matter $matter): Response
    {
        $matter->load([
            'client:id,client_number,full_name,organization_name,email,phone',
            'matterType:id,name',
            'responsibleUser:id,name,email',
            'attorneys:id,name,email',
            'tasks.assignees:id,name',
            'timeEntries.user:id,name',
            'documents.uploader:id,name',
            'notes.author:id,name',
            'emails.sender:id,name',
            'emails.documents:id,name,original_filename',
            'appointments.attendees:id,name',
            'expenses.categoryRecord:id,name',
            'charges.billingItem:id,name',
        ]);

        $activity = ActivityLog::query()
            ->whereBelongsTo($matter)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(12)
            ->get(['id', 'user_id', 'event', 'description', 'created_at']);

        return Inertia::render('matters/show', [
            'matter' => $matter,
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'expenseCategories' => ExpenseCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_billable_by_default']),
            'billingItems' => BillingItem::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'default_amount', 'tax_rate']),
            'activity' => $activity,
            'summary' => [
                'open_tasks' => $matter->tasks->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'upcoming_events' => $matter->appointments->filter(fn (Appointment $event): bool => $event->starts_at->isFuture())->count(),
                'billable_time_amount' => $matter->timeEntries
                    ->filter(fn (TimeEntry $entry): bool => $entry->is_billable)
                    ->sum(fn (TimeEntry $entry): float => (float) $entry->hours * (float) ($entry->hourly_rate ?? 0)),
                'expenses_amount' => $matter->expenses->sum('amount'),
                'flat_fees_amount' => $matter->charges->sum('amount'),
            ],
        ]);
    }

    public function store(MatterWorkRequest $request, Matter $matter, string $work): RedirectResponse
    {
        abort_unless(in_array($work, self::WORK_AREAS, true), 404);

        $record = match ($work) {
            'tasks' => $this->storeTask($request, $matter),
            'time-entries' => $this->storeTimeEntry($request, $matter),
            'documents' => $this->storeDocument($request, $matter),
            'notes' => $this->storeNote($request, $matter),
            'emails' => $this->storeEmail($request, $matter),
            'calendar-events' => $this->storeCalendarEvent($request, $matter),
            'expenses' => $this->storeExpense($request, $matter),
            'flat-fees' => $this->storeFlatFee($request, $matter),
        };

        $this->recordActivity($matter, $request->user(), $work.'.created', 'Added a '.$this->label($work).'.', $record);

        if ($record instanceof Document) {
            $this->alerts->handle(
                $matter->attorneys()->get(),
                $request->user(),
                [
                    'title' => 'Document added',
                    'body' => $record->name.' was added to '.$matter->matter_number.'.',
                    'url' => route('matters.show', $matter),
                    'type' => 'document',
                ],
            );
        }

        return to_route('matters.show', $matter);
    }

    public function update(MatterWorkRequest $request, Matter $matter, string $work, int $record): RedirectResponse
    {
        abort_unless(in_array($work, self::WORK_AREAS, true) && $work !== 'emails', 404);

        $updated = match ($work) {
            'tasks' => $this->updateTask($request, $matter->tasks()->findOrFail($record)),
            'time-entries' => $this->updateTimeEntry($request, $matter->timeEntries()->findOrFail($record)),
            'documents' => $this->updateDocument($request, $matter->documents()->findOrFail($record)),
            'notes' => $this->updateNote($request, $matter->notes()->findOrFail($record)),
            'calendar-events' => $this->updateCalendarEvent($request, $matter->appointments()->findOrFail($record)),
            'expenses' => $this->updateExpense($request, $matter->expenses()->findOrFail($record)),
            'flat-fees' => $this->updateFlatFee($request, $matter->charges()->findOrFail($record)),
        };

        $this->recordActivity($matter, $request->user(), $work.'.updated', 'Updated a '.$this->label($work).'.', $updated);

        return to_route('matters.show', $matter);
    }

    public function completeTask(Matter $matter, Task $task): RedirectResponse
    {
        abort_unless($task->matter_id === $matter->id, 404);

        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->recordActivity($matter, auth()->user(), 'tasks.completed', 'Completed task: '.$task->title, $task);

        return to_route('matters.show', $matter);
    }

    public function download(Matter $matter, Document $document): StreamedResponse
    {
        abort_unless($document->matter_id === $matter->id, 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function downloadReceipt(Matter $matter, Expense $expense): StreamedResponse
    {
        abort_unless($expense->matter_id === $matter->id && $expense->receipt_path !== null && $expense->receipt_disk !== null, 404);

        return Storage::disk($expense->receipt_disk)->download($expense->receipt_path);
    }

    private function storeTask(MatterWorkRequest $request, Matter $matter): Task
    {
        $task = $matter->tasks()->create([...Arr::except($request->validated(), 'assignee_ids'), 'created_by_user_id' => $request->user()->id]);
        $sync = $task->assignees()->sync($request->validated('assignee_ids', []));
        $this->notifyTaskAssignees($task, $request->user(), $sync['attached']);

        return $task;
    }

    private function updateTask(MatterWorkRequest $request, Task $task): Task
    {
        $task->update(Arr::except($request->validated(), 'assignee_ids'));
        $sync = $task->assignees()->sync($request->validated('assignee_ids', []));
        $this->notifyTaskAssignees($task, $request->user(), $sync['attached']);

        return $task;
    }

    private function storeTimeEntry(MatterWorkRequest $request, Matter $matter): TimeEntry
    {
        return $matter->timeEntries()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'hourly_rate' => $request->validated('hourly_rate') ?? $matter->default_hourly_rate,
        ]);
    }

    private function updateTimeEntry(MatterWorkRequest $request, TimeEntry $timeEntry): TimeEntry
    {
        $timeEntry->update($request->validated());

        return $timeEntry;
    }

    private function storeDocument(MatterWorkRequest $request, Matter $matter): Document
    {
        $file = $request->file('document');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $disk = config('filesystems.default');

        if (! is_string($disk)) {
            throw new \LogicException('The default filesystem disk must be a string.');
        }

        $path = $file->store('matters/'.$matter->id.'/documents', $disk);

        return $matter->documents()->create([
            'client_id' => $matter->client_id,
            'company_drive_folder_id' => $this->ensureClientDriveFolder
                ->handle($matter->client, $request->user())
                ->id,
            'uploaded_by_user_id' => $request->user()->id,
            'name' => $request->validated('name') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'category' => $request->validated('category'),
            'visibility' => $request->validated('visibility'),
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    private function updateDocument(MatterWorkRequest $request, Document $document): Document
    {
        $data = Arr::except($request->validated(), 'document');
        $file = $request->file('document');

        if ($file instanceof UploadedFile) {
            $disk = config('filesystems.default');

            if (! is_string($disk)) {
                throw new \LogicException('The default filesystem disk must be a string.');
            }

            $data = [
                ...$data,
                'disk' => $disk,
                'path' => $file->store('matters/'.$document->matter_id.'/documents', $disk),
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ];
        }

        $document->update($data);

        return $document;
    }

    private function storeNote(MatterWorkRequest $request, Matter $matter): MatterNote
    {
        return $matter->notes()->create(['author_user_id' => $request->user()->id, 'body' => $request->validated('body')]);
    }

    private function updateNote(MatterWorkRequest $request, MatterNote $note): MatterNote
    {
        $note->update($request->validated());

        return $note;
    }

    private function storeEmail(MatterWorkRequest $request, Matter $matter): MatterEmail
    {
        $email = $matter->emails()->create([
            'sent_by_user_id' => $request->user()->id,
            'from_email' => config('mail.from.address'),
            'to_recipients' => $request->validated('to'),
            'cc_recipients' => $request->validated('cc', []),
            'subject' => $request->validated('subject'),
            'body' => $request->validated('body'),
            'status' => 'queued',
        ]);
        $documentIds = $request->validated('document_ids', []);
        $documents = $matter->documents()->whereIn('id', $documentIds)->get();

        abort_unless($documents->count() === count($documentIds), 422);
        $email->documents()->sync($documents->modelKeys());
        $email->load('documents');
        Mail::to($email->to_recipients)->cc($email->cc_recipients ?? [])->queue(new MatterEmailMessage($email));

        return $email;
    }

    private function storeCalendarEvent(MatterWorkRequest $request, Matter $matter): Appointment
    {
        $event = $matter->appointments()->create([
            ...Arr::except($request->validated(), 'attendee_ids'),
            'client_id' => $matter->client_id,
            'organizer_user_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);
        $event->attendees()->sync($request->validated('attendee_ids', []));

        return $event;
    }

    private function updateCalendarEvent(MatterWorkRequest $request, Appointment $event): Appointment
    {
        $event->update(Arr::except($request->validated(), 'attendee_ids'));
        $event->attendees()->sync($request->validated('attendee_ids', []));

        return $event;
    }

    private function storeExpense(MatterWorkRequest $request, Matter $matter): Expense
    {
        $data = $request->validated();
        $category = ExpenseCategory::query()->whereKey($request->integer('expense_category_id'))->firstOrFail();
        $disk = config('filesystems.default');
        $receipt = $request->file('receipt');

        if ($receipt !== null && ! $receipt instanceof UploadedFile) {
            abort(422);
        }

        if (! is_string($disk)) {
            throw new \LogicException('The default filesystem disk must be a string.');
        }

        $receiptPath = $receipt?->store('matters/'.$matter->id.'/expenses', $disk);

        return $matter->expenses()->create([
            ...Arr::except($data, 'receipt'),
            'recorded_by_user_id' => $request->user()->id,
            'expense_number' => sprintf('EXP-%06d', ((int) Expense::query()->max('id')) + 1),
            'category' => $category->name,
            'receipt_path' => $receiptPath,
            'receipt_disk' => $receiptPath === null ? null : $disk,
        ]);
    }

    private function updateExpense(MatterWorkRequest $request, Expense $expense): Expense
    {
        $data = $request->validated();
        $category = ExpenseCategory::query()->whereKey($request->integer('expense_category_id'))->firstOrFail();
        $receipt = $request->file('receipt');

        if ($receipt !== null && ! $receipt instanceof UploadedFile) {
            abort(422);
        }

        $attributes = [
            ...Arr::except($data, 'receipt'),
            'category' => $category->name,
        ];

        if ($receipt instanceof UploadedFile) {
            $disk = config('filesystems.default');

            if (! is_string($disk)) {
                throw new \LogicException('The default filesystem disk must be a string.');
            }

            $attributes = [
                ...$attributes,
                'receipt_disk' => $disk,
                'receipt_path' => $receipt->store('matters/'.$expense->matter_id.'/expenses', $disk),
            ];
        }

        $expense->update($attributes);

        return $expense;
    }

    private function storeFlatFee(MatterWorkRequest $request, Matter $matter): MatterCharge
    {
        $data = $request->validated();
        $amount = round((float) $data['quantity'] * (float) $data['unit_price'] * (1 + ((float) $data['tax_rate'] / 100)), 2);

        return $matter->charges()->create([...$data, 'created_by_user_id' => $request->user()->id, 'amount' => $amount]);
    }

    private function updateFlatFee(MatterWorkRequest $request, MatterCharge $charge): MatterCharge
    {
        $data = $request->validated();
        $amount = round((float) $data['quantity'] * (float) $data['unit_price'] * (1 + ((float) $data['tax_rate'] / 100)), 2);
        $charge->update([...$data, 'amount' => $amount]);

        return $charge;
    }

    /** @param array<int, int|string> $assigneeIds */
    private function notifyTaskAssignees(Task $task, User $actor, array $assigneeIds): void
    {
        if ($assigneeIds === []) {
            return;
        }

        $this->alerts->handle(
            User::query()->whereKey($assigneeIds)->get(),
            $actor,
            [
                'title' => 'Task assigned',
                'body' => 'You were assigned to '.$task->title.'.',
                'url' => route('matters.show', $task->matter_id),
                'type' => 'task',
            ],
        );
    }

    private function recordActivity(Matter $matter, ?User $user, string $event, string $description, Model $subject): void
    {
        $this->audit->handle(
            user: $user,
            event: $event,
            description: $description,
            subject: $subject,
            clientId: $matter->client_id,
            matterId: $matter->id,
        );
    }

    private function label(string $work): string
    {
        return str_replace('-', ' ', rtrim($work, 's'));
    }
}
