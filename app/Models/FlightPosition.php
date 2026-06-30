<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class FlightPosition extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'flight_positions';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'icao',
        'registration',
        'callsign',
        'aircraft_type',
        'lat',
        'lon',
        'altitude',
        'ground_speed',
        'vertical_speed',
        'track',
        'squawk',
        'on_ground',
        'sampled_at',
        'source',
        'fr24_id',
    ];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'altitude' => 'integer',
        'ground_speed' => 'integer',
        'vertical_speed' => 'integer',
        'track' => 'integer',
        'on_ground' => 'boolean',
        'sampled_at' => 'datetime',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];
}
