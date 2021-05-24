<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class HistoryTotal extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'historyTotal';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'aerial',
        'terrain',
        'location',
        'man',
        'total'
    ];
}
