<?php

namespace App\Models;

use App\Services\ClientContactManager;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $client_number
 * @property string $type
 * @property string|null $full_name
 * @property string|null $organization_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $alternate_phone
 * @property int|null $email_contact_id
 * @property int|null $phone_contact_id
 * @property int|null $alternate_phone_contact_id
 * @property string|null $normalized_name
 * @property string $status
 */
#[Fillable([
    'client_number',
    'type',
    'full_name',
    'organization_name',
    'contact_person',
    'email',
    'phone',
    'alternate_phone',
    'email_contact_id',
    'phone_contact_id',
    'alternate_phone_contact_id',
    'normalized_name',
    'tax_number',
    'registration_number',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
    'notes',
    'metadata',
    'status',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Client $client): void {
            $name = $client->full_name ?? $client->organization_name;
            $client->normalized_name = $name === null
                ? null
                : app(ClientContactManager::class)->normalizeName($name);
        });
    }

    /** @return BelongsTo<ClientContactPoint, $this> */
    public function emailContact(): BelongsTo
    {
        return $this->belongsTo(ClientContactPoint::class, 'email_contact_id');
    }

    /** @return BelongsTo<ClientContactPoint, $this> */
    public function phoneContact(): BelongsTo
    {
        return $this->belongsTo(ClientContactPoint::class, 'phone_contact_id');
    }

    /** @return BelongsTo<ClientContactPoint, $this> */
    public function alternatePhoneContact(): BelongsTo
    {
        return $this->belongsTo(ClientContactPoint::class, 'alternate_phone_contact_id');
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->emailContact?->value,
            set: fn (?string $value): array => [
                'email_contact_id' => app(ClientContactManager::class)->resolveId('email', $value),
            ],
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->phoneContact?->value,
            set: fn (?string $value): array => [
                'phone_contact_id' => app(ClientContactManager::class)->resolveId('phone', $value),
            ],
        );
    }

    protected function alternatePhone(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->alternatePhoneContact?->value,
            set: fn (?string $value): array => [
                'alternate_phone_contact_id' => app(ClientContactManager::class)->resolveId('phone', $value),
            ],
        );
    }

    /**
     * Legal matters opened for this client.
     *
     * @return HasMany<Matter, $this>
     */
    public function matters(): HasMany
    {
        return $this->hasMany(Matter::class);
    }

    /**
     * Documents filed against this client, including documents created from a matter workspace.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The system-managed Company Drive folder for this client or organization.
     *
     * @return HasOne<CompanyDriveFolder, $this>
     */
    public function companyDriveFolder(): HasOne
    {
        return $this->hasOne(CompanyDriveFolder::class);
    }

    /**
     * Invoices raised for this client.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
