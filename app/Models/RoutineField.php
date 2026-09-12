<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineField extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'routine_fields';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['templateId', 'name', 'label', 'fieldType', 'frequency', 'required', 'options', 'orderIndex'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'options' => 'array', 'createdAt' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoutineTemplate::class, 'templateId');
    }
}
