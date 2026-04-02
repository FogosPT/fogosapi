<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class Location extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'locations';
    protected $primaryKey = '_id';

    protected $fillable = [
        'level',
        'code',
        'name'
    ];
}
