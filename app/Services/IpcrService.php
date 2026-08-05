<?php

namespace App\Services;

use App\Http\Requests\AttendanceRequest;
use App\Models\DocumentSignatory;
use App\Models\Employee;
use App\Models\Month;
use App\Models\OfficeOpcr;
use App\Models\PerformanceRating;
use App\Models\PerformanceStandard;
use App\Models\TargetPeriod;
use App\Models\TargetPeriodRecord;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IpcrService
{
    /**
     * Create a new class instance.
     */


    // public function __construct()
    // {
    //     //
    // }

    use ApiResponseTrait;
    public function opcrOfficeHead(string $controlNo, string $semester, int $year)
    {
        $officeHeadOpcr = Employee::select('id', 'ControlNo', 'name', 'office_id', 'office','position')
            ->where('ControlNo', $controlNo)
            ->whereHas('targetPeriods', function ($q) use ($year, $semester) {
                $q->where('year', $year)->where('semester', $semester);
            })
            ->with([
                'targetPeriods' => function ($queryTargetPeriod) use ($year, $semester) {
                    $queryTargetPeriod
                        ->select('id', 'control_no', 'semester', 'year')
                        ->where('year', $year)
                        ->where('semester', $semester)
                        ->with([
                            'performanceStandards' => function ($queryPerformanceStandard) {
                                $queryPerformanceStandard->select(
                                    'id',
                                    'target_period_id',
                                    'category',
                                    'mfo',
                                    'output',
                                    'success_indicator',
                                    'core',
                                    'technical',
                                    'leadership',
                                )->with([
                                    'opcr' => function ($queryopcr) {
                                        $queryopcr->select(
                                            'id',
                                            'performance_standard_id',
                                            'competency',
                                            'budget',
                                            'accountable',
                                            'profiency',
                                            'remarks',
                                        );
                                    }
                                ]);
                            }
                        ]);
                }
            ])
            ->first();
        // ✅ Guard clause — return null so the controller can handle the 404

        if (!$officeHeadOpcr) {
            return null;
        }


        $opcr_status = OfficeOpcr::with(['officeOpcrRecordLastestRecord' => function ($query) {
            $query->select(
                'office_opcrs_records.id',
                'office_opcrs_records.office_opcr_id',
                'office_opcrs_records.date',
                'office_opcrs_records.status',
                'office_opcrs_records.remarks',
                'office_opcrs_records.processed_by',
            );
        }])
            ->select('id', 'office_id', 'office_name', 'semester', 'year')
            ->where('office_id', $officeHeadOpcr->office_id)
            ->where('semester', $semester)
            ->where('year', $year)
            ->first();


        $successIndicatorsByMfo = collect();
        $officeHeadOpcr->targetPeriods->each(function ($period) use (&$successIndicatorsByMfo) {
            $period->performanceStandards->each(function ($standard) use (&$successIndicatorsByMfo) {
                $successIndicatorsByMfo->put($standard->mfo, $standard->success_indicator);
            });
        });

        // Get aggregated IPCR keyed by MFO name for easy lookup
        $ipcrByMfo = $this->aggregateIpcrByMfoForOpcr(
            $officeHeadOpcr->office_id,
            $year,
            $semester,
            $officeHeadOpcr->ControlNo,  // <-- exclude the Department Head himself
            $successIndicatorsByMfo   // ✅ pass it here
        );
        // ✅ Extract both parts from the returned array
        $opcr_accomplishment = $ipcrByMfo['opcr_accomplishment'];
        $averageRating       = $ipcrByMfo['average_rating'];
        // Key the result by MFO name for easy lookup
        // $ipcrByMfoKeyed = collect($ipcrByMfo)->keyBy('mfo');
        $ipcrByMfoKeyed = collect($opcr_accomplishment)->keyBy('mfo');

        // Inject ipcr accomplishment into each of the Department Head's performance standards
        $officeHeadOpcr->targetPeriods->each(function ($period) use ($ipcrByMfoKeyed) {
            $period->performanceStandards->each(function ($standard) use ($ipcrByMfoKeyed) {
                $mfo = $standard->mfo;

                if ($ipcrByMfoKeyed->has($mfo)) {
                    $ipcr = $ipcrByMfoKeyed->get($mfo);

                    // Inject the aggregated accomplishment into the opcr
                    $standard->ipcr_accomplishment = [
                        'quantity_total'   => $ipcr['quantity_total'],
                        'accomplishment'  => $ipcr['accomplishment'],
                        'ratings'          => $ipcr['ratings'],
                        'employee_count'   => $ipcr['employee_count'],

                    ];
                } else {
                    // No employee data found for this MFO yet
                    $standard->ipcr_accomplishment = null;
                }
            });
        });

        return [
            'employee'    => $officeHeadOpcr,
            'opcr_status'    => $opcr_status,
            'average_rating' => $averageRating,   // add this
        ];
    }

    /**
     * Aggregate IPCR by MFO — exclude the Department Head's own control number
     */
    public function aggregateIpcrByMfoForOpcr(int $officeId, int $year, string $semester, $excludeControlNo = null,  $successIndicatorsByMfo = null)
    {
        $query = Employee::where('office_id', $officeId);

        // Exclude the Department Head himself from the aggregation
        if ($excludeControlNo) {
            $query->where('ControlNo', '!=', $excludeControlNo);
        }

        $employees = $query->with([
            'targetPeriods' => function ($q) use ($year, $semester) {
                $q->where('year', $year)
                    ->where('semester', $semester)
                    ->with([
                        // 'performanceStandards.performanceRating:id,performance_standard_id,date,quantity_actual as quantity,effectiveness_actual as effectiveness,timeliness_actual as timeliness',
                        'performanceStandards.performanceRating' => function ($query) {
                            $query->select(
                                'id',
                                'performance_standard_id',
                                'date',
                                'status',
                                'quantity_actual as quantity',
                                'effectiveness_actual as effectiveness',
                                'timeliness_actual as timeliness'
                            );
                            // ->where('status', 'Approved');
                        },
                        'performanceStandards.standardOutcomes' => function ($query) {
                            $query->select(
                                'performance_standard_id',
                                'rating',
                                'quantity_target as quantity',
                                'effectiveness_criteria as effectiveness',
                                'timeliness_range as timeliness'
                            );
                        },
                        // 'performanceStandards.standardOutcomes:performance_standard_id,rating,quantity_target as quantity,effectiveness_criteria as effectiveness,timeliness_range as timeliness',
                    ]);
            }
        ])->get();

        // Compute IPCR for each employee
        $employees->each(function ($employee) {
            $employee->targetPeriods->each(function ($period) {
                $period->performanceStandards->each(function ($standard) {

                    // Skip if no ratings data (e.g. draft/no submissions)
                    if ($standard->performanceRating->isEmpty()) {
                        $standard->ratings       = null;
                        $standard->accomplishment = null;
                        return;
                    }

                    $monthly = $this->groupRatingsByMonthlySummary($standard->performanceRating);
                    $summary = $this->getComputationTotalAndRating($monthly['monthly'], $standard->standardOutcomes);
                    $average = $this->getAverageRating($summary['ratings']);

                    $standard->monthly_ratings = $monthly;
                    $standard->totals          = $summary['totals'];
                    $standard->ratings         = array_merge(
                        $summary['ratings'],
                        ['average_rating' => $average]
                    );

                    $accomplishment           = $this->accomplishment($standard, $summary);
                    $standard->accomplishment = array_merge(
                        $accomplishment,
                        [
                            'effectiveness_rating' => $summary['ratings']['effectiveness_rating'],
                            'timeliness_rating'    => $summary['ratings']['timeliness_rating'],
                        ]
                    );

                    $standard->makeHidden('performanceRating');
                });
            });
        });

        // Group by MFO and aggregate
        $mfoGroups = [];

        foreach ($employees as $employee) {
            foreach ($employee->targetPeriods as $period) {
                foreach ($period->performanceStandards as $standard) {

                    // Skip standards with no computed ratings
                    if (is_null($standard->ratings) || is_null($standard->accomplishment)) {
                        continue;
                    }

                    $mfo = $standard->mfo;

                    if (!isset($mfoGroups[$mfo])) {
                        $mfoGroups[$mfo] = [
                            'mfo'                      => $mfo,
                            'category'                 => $standard->category,
                            'quantity_total'            => 0,
                            'quantity_rating_sum'       => 0,
                            'effectiveness_rating_sum'  => 0,
                            'timeliness_rating_sum'     => 0,
                            'employee_count'            => 0,
                        ];
                    }

                    $mfoGroups[$mfo]['quantity_total']            += $standard->accomplishment['quantityTotal']          ?? 0;
                    $mfoGroups[$mfo]['quantity_rating_sum']       += $standard->ratings['quantity_rating']               ?? 0;
                    $mfoGroups[$mfo]['effectiveness_rating_sum']  += $standard->ratings['effectiveness_rating']          ?? 0;
                    $mfoGroups[$mfo]['timeliness_rating_sum']     += $standard->ratings['timeliness_rating']             ?? 0;
                    $mfoGroups[$mfo]['employee_count']++;
                }
            }
        }

        // Compute averages
        $result = [];

        $categoryRatings = [
            'strategic' => ['sum' => 0, 'count' => 0],
            'core'      => ['sum' => 0, 'count' => 0],
            'support'   => ['sum' => 0, 'count' => 0],
        ];
        foreach ($mfoGroups as $mfo => $data) {
            $count = $data['employee_count'];

            $avgQ = $count > 0 ? round($data['quantity_rating_sum']      / $count, 2) : 0;
            $avgE = $count > 0 ? round($data['effectiveness_rating_sum'] / $count, 2) : 0;
            $avgT = $count > 0 ? round($data['timeliness_rating_sum']    / $count, 2) : 0;
            $avgA = round(($avgQ + $avgE + $avgT) / 3, 2);


            $rawIndicator = $successIndicatorsByMfo?->get($mfo) ?? '';
            // Remove leading number (e.g. "70 ")
            $cleanIndicator = preg_replace('/^\d+\s*/', '', $rawIndicator);

            // ✅ Step 2: Get the category for this MFO and accumulate
            $category = strtoupper($data['category'] ?? '');

            if (str_contains($category, 'STRATEGIC')) {
                $categoryRatings['strategic']['sum']   += $avgA;
                $categoryRatings['strategic']['count'] += 1;
            } elseif (str_contains($category, 'CORE')) {
                $categoryRatings['core']['sum']   += $avgA;
                $categoryRatings['core']['count'] += 1;
            } elseif (str_contains($category, 'SUPPORT')) {
                $categoryRatings['support']['sum']   += $avgA;
                $categoryRatings['support']['count'] += 1;
            }


            $result[] = [
                'mfo'            => $mfo,
                'quantity_total' => $data['quantity_total'],
                'accomplishment' => $data['quantity_total'] . ' ' . $cleanIndicator,

                'ratings'        => [
                    'quantity_rating'      => $avgQ,
                    'effectiveness_rating' => $avgE,
                    'timeliness_rating'    => $avgT,
                    'average_rating'       => $avgA,
                ],


                'employee_count' => $count,
            ];
        }
        // ✅ Step 3: Compute weighted category averages
        $strategicAvg = $categoryRatings['strategic']['count'] > 0
            ? round($categoryRatings['strategic']['sum'] / $categoryRatings['strategic']['count'], 2)
            : 0;

        $coreAvg = $categoryRatings['core']['count'] > 0
            ? round($categoryRatings['core']['sum'] / $categoryRatings['core']['count'], 2)
            : 0;

        $supportAvg = $categoryRatings['support']['count'] > 0
            ? round($categoryRatings['support']['sum'] / $categoryRatings['support']['count'], 2)
            : 0;


        $hasStrategic = $categoryRatings['strategic']['count'] > 0;
        $hasSupport   = $categoryRatings['support']['count'] > 0;

        if ($hasStrategic && $hasSupport) {
            // All 3 categories: Strategic 20% + Core 60% + Support 20%
            $strategicWeighted = round($strategicAvg * 0.2, 2);
            $coreWeighted      = round($coreAvg      * 0.6, 2);
            $supportWeighted   = round($supportAvg   * 0.2, 2);
        } elseif ($hasStrategic && !$hasSupport) {
            // No Support: Strategic 20% + Core 80% + Support 0%
            $strategicWeighted = round($strategicAvg * 0.2, 2);
            $coreWeighted      = round($coreAvg      * 0.8, 2);
            $supportWeighted   = 0;
        } elseif (!$hasStrategic && $hasSupport) {
            // No Strategic: Strategic 0% + Core 80% + Support 20%
            $strategicWeighted = 0;
            $coreWeighted      = round($coreAvg   * 0.8, 2);
            $supportWeighted   = round($supportAvg * 0.2, 2);
        } else {
            // Core only: Strategic 0% + Core 100% + Support 0%
            $strategicWeighted = 0;
            $coreWeighted      = round($coreAvg * 1.0, 2);
            $supportWeighted   = 0;
        }

        $finalRating = round($strategicWeighted + $coreWeighted + $supportWeighted, 2);

        // Adjectival rating based on final rating
        $adjectivalRating = match (true) {
            $finalRating >= 5 => 'Outstanding',
            $finalRating >= 4 => 'Very Satisfactory',
            $finalRating >= 3 => 'Satisfactory',
            $finalRating >= 2 => 'Unsatisfactory',
            $finalRating >= 1         => 'Poor',
            default => 'Pending rating',
        };

        $averageRating = [
            'strategic_functions' => $strategicWeighted,
            'core_functions'      => $coreWeighted,
            'support_functions'   => $supportWeighted,
            'final_rating'        => $finalRating,
            'adjectival_rating'   => $adjectivalRating,  // ← add this
        ];


        return [
            'opcr_accomplishment'    => $result,
            'average_rating' => $averageRating,
        ];
    }
    //---------------------------------------------------------------------------- Ipcr Data-----------------------------------------------------------------------------//

    //Ipcr Data of Employee
    public function getIpcrData(string $controlNo, int $year, string $semester)
    {
        $employee = Employee::where('ControlNo', $controlNo)
            ->with([
                'targetPeriods' => function ($q) use ($year, $semester) {
                    $q->select('id', 'year', 'semester', 'control_no')->where('year', $year)
                        ->where('semester', $semester)
                        ->with([
                            'ipcrLastestRecord' => function ($query) {  // get the lastest record of the ipcr
                                $query->select(
                                    'targetperiod_records.id',
                                    'targetperiod_records.target_period_id',
                                    'targetperiod_records.status',
                                    'targetperiod_records.remarks'
                                );
                            },
                            'ipcrRecord' => function ($query) {  // ipcr record find the person and date who received the ipcr
                                $query->select(
                                    'targetperiod_records.id',
                                    'targetperiod_records.target_period_id',
                                    'targetperiod_records.status',
                                    'targetperiod_records.remarks',
                                    'targetperiod_records.processed_by',
                                    'targetperiod_records.date',
                                    'targetperiod_records.created_at',
                                );
                                // ->where('status', 'Received')->with(['processedBy' => function ($q) {
                                //     $q->select('id', 'name', 'prefix', 'suffix', 'designation');  // only what you need
                                // }]);;
                            },
                            'performanceStandards.performanceRating' => function ($query) {
                                $query->select(
                                    'id',
                                    'performance_standard_id',
                                    'date',
                                    'status',
                                    'quantity_actual as quantity',
                                    'effectiveness_actual as effectiveness',
                                    'timeliness_actual as timeliness'
                                );
                                // ->where('status', 'Approved');

                            },
                            'performanceStandards.standardOutcomes' => function ($query) {
                                $query->select(
                                    'performance_standard_id',
                                    'rating',
                                    'quantity_target as quantity',
                                    'effectiveness_criteria as effectiveness',
                                    'timeliness_range as timeliness'
                                );
                            },

                            // 'performanceStandards.standardOutcomes:performance_standard_id,rating,quantity_target as quantity,effectiveness_criteria as effectiveness,timeliness_range as timeliness',

                        ]); //

                },
                'signatories' => function ($query) use ($controlNo) {  // get the lastest record of the ipcr
                    $query->select(
                        'id',
                        'control_no',
                        'performance_standard_discussed_by_control_no',
                        'performance_standard_approved_by_control_no',
                        //ipcr
                        'ipcr_reviewed_by_control_no',
                        'ipcr_approved_by_control_no',
                        'ipcr_assessed_by_control_no',
                        'ipcr_final_rating_by_control_no',
                        //por
                        'por_confirmed_control_no',
                        'por_approved_final_rating_control_no'
                    )->where('control_no', $controlNo)->first();
                },
            ])
            ->first();

        if (! $employee) {
            return null;
        }

        // ── Resolve signatory control_nos into employee objects (ONCE, not per period) ──
        $signatoryFields = [
            'performance_standard_discussed_by_control_no',
            'performance_standard_approved_by_control_no',
            'ipcr_reviewed_by_control_no',
            'ipcr_approved_by_control_no',
            'ipcr_assessed_by_control_no',
            'ipcr_final_rating_by_control_no',
            'por_confirmed_control_no',
            'por_approved_final_rating_control_no',
        ];

        if ($employee->signatories) {
            $controlNos = collect($signatoryFields)
                ->map(fn($field) => $employee->signatories->{$field})
                ->filter()
                ->unique()
                ->values();

            $employeesByControlNo = Employee::select('ControlNo', 'name', 'rank', 'job_title', 'suffix', 'prefix')
                ->whereIn('ControlNo', $controlNos)
                ->get()
                ->keyBy('ControlNo');

            $resolved = [
                'id' => $employee->signatories->id,
                'control_no' => $employee->signatories->control_no,
            ];

            foreach ($signatoryFields as $field) {
                $cn = $employee->signatories->{$field};
                $resolved[$field] = $cn ? ($employeesByControlNo->get($cn) ?? null) : null;
            }

            $employee->setAttribute('signatories_resolved', $resolved);
        }

        $employee->targetPeriods->each(function ($period) {
            $period->performanceStandards->each(function ($standard) {


                $monthly = $this->groupRatingsByMonthlySummary(
                    $standard->performanceRating
                );

                $summary = $this->getComputationTotalAndRating(
                    $monthly['monthly'],
                    $standard->standardOutcomes
                );

                $average = $this->getAverageRating($summary['ratings']);



                // attach computed values (important)
                $standard->monthly_ratings = $monthly;
                $standard->totals = $summary['totals'];
                // $standard->ratings = $summary['ratings'];

                // merge average rating into ratings array
                $standard->ratings = array_merge(
                    $summary['ratings'],
                    ['average_rating' => $average]
                );

                //accomplishment
                $accomplishment = $this->accomplishment($standard, $summary);
                $standard->accomplishment = $accomplishment;



                // merge get the effectiveness and timeliness rating into accomplishment
                $standard->accomplishment = array_merge(
                    $accomplishment,
                    [
                        'effectiveness_rating' => $summary['ratings']['effectiveness_rating'],
                        'timeliness_rating' => $summary['ratings']['timeliness_rating'],
                    ]
                );

                $standard->makeHidden('performanceRating');
            });
        });
        // ─── Add final rating computation ───────────────────────────────────────
        $categoryRatings = [
            'core'    => ['sum' => 0, 'count' => 0],
            'support' => ['sum' => 0, 'count' => 0],
        ];

        foreach ($employee->targetPeriods as $period) {
            foreach ($period->performanceStandards as $standard) {
                if (is_null($standard->ratings)) continue;

                $category = strtoupper($standard->category ?? '');
                $avgRating = $standard->ratings['average_rating'] ?? 0;

                if (str_contains($category, 'CORE')) {
                    $categoryRatings['core']['sum']   += $avgRating;
                    $categoryRatings['core']['count'] += 1;
                } elseif (str_contains($category, 'SUPPORT')) {
                    $categoryRatings['support']['sum']   += $avgRating;
                    $categoryRatings['support']['count'] += 1;
                }
            }
        }

        $coreAvg = $categoryRatings['core']['count'] > 0
            ? round($categoryRatings['core']['sum'] / $categoryRatings['core']['count'], 2)
            : 0;

        $supportAvg = $categoryRatings['support']['count'] > 0
            ? round($categoryRatings['support']['sum'] / $categoryRatings['support']['count'], 2)
            : 0;

        $coreWeighted    = $categoryRatings['core']['count'] > 0;
        $supportWeighted = $categoryRatings['support']['count'] > 0;


        $coreWeighted    = round($coreAvg    * 0.8, 2);
        $supportWeighted = round($supportAvg * 0.2, 2);


        $finalRating = round($coreWeighted + $supportWeighted, 2);

        $adjectivalRating = match (true) {
            $finalRating >= 4.5 => 'Outstanding',
            $finalRating >= 3.5 => 'Very Satisfactory',
            $finalRating >= 2.5 => 'Satisfactory',
            $finalRating >= 1.5 => 'Unsatisfactory',
            default             => 'Poor',
        };

        $employee->final_rating = [
            'core_functions'    => $coreWeighted,
            'support_functions' => $supportWeighted,
            'final_rating'      => $finalRating,
            'adjectival_rating' => $adjectivalRating,
        ];
        // ────────────────────────────────────────────────────────────────────────

        return $employee;
    }

    //---------------------------------------------------------------------------- monthly-rate-----------------------------------------------------------------------------//


    public function getMonthly(int $targetPeriodId)
    {
        $standards = PerformanceStandard::select([
            'id',
            'target_period_id',
            'category',
            'mfo',
            'output'
        ])
            ->where('target_period_id', $targetPeriodId)
            ->with([
                'performanceRating' => function ($query) {
                    $query->select([
                        'id',
                        'performance_standard_id', // ✅ Include foreign key
                        'date',
                        'quantity_actual as quantity',
                        'effectiveness_actual as effectiveness',
                        'timeliness_actual as timeliness'
                    ]);
                    // ->where('status','Approved');
                }
            ])
            ->get();

        $standards->transform(function ($standard) {
            $grouped = $this->groupRatingsByMonthly($standard->performanceRating);

            // Store in a new property to preserve original relation
            $standard->monthly_ratings = $grouped;
            $standard->makeHidden('performanceRating');

            return $standard;
        });

        // 🔹 FETCH ATTENDANCE SEPARATELY
        $attendance = Month::with([
            'absents:month_id,week1,week2,week3,week4,week5,total_absent',
            'lates:month_id,week1,week2,week3,week4,week5,total_late'
        ])
            ->where('target_period_id', $targetPeriodId)
            ->get();


        // 🔹 RETURN BOTH AS SEPARATE DATA
        return [

            'standards' => $standards,
            'attendance' => $attendance ?? null
        ];
    }


    private function groupRatingsByMonthly($ratings)
    {
        $grouped = [];


        foreach ($ratings as $rating) {
            try {
                $date = Carbon::createFromFormat('m/d/Y', $rating->date);
            } catch (\Exception $e) {
                Log::warning("Invalid date format: {$rating->date}");
                continue;
            }

            $monthKey = $date->format('Y-m');
            $weekKey  = 'week' . $this->getWeekOfMonth($date);

            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month' => $date->format('F'),

                    // WEEKLY TOTALS
                    'quantity' => $this->initializeWeeks(),
                    'effectiveness' => $this->initializeWeeks(),
                    'timeliness' => $this->initializeWeeks(),
                ];
            }

            // 🔹 ADD TOTALS (NO AVERAGE)
            $grouped[$monthKey]['quantity'][$weekKey] += (int) $rating->quantity;
            $grouped[$monthKey]['effectiveness'][$weekKey] += (int) $rating->effectiveness;
            $grouped[$monthKey]['timeliness'][$weekKey] += (int) $rating->timeliness;
        }

        // 🔹 OPTIONAL: Monthly TOTAL (sum of weeks)
        foreach ($grouped as &$month) {
            foreach (['quantity', 'effectiveness', 'timeliness'] as $type) {
                // $month[$type]['week_total'] = array_sum($month[$type]);
                $weeks = $month[$type];
                unset($weeks['week_total']);
                $month[$type]['week_total'] = array_sum($weeks);
            }
        }

        // 🔹 OVERALL TOTALS (NO AVERAGE)


        return [
            'monthly' => array_values($grouped),

        ];
    }



    /**
     * Initialize weeks structure with zeros
     */
    private function initializeWeeks()
    {
        return [
            'week1' => 0,
            'week2' => 0,
            'week3' => 0,
            'week4' => 0,
            'week5' => 0, // For months with 29-31 days
            // 'total'=> 0,
        ];
    }

    /**
     * Get week number of the month (1-5)
     */
    private function getWeekOfMonth(Carbon $date)
    {
        // More accurate week calculation
        return (int) ceil($date->day / 7);

        // Alternative: Use Carbon's weekOfMonth if you want ISO week standards
        // return $date->weekOfMonth;
    }

    //---------------------------------------------------------------------------- summary-monthly-rate-----------------------------------------------------------------------------//


    // get the summary-monthly-rate
    public function getSummaryMonthly($targetPeriodId)
    {
        $standards = PerformanceStandard::select([
            'id',
            'target_period_id',
            'category',
            'mfo',
            'output'
        ])
            ->where('target_period_id', $targetPeriodId)
            ->with([
                // 'standardOutcomes:performance_standard_id,rating,quantity_target as quantity',
                'standardOutcomes' => function ($query) {
                    $query->select('performance_standard_id', 'rating', 'quantity_target as quantity');
                },
                'performanceRating' => function ($query) {
                    $query->select(
                        'id',
                        'performance_standard_id',
                        'date',
                        'quantity_actual as quantity',
                        'effectiveness_actual as effectiveness',
                        'timeliness_actual as timeliness'
                    );
                    //  ->where('status','Approved');
                },
                // 'performanceRating:id,performance_standard_id,date,quantity_actual as quantity,effectiveness_actual as effectiveness,timeliness_actual as timeliness'
            ])
            ->get();

        foreach ($standards as $standard) {

            $monthly = $this->groupRatingsByMonthlySummary(
                $standard->performanceRating
            );

            $summary = $this->getComputationTotalAndRating(
                $monthly['monthly'],
                $standard->standardOutcomes
            );

            // attach computed values (important)
            $standard->monthly_ratings = $monthly;
            $standard->totals = $summary['totals'];
            $standard->ratings = $summary['ratings'];
        }

        // 🔹 FETCH ATTENDANCE SEPARATELY
        $attendance = Month::with([
            'absents:month_id,week1,week2,week3,week4,week5,total_absent',
            'lates:month_id,week1,week2,week3,week4,week5,total_late'
        ])
            ->where('target_period_id', $targetPeriodId)
            ->get();

        // 🔹 RETURN BOTH AS SEPARATE DATA
        return [

            'standards' => $standards,
            'attendance' => $attendance ?? null
        ];
    }


    // get the summary-monthly-rate



    private function groupRatingsByMonthlySummary($ratings)
    {
        $grouped = [];

        foreach ($ratings as $rating) {
            try {
                $date = Carbon::createFromFormat('m/d/Y', $rating->date);
            } catch (\Exception $e) {
                Log::warning("Invalid date format: {$rating->date}");
                continue;
            }

            $monthKey = $date->format('Y-m');
            $weekKey  = 'week' . $this->getWeekOfMonth($date);

            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month' => $date->format('F'), // Y for Year

                    // WEEKLY TOTALS
                    'quantity' => $this->initializeWeeks(),
                    'effectiveness' => $this->initializeWeeks(),
                    'timeliness' => $this->initializeWeeks(),
                ];
            }

            // 🔹 ADD TOTALS (NO AVERAGE)
            $grouped[$monthKey]['quantity'][$weekKey] += (int) $rating->quantity;
            $grouped[$monthKey]['effectiveness'][$weekKey] += (int) $rating->effectiveness;
            $grouped[$monthKey]['timeliness'][$weekKey] += (int) $rating->timeliness;
        }

        // 🔹 OPTIONAL: Monthly TOTAL (sum of weeks)
        foreach ($grouped as &$month) {
            foreach (['quantity', 'effectiveness', 'timeliness'] as $type) {
                $month[$type]['month_total'] = array_sum($month[$type]);

                // REMOVE week1–week5 from response
                unset(
                    $month[$type]['week1'],
                    $month[$type]['week2'],
                    $month[$type]['week3'],
                    $month[$type]['week4'],
                    $month[$type]['week5']
                );
            }
        }

        //


        return [
            'monthly' => array_values($grouped),

        ];
    }

    // get the monthly rate of employee Timeliness and Effectiveness
    private function getComputationTotalAndRating(array $monthlyRatings, $standardOutcomes)
    {
        $totals = [
            'quantity_total' => 0,
            'effectiveness_total' => 0,
            'timeliness_total' => 0,
        ];

        // get the total of quantity, effectiveness, and timeliness across all months
        foreach ($monthlyRatings as $month) {
            $totals['quantity_total'] += data_get($month, 'quantity.month_total', 0);
            $totals['effectiveness_total'] += data_get($month, 'effectiveness.month_total', 0);
            $totals['timeliness_total'] += data_get($month, 'timeliness.month_total', 0);
        }



        // Calculate quantity_rating based on standard_outcomes
        $quantityRating = $this->getQuantityRating($totals['quantity_total'], $standardOutcomes);

        $quantityTotal = $totals['quantity_total'];

        $effectivenessRating = $quantityTotal > 0
            ? round($totals['effectiveness_total'] / $quantityTotal)
            : 0;

        $timelinessRating = $quantityTotal > 0
            ? round($totals['timeliness_total'] / $quantityTotal)
            : 0;



        return [
            'totals' => $totals,
            'ratings' => [
                'quantity_rating'      => (int) $quantityRating,
                'effectiveness_rating' => (int) $effectivenessRating,
                'timeliness_rating'    => (int) $timelinessRating,
            ],

        ];
    }

    //getting the quantity rating based on the standard outcomes
    private function getQuantityRating($quantityTotal, $standardOutcomes)
    {
        if ($quantityTotal <= 0) {
            return 0;
        }

        $outcomes = collect($standardOutcomes)->sortByDesc('rating');

        foreach ($outcomes as $outcome) {
            $quantity = data_get($outcome, 'quantity');

            if (is_null($quantity)) {
                continue;
            }

            if (preg_match('/^(\d+)\s+and\s+above$/i', $quantity, $matches)) {
                $threshold = (int) $matches[1];
                if ($quantityTotal >= $threshold) {
                    return (int) data_get($outcome, 'rating');
                }
            } elseif (preg_match('/^(\d+)\s+and\s+below$/i', $quantity, $matches)) {
                $threshold = (int) $matches[1];
                if ($quantityTotal <= $threshold) {
                    return (int) data_get($outcome, 'rating');
                }
            } elseif (preg_match('/^(\d+)-(\d+)$/', $quantity, $matches)) {
                $min = (int) $matches[1];
                $max = (int) $matches[2];
                if ($quantityTotal >= $min && $quantityTotal <= $max) {
                    return (int) data_get($outcome, 'rating');
                }
            } elseif (is_numeric($quantity)) {
                if ($quantityTotal >= (int) $quantity) {
                    return (int) data_get($outcome, 'rating');
                }
            }
        }

        return 0;
    }



    // get the average  of employee base on the ratings
    // quantity rating + effectiveness rating + timeliness rating / 3
    private function getAverageRating($ratings)
    {

        $quantityRating = data_get($ratings, 'quantity_rating', 0);
        $effectivenessRating = data_get($ratings, 'effectiveness_rating', 0);
        $timelinessRating = data_get($ratings, 'timeliness_rating', 0);

        // Calculate average
        $average = ($quantityRating + $effectivenessRating + $timelinessRating) / 3;

        return round($average, 2); // Round to 2 decimal places


    }

    // getting the value of standard outcomes based on the rating
    private function getStandardOutcomeText($standardOutcomes, $rating)
    {
        $outcome = $standardOutcomes->firstWhere('rating', (string) $rating);

        return [
            'effectiveness' => $outcome->effectiveness ?? '',
            'timeliness'    => $outcome->timeliness ?? '',
        ];
    }

    private function accomplishment($standard, $summary)
    {
        $ratings = PerformanceRating::where('performance_standard_id', $standard->id)->get();

        $quantityTotal = $ratings->sum(function ($rating) {
            return is_numeric($rating->quantity_actual) ? $rating->quantity_actual : 0;
        });

        // ratings
        $effectivenessRating = $summary['ratings']['effectiveness_rating'];
        $timelinessRating    = $summary['ratings']['timeliness_rating'];

        // get text from standard outcomes
        $outcomeText = $this->getStandardOutcomeText(
            $standard->standardOutcomes,
            $effectivenessRating
        );

        // performance indicators → text
        $performanceIndicators = collect($standard->performance_indicator ?? [])
            ->implode(' and ');

        // build sentence
        $actualAccomplishment = trim(sprintf(
            '%d %s %s %s %s',
            $quantityTotal, // quantity total
            $standard->output_name, // outpput_name
            $performanceIndicators, // performance indicators
            $outcomeText['effectiveness'], // standard outcome for effectiveness
            $outcomeText['timeliness'] // standard outcome for timeliness
        ));

        return [
            'quantityTotal'         => $quantityTotal,
            'effectiveness_rating'  => $effectivenessRating,
            'timeliness_rating'     => $timelinessRating,
            'actual_accomplishment' => $actualAccomplishment,
        ];
    }

    //-----------------------approve the ipcr of the employee----------------------------------------------//

    // public function approveIpcr(string $controlNo, string $semester, int $year, Request $request)
    // {
    //     $validated = $request->validate([
    //         'status' => 'required|in:approve,reject,review,re-submit',
    //     ]);

    //     // Get employee with office restriction
    //     $employee = Employee::where('ControlNo', $controlNo)
    //         // ->where('office_id', $this->officeId)
    //         ->firstOrFail(); // Throws exception if not found

    //     // Get the target period
    //     $targetPeriod = $employee->targetPeriods()
    //         ->where('year', $year)
    //         ->where('semester', $semester)
    //         ->firstOrFail(); // Throws exception if not found

    //     // Update only the target period
    //     $targetPeriod->update([
    //         'status' => $validated['status'],
    //     ]);

    //     return $targetPeriod->fresh(); // Return updated model
    // }


    //---------------------------------------------------------------------------- late and absent -----------------------------------------------------------------------------//

    // storing attendance
    public function storeAttendance(AttendanceRequest $request)
    {
        $validated = $request->validated();
        $createdMonths = [];

        foreach ($validated['months'] as $monthData) {
            // Create the month record
            $month = Month::create([
                'target_period_id' => $validated['target_period_id'],
                'month' => $monthData['month'],
            ]);

            // Create absent record
            $month->absents()->create([
                'month_id' => $month->id,
                'week1' => $monthData['absent']['week1'] ?? 0,
                'week2' => $monthData['absent']['week2'] ?? 0,
                'week3' => $monthData['absent']['week3'] ?? 0,
                'week4' => $monthData['absent']['week4'] ?? 0,
                'week5' => $monthData['absent']['week5'] ?? 0,
                'total_absent' => $monthData['absent']['total_absent'] ?? 0,
            ]);

            // Create late record
            $month->lates()->create([
                'month_id' => $month->id,
                'week1' => $monthData['late']['week1'] ?? 0,
                'week2' => $monthData['late']['week2'] ?? 0,
                'week3' => $monthData['late']['week3'] ?? 0,
                'week4' => $monthData['late']['week4'] ?? 0,
                'week5' => $monthData['late']['week5'] ?? 0,
                'total_late' => $monthData['late']['total_late'] ?? 0,
            ]);

            // Load relationships
            $month->load(['absents', 'lates']);
            $createdMonths[] = $month;
        }

        return $createdMonths;
    }


    // --------------------------------------------------------Office admin------------------------------------------------------------ //

    // list of ipcr of employee need to approve by Department Head
    public function ipcr(?string $semester = null, ?int $year = null, Authenticatable $authUser)
    {
        $employee = Employee::select('id', 'ControlNo', 'name', 'office', 'job_title', 'office_id')
            ->where('office_id', $authUser->office_id)
            ->where('job_title', '!=', 'Department Head') // <-- exclude Department Head
            ->where(function ($query) use ($semester, $year) {
                // if job_title is NOT Employee, fetch only if ipcrLastestRecord status is 'Draft'
                $query->where(function ($query) use ($semester, $year) {
                    $query->where('job_title', '!=', 'Employee')
                        ->whereHas('targetPeriods', function ($query) use ($semester, $year) {
                            $query->where('semester', $semester)
                                ->where('year', $year)
                                ->whereHas('ipcrLastestRecord', function ($query) {
                                    $query->where('status', 'Draft');
                                });
                        });
                })
                    // if job_title IS Employee, only fetch if ipcrLastestRecord status is 'Discussed'
                    ->orWhere(function ($query) use ($semester, $year) {
                        $query->where('job_title', 'Employee')
                            ->whereHas('targetPeriods', function ($query) use ($semester, $year) {
                                $query->where('semester', $semester)
                                    ->where('year', $year)
                                    ->whereHas('ipcrLastestRecord', function ($query) {
                                        $query->where('status', 'Discussed'); // need to change  status
                                    });
                            });
                    });
            })
            ->with(['targetPeriods' => function ($query) use ($semester, $year) {
                $query->select('id', 'control_no', 'semester', 'year')
                    ->where('semester', $semester)
                    ->where('year', $year)
                    ->with(['ipcrLastestRecord' => function ($query) {
                        $query->select(
                            'targetperiod_records.id',
                            'targetperiod_records.target_period_id',
                            'targetperiod_records.status',
                            'targetperiod_records.remarks'
                        );
                    }]);
            }])
            ->get();

        return $employee;
    }

    // document signatories
    public function storeDocumentSignatories($validatedData)
    {

        $data = DocumentSignatory::updateOrCreate(
            ['control_no' => $validatedData['control_no']],
            $validatedData
        );

        return $data;
    }

    // signatories detail
    public function viewDocumentSignatories(string $controlNo)
    {
        $data = DocumentSignatory::where('control_no', $controlNo)->first();

        if (!$data) {
            return null; // or throw a NotFoundException if you want the controller to catch it
        }

        // fields that hold a control_no needing resolution to employee details
        $signatoryFields = [
            'performance_standard_discussed_by_control_no',
            'performance_standard_approved_by_control_no',
            'ipcr_reviewed_by_control_no',
            'ipcr_approved_by_control_no',
            'ipcr_assessed_by_control_no',
            'ipcr_final_rating_by_control_no',
            'por_confirmed_control_no',
            'por_approved_final_rating_control_no',
        ];

        // gather all control_nos we need to look up in one query (avoid N+1)
        $controlNos = collect($signatoryFields)
            ->map(fn($field) => $data->{$field})
            ->filter()
            ->unique()
            ->values();

        $employees = Employee::select('ControlNo', 'name', 'rank', 'job_title')
            ->whereIn('ControlNo', $controlNos)
            ->get()
            ->keyBy('ControlNo');

        // build the response, replacing each control_no with the employee object
        $result = [
            'id' => $data->id,
            'control_no' => $data->control_no,
        ];

        foreach ($signatoryFields as $field) {
            $cn = $data->{$field};
            $result[$field] = $cn ? ($employees->get($cn) ?? null) : null;
        }

        return $result;
    }
}
