<?php

namespace App\Models;

use App\Observers\IncidentObserver;
use Jenssegers\Mongodb\Eloquent\Model;

class Incident extends Model
{
    use IncidentObserver;

    protected $connection = 'mongodb';
    protected $collection = 'data';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $dates = ['dateTime', 'created', 'updated'];

    protected $fillable = [
        'id',
        'coords',
        'dateTime',
        'date',
        'hour',
        'location',
        'aerial',
        'terrain',
        'man',
        'district',
        'concelho',
        'dico',
        'freguesia',
        'lat',
        'lng',
        'naturezaCode',
        'natureza',
        'statusCode',
        'statusColor',
        'status',
        'important',
        'localidade',
        'active',
        'sadoId',
        'sharepointId',
        'extra',
        'disappear',
        'detailLocation',
        'isFire',
        'isUrbanFire',
        'isTransporteFire',
        'isOtherFire',
        'isOtherIncident',
        'lastTweetId',
        'notifyBig',
        'coordinates'
    ];

    const NATUREZA_CODE_FIRE = array(
        "3101",
        "3103",
        "3105",
        "3107",
    );

    const NATUREZA_CODE_URBAN_FIRE = array(
        "2101",
        "2103",
        "2105",
        "2107",
        "2109",
        "2111",
        "2113",
        "2115",
        "2117",
        "2119",
        "2121",
        "2123",
        "2125",
        "2127",
        "2129",
    );

    const NATUREZA_CODE_TRANSPORT_FIRE = array(
        "2301",
        "2303",
        "2305",
        "2307",
    );

    const NATUREZA_CODE_OTHER_FIRE = array(
        "3201",
        "3203",
        "2201",
        "2203",
        "3111",
        "3109",
    );

    const ACTIVE_STATUS_CODES = array(
        3,4,5,6
    );


    const STATUS_COLORS = array(
        4 => 'FF6E02',
        3 => 'CE773C',
        5 => 'B81E1F',
        6 => 'B81E1F',
        7 => '65C4ED',
        8 => '8e7e7d',
        ' Encerrada' => '6ABF59', // por vezes vem este valor..
        '  DESPACHO DE 1º ALERTA' => 'FF6E02', // por vezes vem este valor..
        9 => '65C4ED',
        10 => '6ABF59',
        11 => 'BDBDBD',
        12 => 'BDBDBD'

    );
}
