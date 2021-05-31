<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class WarningMadeira extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'warningMadeira';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'title',
        'description',
        'num',
        'menu',
        'dia_hora',
        'id',
        'label',
    ];
}
