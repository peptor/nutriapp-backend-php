<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_schedule_exceptions';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['nutricionistaId', 'date', 'type', 'startTime', 'endTime', 'description'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }
}
