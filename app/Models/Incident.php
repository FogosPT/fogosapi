<?php

namespace App\Models;

use App\Observers\IncidentObserver;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\Model;

class Incident extends Model
{
    use IncidentObserver;

    protected $connection = 'mongodb';
    protected $collection = 'data';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $dates = ['dateTime', 'created', 'updated'];

    protected $casts = [
        'active' => 'boolean',
        'aerial' => 'integer',
        'coords' => 'boolean',
        'dico' => 'string',
        'disappear' => 'boolean',
        'id' => 'string',
        'important' => 'boolean',
        'isFire' => 'boolean',
        'isOtherFire' => 'boolean',
        'isOtherIncident' => 'boolean',
        'isTransporteFire' => 'boolean',
        'isUrbanFire' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'man' => 'integer',
        'naturezaCode' => 'string',
        'sadoId' => 'string',
        'sharepointId' => 'integer',
        'statusCode' => 'integer',
        'terrain' => 'integer',
        'detailLocation' => 'string',
        'kml' => 'string',
        'kmlVost' => 'string',
        'especieName' => 'string',
        'familiaName' => 'string',
        'heliFight' => 'integer',
        'heliCoord' => 'integer',
        'planeFight' => 'integer',
        'anepcDirectUpdate' => 'boolean'
    ];

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
        'isFMA',
        'lastTweetId',
        'notifyBig',
        'coordinates',
        'kml',
        'kmlVost',
        'icnf',
        'especieName',
        'familiaName',
        'heliFight',
        'heliCoord',
        'planeFight',
        'anepcDirectUpdate',
        'regiao',
        'sub_regiao'
    ];

    public const NATUREZA_CODE_FIRE = [
        '3101',
        '3103',
        '3105',
    ];

    public const NATUREZA_CODE_URBAN_FIRE = [
        '2101',
        '2103',
        '2105',
        '2107',
        '2109',
        '2111',
        '2113',
        '2115',
        '2117',
        '2119',
        '2121',
        '2123',
        '2125',
        '2127',
        '2129',
    ];

    public const NATUREZA_CODE_TRANSPORT_FIRE = [
        '2301',
        '2303',
        '2305',
        '2307',
    ];

    public const NATUREZA_CODE_OTHER_FIRE = [
        '3201',
        '3203',
        '2201',
        '2203',
        '3111',
        '3109',
        '3107'
    ];

    public const NATUREZA_CODE_FMA = [
        '3315',
        '3317',
        '3301',
        '4305',
        '3309',
        '2419',
        '3313',
        '3319',
        '3321',
        '3329',
        '4329',
        '4339',
    ];

    public const ACTIVE_STATUS_CODES = [
        3, 4, 5, 6,
    ];

    public const NOT_ACTIVE_STATUS_CODES = [
        7,8,9,10,11,12
    ];

    public const STATUS_ID = [
        'Despacho de 1.º Alerta' => 4,
        'Despacho' => 3,
        'Em Resolução' => 7,
        'Conclusão' => 8,
        'Vigilância' => 9,
        'Em Curso' => 5,
        'Chegada ao TO' => 6
    ];


    public const STATUS_COLORS = [
        '  DESPACHO DE 1º ALERTA' => 'FF6E02', // sometimes we get this value...
        ' Encerrada' => '6ABF59', // sometimes we get this value...
        'Despacho de 1.º Alerta' => 'FF6E02',
        'Despacho' => 'FF6E02',
        'Em Resolução' => '6ABF59',
        'Conclusão' => 'BDBDBD',
        'Vigilância' => '6ABF59',
        'Em Curso' => 'B81E1F',
        'Chegada ao TO' => 'B81E1F',
        3 => 'CE773C',
        4 => 'FF6E02',
        5 => 'B81E1F',
        6 => 'B81E1F',
        7 => '65C4ED',
        8 => '8e7e7d',
        9 => '65C4ED',
        10 => '6ABF59',
        11 => 'BDBDBD',
        12 => 'BDBDBD',
    ];

    public function scopeIsActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeIsFire(Builder $query): Builder
    {
        return $query->where('isFire', true);
    }

    public function scopeIsFMA(Builder $query): Builder
    {
        return $query->where('isFMA', true);
    }

    public function scopeIsOtherFire(Builder $query): Builder
    {
        return $query->where('isOtherFire', true)->orWhere('isTransporteFire', true)->orWhere('isUrbanFire', true);
    }

    public function getDateTimeObjectAttribute(): array
    {
        return [
            'sec' => $this->dateTime->getTimestamp(),
        ];
    }

    public function getCreatedObjectAttribute(): array
    {
        return [
            'sec' => $this->created->getTimestamp(),
        ];
    }

    public function getUpdatedObjectAttribute(): array
    {
        return [
            'sec' => $this->updated->getTimestamp(),
        ];
    }

    public function history()
    {
        return $this->hasMany(IncidentHistory::class, 'id', 'id');
    }

    public function statusHistory()
    {
        return $this->hasMany(IncidentStatusHistory::class, 'id', 'id');

    }
}
