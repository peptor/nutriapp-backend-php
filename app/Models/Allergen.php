<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Allergen extends Model
{
    protected $table = 'mst_allergens';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $casts = ['actiu' => 'boolean'];

    public function translation(): HasOne
    {
        return $this->hasOne(Translation::class, 'entityId', 'id')->where('entityType', 'allergen');
    }
}
