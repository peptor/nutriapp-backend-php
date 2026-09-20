<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutricionistaProfile extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'sys_nutricionista_profiles';

    public static $snakeAttributes = false;

    const CREATED_AT = null;
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId', 'companyName', 'taxId', 'collegiateNumber', 'address', 'postalCode', 'city', 'phone', 'logoUrl', 'brandingTheme',
        'scheduleMode', 'scheduleCycleStartDate', 'scheduleCycleStartWeek',
    ];

    protected function casts(): array
    {
        return [
            'scheduleCycleStartDate' => 'date:Y-m-d',
            'updatedAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
