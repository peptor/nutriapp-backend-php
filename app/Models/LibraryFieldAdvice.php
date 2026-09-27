<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Consell predefinit de la biblioteca per a un camp (docs/com-funcionen-les-alertes.md): es copia a
// mst_routine_field_advice en crear una plantilla des de la biblioteca.
class LibraryFieldAdvice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_library_field_advice';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['libraryRoutineFieldId', 'operator', 'thresholdValue', 'message', 'orderIndex'];

    protected function casts(): array
    {
        return ['thresholdValue' => 'decimal:2', 'orderIndex' => 'integer', 'createdAt' => 'datetime'];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(LibraryRoutineField::class, 'libraryRoutineFieldId');
    }
}
