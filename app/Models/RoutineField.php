<?php

namespace App\Models;

use App\Models\Concerns\HasRuleColumns;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineField extends Model
{
    use HasRuleColumns;
    use HasUuidPrimaryKey;

    protected $table = 'mst_routine_fields';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['templateId', 'name', 'label', 'fieldType', 'frequency', 'required', 'options', 'orderIndex', 'fieldIconId', 'goodDirection', 'unit', 'helpText', 'scaleMin', 'scaleMax', 'sourceLibraryFieldId'];

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

    public function advice(): HasMany
    {
        return $this->hasMany(RoutineFieldAdvice::class, 'routineFieldId')->orderBy('orderIndex');
    }
}
