<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldLibraryItem extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_field_library_items';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['createdById', 'name', 'label', 'fieldType', 'frequency', 'required', 'options', 'helpText'];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
            'createdAt' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }
}
