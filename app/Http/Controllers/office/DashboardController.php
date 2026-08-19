<?php

namespace App\Http\Controllers\office;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Month;
use App\Models\OfficeOpcr;
use App\Models\PerformanceRating;
use App\Models\PerformanceStandard;
use App\Models\TargetPeriod;
use App\Models\TargetPeriodRecord;
use App\Models\UnitWorkPlan;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{

    use ApiResponseTrait;

    //getting the  total employee in the office
    public function dashboardStatus(Request $request)
    {
        $user     = Auth::user();
        $officeId = $user->office_id;

        $year     = $request->input('year');
        $semester = $request->input('semester');

        $totalEmployeesOnOffice = \App\Models\Employee::where('office_id', $officeId)->pluck('ControlNo');

        // get control_nos belonging to this office
        $employeeControlNos = \App\Models\Employee::where('office_id', $officeId)->whereNotIn('job_title',['Department Head'])->pluck('ControlNo');

        // total employee count in office
        $totalEmployees = $totalEmployeesOnOffice->count();

        // ipcr status counts — filtered by office employees only ✅
     // Get TargetPeriod IDs filtered by office employees, year, semester
        $targetPeriodIds = TargetPeriod::whereIn('control_no', $employeeControlNos)
            ->when($year,     fn($q) => $q->where('year', $year))
            ->when($semester, fn($q) => $q->where('semester', $semester))
            ->pluck('id');

        // Now get status counts from TargetPeriodRecord (latest per target_period_id)
        $ipcr_status = TargetPeriodRecord::whereIn('target_period_id', $targetPeriodIds)
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('targetperiod_records')
                    ->groupBy('target_period_id');
            })
            ->selectRaw('LOWER(status) as status, COUNT(*) as count')
            ->groupBy(DB::raw('LOWER(status)'))
            ->pluck('count', 'status');

        $ipcr_data = [
            'ipcr' => [
                // todo: dashboard
                // target
                'Validated_target'  => (int) $ipcr_status->get('Validated target', 0),
                'Calibrated_target'  => (int) $ipcr_status->get('Calibrated target', 0),
                'Approved Target' => (int) $ipcr_status->get('Approved Target', 0),
                'Reviewed Target' => (int) $ipcr_status->get('Reviewed Target', 0),
                'Received Target'  => (int) $ipcr_status->get('Received Target', 0),
                'Discussed Target'  => (int) $ipcr_status->get('Discussed Target', 0),
                'Draft'    => (int) $ipcr_status->get('Draft', 0),

                // accomplishment
                'Validated_accomplishment'  => (int) $ipcr_status->get('Validated accomplishment', 0),
                'Calibrated_accomplishment' => (int) $ipcr_status->get('Calibrated accomplishment', 0),
                'Pre_validation'    => (int) $ipcr_status->get('Pre validation', 0),
                'In_Progress' => (int) $ipcr_status->get('In Progress', 0),
                'total_ipcr' => (int) $ipcr_status->sum(),
            ]
        ];

        // opcr get the status of opcr
        $opcr = OfficeOpcr::select('id', 'office_name', 'semester', 'year', 'office_id')
            ->where('semester', $semester)
            ->where('year', $year)
            ->where('office_id', $officeId)
            ->with('officeOpcrRecordLastestRecord')
            ->get();

        // if ($opcr->isEmpty()) {
        //     return $this->errorMessage('There is no data available for OPCR.', 404);
        // }

     $opcr_data = $opcr->isNotEmpty()
        ? $opcr->map(fn($item) => [
            'id'          => $item->id,
            'office_name' => $item->office_name,
            'semester'    => $item->semester,
            'year'        => $item->year,
            'date'        => $item->officeOpcrRecordLastestRecord?->date,
            'status'      => $item->officeOpcrRecordLastestRecord?->status,
            // 'remarks'     => $item->officeOpcrRecordLastestRecord?->remarks,
        ]) : 'No record found'; // ← no error, just null // ←


        // unit work plan status 
        $unitworkplan = UnitWorkPlan::select('id', 'office_name', 'semester', 'year')
            ->where('semester', $semester)
            ->where('year', $year)
             ->where('office_id', $officeId)

            ->with('unitworkplanLastestRecord')
            ->get();

        // if ($unitworkplan->isEmpty()) {
        //     return $this->errorMessage('There is no data available for unit work plans.', 404);
        // }

          $unitworkplan_data = $unitworkplan->isNotEmpty()
        ? $unitworkplan->map(fn($item) => [
            'id'          => $item->id,
            'office_name' => $item->office_name,
            'semester'    => $item->semester,
            'year'        => $item->year,
            'date'        => $item->unitworkplanLastestRecord?->date,
            'status'      => $item->unitworkplanLastestRecord?->status,
            // 'remarks'     => $item->unitworkplanLastestRecord?->remarks,
        ])
        : 'No record found'; // ← no error, just null


            //---- opcr rating ----------//
         $employee = Employee::select('ControlNo','job_title','name','office_id')
             ->where('office_id',$user->office_id)
            ->where('job_title', 'Department Head') ->first();

            $opcr_rating = null;
        if ($employee) {
                $opcrResult  = $this->opcrOfficeHead($employee->ControlNo, $year, $semester);
                $opcr_rating = $opcrResult['average_rating'] ?? null; // ✅ extract only average_rating
            }

        return $this->successMessage([
            'opcr_rating' => $opcr_rating,
            'total_employee' => $totalEmployees,
            'ipcr_status'    => $ipcr_data,
            'opcr_status' => $opcr_data,
            'unitworkplan_status'    => $unitworkplan_data,
        
        ], 'Successfully fetch');
    }


    // list of employee without ipcr  base on the year and semester
    public function listOfEmployeeNoIpcr(Request $request)
    {
        $user = Auth::user();

        $year     = $request->input('year');
        $semester = $request->input('semester');

        // Get all employees in the same office (excluding contractual/job order)
        $employees = Employee::select('ControlNo', 'name', 'position', 'office_id', 'status', 'job_title')
            ->where('office_id', $user->office_id)
            ->whereNotIn('status', ['CONTRACTUAL', 'JOB ORDER', 'Contractual', 'Job Order']) // handle both casings
            ->get();

        // Get control numbers of employees WHO ALREADY HAVE a target period
        // Use the employee ControlNos scoped to this office instead of office_id on TargetPeriod
        $officeControlNos = $employees->pluck('ControlNo')->toArray();

        $withTargetPeriod = \App\Models\TargetPeriod::whereIn('control_no', $officeControlNos)
            ->where('semester', $semester)
            ->where('year', $year)
            ->pluck('control_no')
            ->toArray();

        // Filter out employees who already have a target period
        $employeesWithoutIpcr = $employees->whereNotIn('ControlNo', $withTargetPeriod)->values();

        $countEmployeeNoIpcr = $employeesWithoutIpcr->count();

        return $this->successMessage(['employee'=> $employeesWithoutIpcr,'total_employe_no_ipcr' => $countEmployeeNoIpcr ],'Successfully fetch');
    }


    
    private function opcrOfficeHead(string $controlNo, ?int $year, ?string $semester)
    {
        $officeHeadOpcr = Employee::select('id', 'ControlNo', 'name', 'office_id', 'office')
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
                            // ->where('status', 'Pending'); // need to change 
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

                // 'average_rating'        => [
                //     'stragetic_functions'      =>
                //     'core_functions' =>
                //     'support_functions'    =>
                //     'final_rating'       =>  //  final_rating = stragetic_functions + core_functions+ support_functions
                // ],
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

        $averageRating = [
            'strategic_functions' => $strategicWeighted,
            'core_functions'      => $coreWeighted,
            'support_functions'   => $supportWeighted,
            'final_rating'        => $finalRating,
        ];


        return [
            'opcr_accomplishment'    => $result,
            'average_rating' => $averageRating,
        ];
    }


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
    public function getSummaryMonthly(int $targetPeriodId)
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
    private function getComputationTotalAndRating(array $monthlyRatings, string $standardOutcomes)
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
            // 'ratings' => [
            //     'quantity_rating' => $quantityRating,'effectiveness_rating' => round( $quantityTotal / $totals['effectiveness_total'], 2),
            //     'timeliness_rating' => round($quantityTotal / $totals['timeliness_total'], 2),
            // ],

            // Timeliness and Effectiveness Rating Calculation
            // Rating = Total Actual / Total Quantity
            'ratings' => [
                'quantity_rating'      => (int) $quantityRating,
                'effectiveness_rating' => (int) $effectivenessRating,
                'timeliness_rating'    => (int) $timelinessRating,
            ],

        ];
    }

    //getting the quantity rating based on the standard outcomes
    private function getQuantityRating(int $quantityTotal, $standardOutcomes)
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
    private function getStandardOutcomeText($standardOutcomes, string $rating)
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
}
