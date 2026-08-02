<?php

namespace App\Mail;

use App\Models\MatterEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Throwable;

class MatterEmailMessage extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public MatterEmail $matterEmail) {}

    public function build(): self
    {
        $this->matterEmail->update([
            'status' => 'sent',
            'sent_at' => now(),
            'failure_reason' => null,
        ]);

        return $this->from($this->matterEmail->from_email)
            ->subject($this->matterEmail->subject)
            ->html($this->matterEmail->body);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->matterEmail->documents
            ->map(fn ($document): Attachment => Attachment::fromStorageDisk($document->disk, $document->path)
                ->as($document->original_filename)
                ->withMime($document->mime_type ?? 'application/octet-stream'))
            ->all();
    }

    public function failed(Throwable $exception): void
    {
        $this->matterEmail->update([
            'status' => 'failed',
            'failure_reason' => str($exception->getMessage())->limit(1000)->toString(),
        ]);
    }
}
