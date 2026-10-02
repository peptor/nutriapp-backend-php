<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Factura de NutriEvo a un nutricionista per una quota EvoPro (reg_invoices). Vegeu App\Support\Invoices.
class Invoice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_invoices';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'nutricionistaId', 'licenseId', 'year', 'sequence', 'number', 'issuedAt', 'periodStart', 'periodEnd', 'concept',
        'baseCents', 'vatRate', 'vatCents', 'totalCents', 'currency', 'issuer', 'recipient', 'paymentRef', 'emailedAt',
    ];

    protected function casts(): array
    {
        return [
            'issuedAt' => 'date:Y-m-d',
            'periodStart' => 'date:Y-m-d',
            'periodEnd' => 'date:Y-m-d',
            'vatRate' => 'float',
            'issuer' => 'array',
            'recipient' => 'array',
            'emailedAt' => 'datetime',
            'createdAt' => 'datetime',
        ];
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }
}
