<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutricionistaProfile extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'nutricionista_profiles';

    public static $snakeAttributes = false;

    const CREATED_AT = null;
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['userId', 'companyName', 'taxId', 'address', 'postalCode', 'city', 'phone', 'logoUrl'];

    protected function casts(): array
    {
        return ['updatedAt' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
