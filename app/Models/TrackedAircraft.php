<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TrackedAircraft extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'tracked_aircraft';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    public const KIND_AIRPLANE = 'airplane';
    public const KIND_HELICOPTER = 'helicopter';

    protected $fillable = [
        'icao',
        'registration',
        'name',
        'type',
        'kind',
        'base',
        'operator',
        'notify',
        'active',
        'notes',
    ];

    protected $casts = [
        'notify' => 'boolean',
        'active' => 'boolean',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $attributes = [
        'kind' => self::KIND_AIRPLANE,
    ];

    public function getKindAttribute(?string $value): string
    {
        return $value ?: self::KIND_AIRPLANE;
    }
}
