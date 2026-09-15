<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodRecentSelection extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_food_recent_selections';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['patientId', 'foodId', 'lastSelectedAt'];

    protected function casts(): array
    {
        return ['lastSelectedAt' => 'datetime'];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'foodId');
    }
}
