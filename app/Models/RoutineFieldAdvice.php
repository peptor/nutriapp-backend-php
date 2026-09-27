<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Consell d'un camp d'una plantilla concreta: heretat de la biblioteca (sourceLibraryAdviceId) o escrit
// directament pel nutricionista (llavors no cal validació: ell mateix n'és l'autor).
// docs/com-funcionen-les-alertes.md.
class RoutineFieldAdvice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_routine_field_advice';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['routineFieldId', 'operator', 'thresholdValue', 'message', 'orderIndex', 'sourceLibraryAdviceId'];

    protected function casts(): array
    {
        return ['thresholdValue' => 'decimal:2', 'orderIndex' => 'integer', 'createdAt' => 'datetime'];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(RoutineField::class, 'routineFieldId');
    }
}
