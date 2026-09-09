<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetPeriodDetailsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
         'target_period_id' => $this->id,
         'name'   => $this->whenLoaded('employee', fn () => $this->employee?->name),
         'office' => $this->whenLoaded('employee', fn () => $this->employee?->office),
         'week_status' => $this->week_status ?? 'No Rating',

        // 'rating_weeks' => $this->whenLoaded('ratingWeeks', function () {
        //     return $this->ratingWeeks->map(function ($week) {
        //         return [
        //             'week' => $week->week,
        //             'status' => $week->status,
        //         ];
        //     });
        // }, []),

            'performance_standards' => $this->whenLoaded('performanceStandards', function () {
                return $this->performanceStandards->map(function ($standard) {
                    return [
                        'id' => $standard->id,
                        'target_period_id' => $standard->target_period_id,
                        'category' => $standard->category,
                        'mfo' => $standard->mfo,
                        'output' => $standard->output,
                        'output_name' => $standard->output_name,
                        'performance_indicator' => $standard->performance_indicator,
                        'success_indicator' => $standard->success_indicator,
                        'required_output' => $standard->required_output,

                        'standard_outcomes' => $standard->relationLoaded('standardOutcomes')
                            ? $standard->standardOutcomes->map(function ($outcome) {
                                return [
                                    'id' => $outcome->id,
                                    'performance_standard_id' => $outcome->performance_standard_id,
                                    'rating' => $outcome->rating,
                                    'quantity' => $outcome->quantity,
                                    'effectiveness' => $outcome->effectiveness,
                                    'timeliness' => $outcome->timeliness,
                                ];
                            })
                            : [],

                        'performance_rating' => $standard->relationLoaded('performanceRating')
                            ? $standard->performanceRating->map(function ($rating) {
                                return [
                                    'performance_rating_id' => $rating->id,
                                    'performance_standard_id' => $rating->performance_standard_id,
                                    'control_no' => $rating->control_no,
                                    'date' => $rating->date,
                                    'quantity_actual' => $rating->quantity_actual,
                                    'effectiveness_actual' => $rating->effectiveness_actual,
                                    'timeliness_actual' => $rating->timeliness_actual,
                                    'status' => $rating->status,

                                    'dropdown_rating' => $rating->relationLoaded('dropdownRating')
                                        ? $rating->dropdownRating->map(function ($dropdown) {
                                            return [
                                                'id' => $dropdown->id,
                                                'performance_rating_id' => $dropdown->performance_rating_id,
                                                'quantity' => $dropdown->quantity,
                                                'effectiveness' => $dropdown->effectiveness,
                                                'timeliness' => $dropdown->timeliness,
                                                'created_at' => $dropdown->created_at,
                                                'updated_at' => $dropdown->updated_at,
                                            ];
                                        })
                                        : [],
                                ];
                            })
                            : [],

                        'configurations' => $standard->relationLoaded('configurations')
                            ? $standard->configurations->map(function ($config) {
                                return [
                                    'id' => $config->id,
                                    'performance_standard_id' => $config->performance_standard_id,
                                    'targetOutput' => $config->targetOutput,
                                    'quantityIndicator' => $config->quantityIndicator,
                                    'timelinessIndicator' => $config->timelinessIndicator,
                                    'range' => $config->range,
                                    'date' => $config->date,
                                    'description' => $config->description,
                                ];
                            })
                            : [],
                    ];
                });
            }, []),
        ];
        
    }
    
}