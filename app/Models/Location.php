<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\Model;

class Location extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'locations';
    protected $primaryKey = '_id';

    protected $fillable = [
        'level',
        'code',
        'name'
    ];
}
