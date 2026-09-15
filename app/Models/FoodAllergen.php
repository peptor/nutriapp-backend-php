<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodAllergen extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_food_allergen';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['foodId', 'allergenId', 'confianca'];

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class, 'allergenId');
    }
}
