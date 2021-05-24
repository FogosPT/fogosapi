<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class RCM extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rcm';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'concelho',
        'date',
        'hoje',
        'amanha',
        'depois',
        'depois2',
        'depois3',
        'dico'
    ];

    const RCM_TO_HUMAN =  array(
        1 => 'Reduzido',
        2 => 'Moderado',
        3 => 'Elevado',
        4 => 'Muito Elevado',
        5 => 'Maximo',
    );
}
