<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $client_number
 * @property string $type
 * @property string|null $full_name
 * @property string|null $organization_name
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
