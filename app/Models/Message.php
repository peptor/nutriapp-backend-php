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
        'attachmentPath', 'attachmentName', 'attachmentMime', 'attachmentSize', 'readAt',
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

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'senderId');
    }
}
