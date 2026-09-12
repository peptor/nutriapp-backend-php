<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'alerts';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['patientId', 'assignmentId', 'type', 'message', 'severity', 'isRead'];

    protected function casts(): array
    {
        return ['isRead' => 'boolean', 'createdAt' => 'datetime'];
    }
}
