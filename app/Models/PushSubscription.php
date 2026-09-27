<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Dispositiu registrat per rebre notificacions push (sys_push_subscriptions). Una fila per navegador/dispositiu.
class PushSubscription extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'sys_push_subscriptions';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['userId', 'endpoint', 'endpointHash', 'publicKey', 'authToken', 'contentEncoding', 'deviceLabel', 'userAgent', 'lastUsedAt'];

    protected $hidden = ['endpoint', 'publicKey', 'authToken'];

    protected function casts(): array
    {
        return ['lastUsedAt' => 'datetime', 'createdAt' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
