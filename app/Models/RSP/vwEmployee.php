<?php

namespace App\Models\RSP;

use Illuminate\Database\Eloquent\Model;

class vwEmployee extends Model
{
    //

    protected $connection = 'second_db';

    protected $table = 'vwEmployee';
    protected $primaryKey = 'ControlNo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // dahil view, walang totoong created_at/updated_at column maliban na lang idinagdag mo
}
