<?php

namespace App\Models;

use App\Listeners\Opcr;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;


class Employee extends Model
{
    //
    use LogsActivity;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'rank',
        'office',
        'division',
        'section',
        'unit',
        'position_id',
        'office_id',
        'ControlNo',
        'group',
        'office2',
        'tblStructureID',
        'sg',
        'level',
        'positionID',
        'itemNo',
        'pageNo',
        'position',
        'status',

        'job_title',
        'suffix',
        'prefix'

    ];

    protected $casts = [
        'office_id' => 'integer',
        // 'position_id' => 'integer',

    ];

    public function office()
    {
        return $this->belongsTo(office::class);
    }

    public function position()
    {
        return $this->belongsTo(position::class);
    }

    public function targetPeriods()
    {
        return $this->hasMany(TargetPeriod::class, 'control_no', 'ControlNo');
    }


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'position', 'rank', 'office', 'division', 'section', 'unit', 'office_id'])
            ->setDescriptionForEvent(fn(string $eventName) => "Employee has been {$eventName}")
            ->useLogName('Employee')
            ->logOnlyDirty();
    }

    public function officeOpcr()
    {
        return $this->belongsTo(Opcr::class);
    }

    public function signatories()
    {
        return $this->hasOne(DocumentSignatory::class, 'control_no', 'ControlNo');

    }
}
