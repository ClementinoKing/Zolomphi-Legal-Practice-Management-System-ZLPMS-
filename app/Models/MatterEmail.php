<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $from_email
 * @property string $subject
 * @property string $body
 * @property list<string> $to_recipients
 * @property list<string>|null $cc_recipients
 */
#[Fillable(['matter_id', 'sent_by_user_id', 'from_email', 'to_recipients', 'cc_recipients', 'subject', 'body', 'status', 'sent_at', 'failure_reason'])]
class MatterEmail extends Model
{
    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'matter_email_document');
    }

    protected function casts(): array
    {
        return ['to_recipients' => 'array', 'cc_recipients' => 'array', 'sent_at' => 'datetime'];
    }
}
