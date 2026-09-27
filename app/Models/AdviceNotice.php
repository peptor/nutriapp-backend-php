<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Un consell ja disparat (reg_advice_notices): evita repetir el push el mateix dia i alimenta el bloc "Consells"
// de l'Avui del pacient. Es desnormalitza el missatge perquè sobrevisqui si la regla es toca després.
// docs/com-funcionen-les-alertes.md.
class AdviceNotice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_advice_notices';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['assignmentId', 'fieldName', 'recordDate', 'ruleKey', 'message'];

    protected function casts(): array
    {
        return ['recordDate' => 'date:Y-m-d', 'createdAt' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoutineAssignment::class, 'assignmentId');
    }
}
