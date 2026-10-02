<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Visita nutricionista <-> pacient. `reason` és el tipus/descripció de la visita; `status`: REQUESTED (sol·licitada pel
// pacient, pendent que el nutricionista l'accepti), CONFIRMED, CANCELLED (anul·lada pel pacient) o REJECTED (rebutjada pel
// nutricionista). El calendari i les "pròximes cites" només compten les CONFIRMED (scope `confirmed`).
class Appointment extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_appointments';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'patientId', 'nutricionistaId', 'startAt', 'endAt', 'reason',
        'status', 'modality', 'location', 'patientNotes', 'requestedBy', 'threadId', 'cancelledAt',
    ];

    protected function casts(): array
    {
        return [
            'startAt' => 'datetime',
            'endAt' => 'datetime',
            'cancelledAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'CONFIRMED');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }
}
