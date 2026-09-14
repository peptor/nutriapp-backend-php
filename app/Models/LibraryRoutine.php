<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryRoutine extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'library_routines';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['slug', 'name', 'description', 'objective', 'durationDays', 'caution', 'sourceName', 'sourceUrl', 'iconId'];

    protected function casts(): array
    {
        return ['createdAt' => 'datetime', 'durationDays' => 'integer'];
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'iconId');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(LibraryRoutineField::class, 'libraryRoutineId');
    }

    public function foods(): HasMany
    {
        return $this->hasMany(LibraryRoutineFood::class, 'libraryRoutineId');
    }

    public function instructions(): HasMany
    {
        return $this->hasMany(LibraryRoutineInstruction::class, 'libraryRoutineId');
    }
}
