<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'foods';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'description', 'imageUrl', 'categoryId'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'categoryId');
    }

    public function routines(): HasMany
    {
        return $this->hasMany(RoutineTemplateFood::class, 'foodId');
    }

    public function libraryRoutines(): HasMany
    {
        return $this->hasMany(LibraryRoutineFood::class, 'foodId');
    }
}
