<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineAssignment extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'routine_assignments';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['patientId', 'templateId', 'startDate', 'endDate', 'status', 'evolutionRating', 'customNotes'];

    protected function casts(): array
    {
        return [
            'startDate' => 'date:Y-m-d',
            'endDate' => 'date:Y-m-d',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoutineTemplate::class, 'templateId');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DailyRecord::class, 'assignmentId');
    }
}
