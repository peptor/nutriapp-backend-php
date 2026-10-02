<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_messages';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'threadId', 'senderId', 'recipientId', 'body',
        'attachmentPath', 'attachmentName', 'attachmentMime', 'attachmentSize', 'readAt', 'appointmentId',
    ];

    protected function casts(): array
    {
        return [
            'readAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'threadId');
    }

    // Visita a què fa referència el missatge (sol·licitud de visita del pacient).
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointmentId');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'senderId');
    }
}
