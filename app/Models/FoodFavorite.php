<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodFavorite extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_food_favorites';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['patientId', 'foodId'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime'];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'foodId');
    }
}
