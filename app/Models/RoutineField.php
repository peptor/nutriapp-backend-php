<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineField extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_routine_fields';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['templateId', 'name', 'label', 'fieldType', 'frequency', 'required', 'options', 'orderIndex', 'fieldIconId', 'goodDirection', 'unit', 'helpText', 'scaleMin', 'scaleMax'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'options' => 'array', 'createdAt' => 'datetime', 'scaleMin' => 'integer', 'scaleMax' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoutineTemplate::class, 'templateId');
    }

    public function fieldIcon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'fieldIconId');
    }
}
