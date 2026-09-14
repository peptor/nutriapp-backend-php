<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class FieldIcon extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_fieldicons';

    public static $snakeAttributes = false;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = ['key', 'label', 'colorToken'];
}
