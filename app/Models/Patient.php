<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'sys_patients';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['userId', 'nutricionistaId', 'birthDate', 'gender', 'photoUrl', 'notes'];

    protected function casts(): array
    {
        return [
            'birthDate' => 'date:Y-m-d',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class, 'patientId');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DailyRecord::class, 'patientId');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patientId');
    }
}
