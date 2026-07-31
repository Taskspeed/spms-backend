<?php

namespace App\Models\RSP;

use Illuminate\Database\Eloquent\Model;

class EmployeeExtraDetail extends Model
{
    //

    protected $connection = 'second_db';
    protected $table = 'employee_extra_details';
    protected $primaryKey = 'control_no';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['control_no', 'rank', 'job_title', 'suffix', 'prefix'];
}
