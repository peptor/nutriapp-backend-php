<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuidPrimaryKey;

    protected $table = 'sys_users';

    // Totes les columnes i relacions són camelCase (com Prisma); evitem que Eloquent
    // les converteixi a snake_case en serialitzar a JSON.
    public static $snakeAttributes = false;

    public $timestamps = true;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['email', 'passwordHash', 'name', 'role', 'phone', 'deletedAt', 'notifyMessagesByEmail'];

    protected $hidden = ['passwordHash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'deletedAt' => 'datetime',
            'notifyMessagesByEmail' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    // Pacients dels quals aquest usuari és el nutricionista responsable.
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'nutricionistaId');
    }

    // Files Patient del propi usuari quan actua com a PACIENT (una per nutricionista).
    public function patientProfiles(): HasMany
    {
        return $this->hasMany(Patient::class, 'userId');
    }

    public function createdRoutines(): HasMany
    {
        return $this->hasMany(RoutineTemplate::class, 'createdById');
    }

    public function nutricionistaProfile(): HasOne
    {
        return $this->hasOne(NutricionistaProfile::class, 'userId');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'userId');
    }

    public function fieldLibraryItems(): HasMany
    {
        return $this->hasMany(FieldLibraryItem::class, 'createdById');
    }

    public function getAuthPassword(): ?string
    {
        return $this->passwordHash;
    }
}
