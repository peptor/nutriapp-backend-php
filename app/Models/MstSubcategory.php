<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MstSubcategory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_subcategory';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['sourceSlugFr'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime'];
    }

    public function translation(): HasOne
    {
        return $this->hasOne(Translation::class, 'entityId', 'id')->where('entityType', 'subcategory');
    }
}
