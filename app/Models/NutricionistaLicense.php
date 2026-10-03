<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Una llicència EvoPro d'un nutricionista (reg_nutricionista_licenses). Vegeu App\Support\Licenses.
class NutricionistaLicense extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_nutricionista_licenses';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'nutricionistaId', 'startsAt', 'endsAt', 'billingPeriod', 'source', 'amountCents', 'currency', 'paymentRef', 'createdById', 'note', 'revokedAt', 'expiryReminderSentAt',
    ];

    protected function casts(): array
    {
        return [
            'startsAt' => 'date:Y-m-d',
            'endsAt' => 'date:Y-m-d',
            'revokedAt' => 'datetime',
            'expiryReminderSentAt' => 'datetime',
            'amountCents' => 'integer',
            'createdAt' => 'datetime',
        ];
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }
}
