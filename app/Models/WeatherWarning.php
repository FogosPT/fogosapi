<?php


namespace App\Models;

use App\Observers\WeatherWarningObserver;
use MongoDB\Laravel\Eloquent\Model;

class WeatherWarning extends Model
{
    use WeatherWarningObserver;

    protected $connection = 'mongodb';
    protected $table = 'weather_warnings';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'created'   => 'datetime',
        'updated'   => 'datetime',
        'startTime' => 'datetime',
        'endTime'   => 'datetime',
    ];

    protected $fillable = [
        'reportDate',
        'text',
        'type',
        'district',
        'level',
        'startTime',
        'endTime',
        'control'
    ];

    public function getLevelPT()
    {
        switch ($this->level){
            case 'yellow':
                return 'amarelo';
            case 'orange':
                return 'laranja';
            case 'red':
                return 'vermelho';
            default:
                return $this->level;
        }
    }
}
