<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class RCM extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rcm';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'concelho',
        'date',
        'hoje',
        'amanha',
        'depois',
        'depois2',
        'depois3',
        'dico',
    ];

    public const RCM_TO_HUMAN = [
        1 => 'Reduzido',
        2 => 'Moderado',
        3 => 'Elevado',
        4 => 'Muito Elevado',
        5 => 'Maximo',
    ];
}
