<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string|null $mime_type
 */
#[Fillable(['matter_id', 'client_id', 'company_drive_folder_id', 'uploaded_by_user_id', 'name', 'category', 'visibility', 'disk', 'path', 'original_filename', 'mime_type', 'size_bytes', 'version', 'expires_at', 'metadata'])]
class Document extends Model
{
    use SoftDeletes;

    /**
     * @return BelongsTo<Matter, $this>
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<CompanyDriveFolder, $this>
     */
    public function companyDriveFolder(): BelongsTo
    {
        return $this->belongsTo(CompanyDriveFolder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'metadata' => 'array'];
    }
}
