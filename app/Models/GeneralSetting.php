<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'firm_name',
    'email',
    'phone',
    'website',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'postal_code',
    'country',
    'tax_id',
    'license_number',
    'timezone',
    'currency',
    'invoice_settings',
])]
class GeneralSetting extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'timezone' => 'Africa/Blantyre',
        'currency' => 'MWK',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_settings' => 'array',
        ];
    }
}
