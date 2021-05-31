<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarningSite extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'warningSite';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'active',
        'text',
    ];
}
