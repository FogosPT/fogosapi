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
        'especieName' => 'string',
        'familiaName' => 'string'
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
        'icnf',
        'especieName',
        'familiaName'
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

    public const STATUS_COLORS = [
        '  DESPACHO DE 1º ALERTA' => 'FF6E02', // sometimes we get this value...
        ' Encerrada' => '6ABF59', // sometimes we get this value...
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
