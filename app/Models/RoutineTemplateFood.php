<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineTemplateFood extends Model
{
    protected $table = 'mst_routine_template_foods';

    public static $snakeAttributes = false;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['templateId', 'foodId', 'use', 'note'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoutineTemplate::class, 'templateId');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class, 'foodId');
    }
}
