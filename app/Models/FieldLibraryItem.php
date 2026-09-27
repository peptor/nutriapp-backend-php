<?php

namespace App\Models;

use App\Models\Concerns\HasRuleColumns;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldLibraryItem extends Model
{
    use HasRuleColumns;
    use HasUuidPrimaryKey;

    protected $table = 'mst_field_library_items';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['createdById', 'name', 'label', 'fieldType', 'fieldIconId', 'frequency', 'required', 'options', 'helpText', 'scaleMin', 'scaleMax', 'goodDirection', 'unit'];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
            'createdAt' => 'datetime',
            'scaleMin' => 'integer',
            'scaleMax' => 'integer',
        ];
    }

    public function fieldIcon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'fieldIconId');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }
}
