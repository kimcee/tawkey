<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceMessage extends Model
{
    protected $fillable = [
        'channel',
        'path',
        'url',
        'duration',
        'callsign',
    ];

    protected $casts = [
        'channel' => 'integer',
        'duration' => 'float',
    ];
}
