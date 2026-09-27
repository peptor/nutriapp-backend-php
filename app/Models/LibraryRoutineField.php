<?php

namespace App\Models;

use App\Models\Concerns\HasRuleColumns;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryRoutineField extends Model
{
    use HasRuleColumns;
    use HasUuidPrimaryKey;

    protected $table = 'mst_library_routine_fields';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['libraryRoutineId', 'name', 'label', 'fieldType', 'fieldIconId', 'frequency', 'required', 'options', 'orderIndex', 'helpText', 'scaleMin', 'scaleMax', 'goodDirection', 'unit'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'options' => 'array', 'scaleMin' => 'integer', 'scaleMax' => 'integer'];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(LibraryRoutine::class, 'libraryRoutineId');
    }

    public function fieldIcon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'fieldIconId');
    }

    public function advice(): HasMany
    {
        return $this->hasMany(LibraryFieldAdvice::class, 'libraryRoutineFieldId')->orderBy('orderIndex');
    }
}
