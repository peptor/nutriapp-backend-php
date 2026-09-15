<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryRoutineInstruction extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_library_routine_instructions';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = ['libraryRoutineId', 'title', 'content', 'orderIndex'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(LibraryRoutine::class, 'libraryRoutineId');
    }
}
