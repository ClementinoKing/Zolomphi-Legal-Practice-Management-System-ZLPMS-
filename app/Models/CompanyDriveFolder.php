<?php

namespace App\Models;

use Database\Factories\CompanyDriveFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'parent_id',
    'client_id',
    'created_by_user_id',
    'name',
    'library',
    'is_system',
    'metadata',
])]
class CompanyDriveFolder extends Model
{
    /** @use HasFactory<CompanyDriveFolderFactory> */
    use HasFactory, SoftDeletes;

    public const LibraryGeneral = 'general';

    public const LibraryClients = 'clients';

    public const LibraryOrganizations = 'organizations';

    /**
     * @return BelongsTo<CompanyDriveFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CompanyDriveFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
