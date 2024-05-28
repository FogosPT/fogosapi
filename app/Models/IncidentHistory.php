<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class IncidentHistory extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'history';

    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';

    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'id',
        'sharepointId',
        'aerial',
        'terrain',
        'location',
        'man',
    ];
}
