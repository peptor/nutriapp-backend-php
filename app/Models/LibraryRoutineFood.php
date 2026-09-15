<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryRoutineFood extends Model
{
    protected $table = 'mst_library_routine_foods';

    public static $snakeAttributes = false;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['libraryRoutineId', 'foodId', 'use', 'note'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(LibraryRoutine::class, 'libraryRoutineId');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'foodId');
    }
}
