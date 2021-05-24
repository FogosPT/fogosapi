<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class IncidentHistory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'history';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'id',
        'sharepointId',
        'aerial',
        'terrain',
        'location',
        'man',
    ];

}
