<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineTemplate extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'routine_templates';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['name', 'description', 'durationDays', 'objective', 'isPublic', 'createdById', 'foodLogEnabled', 'iconId'];

    protected function casts(): array
    {
        return [
            'isPublic' => 'boolean',
            'foodLogEnabled' => 'boolean',
            'durationDays' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(FieldIcon::class, 'iconId');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RoutineField::class, 'templateId');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class, 'templateId');
    }

    public function instructions(): HasMany
    {
        return $this->hasMany(RoutineInstruction::class, 'templateId');
    }

    public function foods(): HasMany
    {
        return $this->hasMany(RoutineTemplateFood::class, 'templateId');
    }
}
