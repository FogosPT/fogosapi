<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Warning extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'warning';

    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';

    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'id',
        'text',
    ];
}
