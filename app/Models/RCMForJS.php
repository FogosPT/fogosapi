<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class RCMForJS extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rcmJS';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

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
