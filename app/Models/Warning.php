<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'warning';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'id',
        'text'
    ];
}
