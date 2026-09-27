<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Alerta per al nutricionista (reg_alerts): una incidència d'un camp d'una assignació, amb estat OPEN / SEEN / RESOLVED.
class Alert extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_alerts';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['assignmentId', 'fieldName', 'recordId', 'recordDate', 'type', 'level', 'severity', 'value', 'reference', 'message', 'status', 'seenAt', 'resolvedAt', 'patientSeenAt'];

    protected function casts(): array
    {
        return ['recordDate' => 'date:Y-m-d', 'seenAt' => 'datetime', 'resolvedAt' => 'datetime', 'patientSeenAt' => 'datetime', 'createdAt' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoutineAssignment::class, 'assignmentId');
    }
}
