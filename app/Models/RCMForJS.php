<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class RCMForJS extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'rcmJS';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'dataPrev',
        'dataRun',
        'fileDate',
        'local',
        'created',
        'updated',
        'when',
    ];
}
