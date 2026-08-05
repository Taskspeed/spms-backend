<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpcrResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $employee = $this->resource['employee'] ?? $this->resource;
        $opcr_status = $this->resource['opcr_status'] ?? null;
        $average_rating = $this->resource['average_rating'] ?? null;

        return [
            'id'         => $employee->id,
            'control_no' => $employee->ControlNo,
            'name'       => $employee->name,
            'office_id'  => $employee->office_id,
            'office'     => $employee->office,
            'position'     => $employee->position,
            'opcr_status'     => $opcr_status?->officeOpcrRecordLastestRecord->status,
            'office_opcr_id'     => $opcr_status?->id,

            'target_periods' => $employee->relationLoaded('targetPeriods')
                ? $employee->targetPeriods->map(function ($target) {
                    return [
                        'id'       => $target->id,
                        'semester' => $target->semester,
                        'year'     => $target->year,
                        'status'   => $target->status,

                        'performance_standards' => $target->performanceStandards
                            ? $target->performanceStandards->map(function ($ps) {
                                return [
                                    'id'                => $ps->id,
                                    'target_period_id'  => $ps->target_period_id,
                                    'category'          => $ps->category,
                                    'mfo'               => $ps->mfo,
                                    'output'            => $ps->output,
                                    'success_indicator' => $ps->success_indicator,
                                    'core'              => $ps->core,
                                    'technical'         => $ps->technical,
                                    'leadership'        => $ps->leadership,
                                    'opcr' => $ps->opcr ? [
                                        'id'            => $ps->opcr->id,
                                        'budget'        => $ps->opcr->budget,
                                        'accountable'   => $ps->opcr->accountable,
                                        // 'accomplishment' => $ps->opcr->accomplishment,
                                    ] : null,
                                      'opcr_accomplishment' => $ps->ipcr_accomplishment ?? null,

                                ];
                            })
                            : [],
                    ];
                })
                : [],

            'average_rating' => $average_rating,
        ];
    }
}
