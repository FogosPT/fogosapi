<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class HistoryTotal extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'historyTotal';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'aerial',
        'terrain',
        'location',
        'man',
        'total',
    ];

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
}
