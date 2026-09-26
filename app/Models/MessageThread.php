<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessageThread extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'reg_message_threads';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['patientId', 'subject', 'lastMessageAt'];

    protected function casts(): array
    {
        return [
            'lastMessageAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'threadId');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'threadId')->latestOfMany('createdAt');
    }
}
