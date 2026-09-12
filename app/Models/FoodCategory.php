<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodCategory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'food_categories';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'slug'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime'];
    }

    public function foods(): HasMany
    {
        return $this->hasMany(Food::class, 'categoryId');
    }
}
