<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Un recordatori de registre ja enviat (reg_reminder_notices): alimenta el comptador setmanal de l'Avui del
// pacient. Vegeu App\Support\RegisterReminders i docs/com-funcionen-les-alertes.md.
class ReminderNotice extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_reminder_notices';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = ['assignmentId', 'period', 'recordDate'];

    protected function casts(): array
    {
        return ['recordDate' => 'date:Y-m-d', 'createdAt' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoutineAssignment::class, 'assignmentId');
    }
}
