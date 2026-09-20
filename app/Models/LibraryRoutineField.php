<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryRoutineField extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_library_routine_fields';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['libraryRoutineId', 'name', 'label', 'fieldType', 'fieldIconId', 'frequency', 'required', 'options', 'orderIndex', 'helpText'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'options' => 'array'];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(LibraryRoutine::class, 'libraryRoutineId');
    }

    public function fieldIcon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'fieldIconId');
    }
}
