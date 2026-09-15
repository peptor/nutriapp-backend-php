<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_daily_records';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['assignmentId', 'patientId', 'recordDate', 'fieldName', 'value', 'notes', 'recordedBy'];

    protected function casts(): array
    {
        return [
            'recordDate' => 'date:Y-m-d',
            'value' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoutineAssignment::class, 'assignmentId');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }
}
