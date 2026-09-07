<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceRating extends Model
{
    //

    protected $table = 'performance_ratings';


    protected $fillable = [

        // 'target_period_id',
        'performance_standard_id',
        'control_no',
        'date',
        'quantity_actual',
        'effectiveness_actual',
        'timeliness_actual',
        'status',
        'remarks'


    ];

    

    protected $casts = [
        'quantity_actual' => 'integer',
        'effectiveness_actual' => 'integer',
        'timeliness_actual' => 'integer',
    ];


    public function performanceStandard()
    {
        return $this->belongsTo(PerformanceStandard::class,);
    }

    public function dropdownRating()
    {
        return $this->hasMany(Performance_dropdown_rating::class);
    }
}
