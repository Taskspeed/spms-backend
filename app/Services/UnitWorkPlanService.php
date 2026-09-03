<?php

namespace App\Services;

use App\Events\IpcrEvent;
use App\Events\UnitWorkPlanEvent;
use App\Models\Employee;
use App\Models\OfficeOpcr;
use App\Models\PerformanceConfigurations;
use App\Models\PerformanceStandard;
use App\Models\StandardOutcome;
use App\Models\TargetPeriod;
use App\Models\UnitWorkPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UnitWorkPlanService
{
    /**
     * Create a new class instance.
     */
    // public function __construct()
    // {
    //     //
    // }
    public function store(?array $validated) //  old working store function
    {
        // $user = Auth::user();

        DB::beginTransaction(); // Start transaction

        try {

            foreach ($validated['employees'] as $employeeData) {
                // Check if already exists
                $existing = TargetPeriod::where('control_no', $employeeData['control_no'])
                    ->where('semester', $employeeData['semester'])
                    ->where('year', $employeeData['year'])
                    ->first();

                if ($existing) {
                    throw new \Exception("Employee ({$employeeData['control_no']}) already has a Unit Work Plan for {$employeeData['semester']} {$employeeData['year']}.");
                }
                $employee = Employee::where('ControlNo', $employeeData['control_no'])->first();

                // Create Target Period
                $targetPeriod = TargetPeriod::create([
                    'control_no' => $employeeData['control_no'],
                    'semester'   => $employeeData['semester'],
                    'year'       => $employeeData['year'],
                    'office'     => $employeeData['office'] ?? null,
                    'office2'    => $employeeData['office2'] ?? null,
                    'group'      => $employeeData['group'] ?? null,
                    'division'   => $employeeData['division'] ?? null,
                    'section'    => $employeeData['section'] ?? null,
                    'unit'       => $employeeData['unit'] ?? null,
                    'supervisory_control_no'  => $employeeData['supervisory_control_no'] ?? null,
                    'office_id'  => $employee->office_id ?? null,
                    // 'status'     => 'Draft',
                ]);

                IpcrEvent::dispatch($targetPeriod, $employee); //closed properly

                if ($employee && $employee->job_title == 'Department Head') {
                    // \Illuminate\Support\Facades\Log::info('Dispatching UnitWorkPlanRecord event...');
                    UnitWorkPlanEvent::dispatch($targetPeriod, $employee);
                }

                // Create Performance Standards
                foreach ($employeeData['performance_standards'] as $standard) {
                    $performanceStandard = PerformanceStandard::create([
                        'target_period_id'      => $targetPeriod->id,
                        'category'              => $standard['category'],
                        'mfo'                   => $standard['mfo'],
                        'output'                => $standard['output'],
                        'output_name'           => $standard['output_name'],
                        'core'                  => $standard['core_competency'] ?? null,
                        'technical'             => $standard['technical_competency'] ?? null,
                        'leadership'            => $standard['leadership_competency'] ?? null,
                        'supervisory_control_no' => $standard['supervisory_control_no'],
                        'performance_indicator' => $standard['performance_indicator'],
                        'success_indicator'     => $standard['success_indicator'],
                        'required_output'       => $standard['required_output'],
                    ]);

                    foreach ($standard['ratings'] as $rating) {
                        $standard_outcome = StandardOutcome::create([
                            'performance_standard_id' => $performanceStandard->id,
                            'rating'                  => $rating['rating'],
                            'quantity_target'         => $rating['quantity'],
                            'effectiveness_criteria'  => $rating['effectiveness'],
                            'timeliness_range'        => $rating['timeliness'],
                        ]);
                    }

                    $config = $standard['config']; // single object

                    $configuration = PerformanceConfigurations::create([
                        'performance_standard_id' => $performanceStandard->id,
                        'target_output'           => $config['target_output'],
                        'quantity_indicator'      => $config['quantity_indicator'],
                        'timeliness_indicator'    => $config['timeliness_indicator'],
                        'timeliness_range'        => $config['timelinessType']['range'],
                        'timeliness_date'         => $config['timelinessType']['date'],
                        'timeliness_description'  => $config['timelinessType']['description'],
                    ]);
                }
            }

            DB::commit(); // Commit transaction

            return [
                'target_period'        => $targetPeriod,
                'performance_standard' => $performanceStandard,
                'standard_outcome'     => $standard_outcome,
                'configuration'        => $configuration,
            ];
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback if any error occurs
            throw $e;
        }
    }

    // select organization on the office

    // fetch unit work plan of the division base on the division and other organization
    public function organization($request)
    {

        /**
         * =====================================
         * 0️⃣ VALIDATE ORGANIZATION BELONGS TO OFFICE
         * =====================================
         */
        $orgExistsInOffice = DB::table('employees')
            ->where('office', $request->office_name)
            ->where(function ($q) use ($request) {
                $q->where('office2', $request->organization)
                    ->orWhere('group', $request->organization)
                    ->orWhere('division', $request->organization)
                    ->orWhere('section', $request->organization)
                    ->orWhere('unit', $request->organization);
            })
            ->exists();

        // if (!$orgExistsInOffice) {
        //     return response()->json([
        //         // 'message' => 'Invalid organization. The organization does not belong to the selected office.'
        //         'message' => 'There are no employees assigned to the selected organization in this office.'

        //     ], 422);
        // }
        if (! $orgExistsInOffice) {
            throw new \Exception('There are no employees assigned to the selected organization in this office.', 422);
        }

        /**
         * ===============================
         * 1️⃣ Department Head
         * ===============================
         */
        $officeEmployee = DB::table('employees')
            ->where('office', $request->office_name)
            ->whereNull('division')
            ->whereNull('section')
            ->whereNull('unit')
            ->select('ControlNo', 'name', 'rank', 'position', 'sg', 'level')
            ->first();

        if (! $officeEmployee) {
            return response()->json([
                'message' => 'Department Head not found.',
            ], 404);
        }

        /**
         * ===============================
         * 2️⃣ ORGANIZATION EMPLOYEES
         * ===============================
         */
        $employees = DB::table('employees')
            ->where('office', $request->office_name)
            ->where(function ($q) use ($request) {
                $q->where('office2', $request->organization)
                    ->orWhere('group', $request->organization)
                    ->orWhere('division', $request->organization)
                    ->orWhere('section', $request->organization)
                    ->orWhere('unit', $request->organization);
            })
            ->select('ControlNo', 'name', 'rank', 'position', 'sg', 'level')
            ->get();

        $controlNos = $employees->pluck('ControlNo');

        $organizationTargetPeriods = TargetPeriod::select('id', 'control_no', 'semester', 'year')->with([
            'employee:ControlNo,name,rank,position,sg,level',
            'performanceStandards.standardOutcomes' => function ($query) {
                $query->select(
                    'id',
                    'performance_standard_id',
                    'rating',
                    'quantity_target',
                    'effectiveness_criteria',
                    'timeliness_range'
                );
            },
        ])
            ->whereIn('control_no', $controlNos)
            ->where('semester', $request->semester)
            ->where('year', $request->year)
            ->get();

        /**
         * ===============================
         * 3️⃣ GET ORGANIZATION MFOs
         * ===============================
         */
        // Extract unique MFOs from organization employees
        $organizationMFOs = $organizationTargetPeriods
            ->pluck('performanceStandards')
            ->flatten()
            ->pluck('mfo')
            ->unique()
            ->values()
            ->toArray();

        /**
         * ===============================
         * 4️⃣ FETCH Department Head TARGET PERIOD WITH FILTERED MFOs
         * ===============================
         */
        $officeTargetPeriod = TargetPeriod::select('id', 'control_no', 'semester', 'year',)->with([
            'employee:ControlNo,name,rank,position', // this maps via control_no
            'performanceStandards'                  => function ($query) use ($organizationMFOs) {
                $query->select('id', 'target_period_id', 'mfo', 'output', 'core as core_competencies', 'technical as technical_competencies', 'leadership as leadership_competencies', 'required_output', 'success_indicator')->whereIn('mfo', $organizationMFOs);
            },
            'performanceStandards.standardOutcomes' => function ($query) {
                $query->select(
                    'id',
                    'performance_standard_id',
                    'rating',
                    'quantity_target',
                    'effectiveness_criteria',
                    'timeliness_range'
                );
            },
        ])
            ->where('control_no', $officeEmployee->ControlNo)
            ->where('semester', $request->semester)
            ->where('year', $request->year)
            ->first();

        
        $unitworkplan_status = UnitWorkPlan::with([
            'unitworkplanLastestRecord' => function ($query) {
                $query->select(
                    'unitworkplan_records.id',
                    'unitworkplan_records.unitworkplan_id',
                    'unitworkplan_records.status',
                    'unitworkplan_records.remarks',
                    'unitworkplan_records.processed_by',
                );
            }
        ])->select('id', 'office_name', 'semester', 'year')

            ->where('office_name', $request->office_name)
            ->where('year', $request->year)
            ->where('semester', $request->semester)
            ->first();


        // opcr status 
        $officeOpcr_status = OfficeOpcr::select('id', 'semester', 'year', 'office_name')->where('office_name', $request->office_name)
            ->where('semester', $request->semester)
            ->where('year',  $request->year)
            ->with(['officeOpcrRecordLastestRecord' => function ($query) {
                $query->select(
                    'office_opcrs_records.id',
                    'office_opcrs_records.office_opcr_id',
                    'office_opcrs_records.date',
                    'office_opcrs_records.status'
                );
            }])
            ->first();

        return (object) [
            'office_name'               => $request->office_name,
            'organization'              => $request->organization,
            'officeEmployee'            => $officeEmployee,
            'officeTargetPeriod'        => $officeTargetPeriod,
            'organizationTargetPeriods' => $organizationTargetPeriods,
            'unitworkplan'       => $unitworkplan_status,
            'opcr'       => $officeOpcr_status,
        ];
    }



    // updating the unit work plan of employee
    public function update(?array $validated)
    {
        DB::beginTransaction();

        try {
            $performanceStandard = null;
            $standard_outcome    = null;
            $configuration       = null;

            foreach ($validated['performance_standards'] as $standard) {

                // PERFORMANCE STANDARD
                $performanceStandard = PerformanceStandard::updateOrCreate(
                    [
                        'id'        => $standard['performanceStandardId'] ?? null,      
                        // 'target_period_id' => $standard['target_period_id'], // ADD THIS
                    ],
                    [
                        'target_period_id'      => $standard['target_period_id'], // ADD THIS
                        'category'              => $standard['category'],
                        'mfo'                   => $standard['mfo'] ?? null,
                        'output'                => $standard['output'] ?? null,
                        'output_name'           => $standard['output_name'] ?? null,
                        'core'                  => $standard['core_competency'] ?? null,
                        'technical'             => $standard['technical_competency'] ?? null,
                        'leadership'            => $standard['leadership_competency'] ?? null,
                        'performance_indicator' => $standard['performance_indicator'] ?? null,
                        'success_indicator'     => $standard['success_indicator'],
                        'required_output'       => $standard['required_output'] ?? null,
                        'supervisory_control_no'       => $standard['supervisory_control_no'] ?? null,
                    ]
                    
                );

                // RATINGS (StandardOutcome)
                foreach ($standard['ratings'] as $rating) {
                    $standard_outcome = StandardOutcome::updateOrCreate(
                        [
                            'id' => $rating['ratingId'] ?? null,  // unique match key
                        ],
                        [
                            'performance_standard_id' => $performanceStandard->id,
                            'rating'                  => $rating['rating'] ?? null,
                            'quantity_target'         => $rating['quantity'] ?? null,
                            'effectiveness_criteria'  => $rating['effectiveness'] ?? null,
                            'timeliness_range'        => $rating['timeliness'] ?? null,
                        ]
                    );
                }

                // CONFIG (PerformanceConfigurations)
                $config = $standard['config'];

                $configuration = PerformanceConfigurations::updateOrCreate(
                    [
                        'id' => $standard['config']['configurationId'] ?? null,  // unique match key
                    ],
                    [
                        'performance_standard_id' => $performanceStandard->id,
                        'target_output'           => $config['targetOutput'] ?? null,
                        'quantity_indicator'      => $config['quantityIndicator'] ?? null,
                        'timeliness_indicator'    => $config['timelinessIndicator'] ?? null,
                        'timeliness_range'        => $config['timelinessType']['range'] ?? null,
                        'timeliness_date'         => $config['timelinessType']['date'] ?? null,
                        'timeliness_description'  => $config['timelinessType']['description'] ?? null,
                    ]
                );
            }

            DB::commit();

            return $performanceStandard;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to update Unit Work Plan: ' . $e->getMessage());
        }
    }

    public function supervisoryDeductionOfSuccessIndicator(int $year, string $semester, string $mfo)
    {
        $user = Auth::user();

        // Get the managerial (Department Head) of this office
        $managerial = Employee::where('job_title', 'Department Head')
            ->where('office_id', $user->office_id)
            ->first();

        if (!$managerial) {
            return response()->json([
                'success' => false,
                'message' => 'No managerial employee found.'
            ], 404);
        }

        // Get the managerial's target period
        $targetPeriod = TargetPeriod::with('performanceStandards.configurations')
            ->where('control_no', $managerial->ControlNo)
            ->where('year', $year)
            ->where('semester', $semester)
            ->first();

        if (!$targetPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'No target period found for this managerial.'
            ], 404);
        }

        // Get ALL target periods in this office for this year/semester (excluding Department Head)
        $allOtherTargetPeriods = TargetPeriod::with('performanceStandards.configurations')
            ->where('office_id', $user->office_id)
            ->where('year', $year)
            ->where('semester', $semester)
            ->where('control_no', '!=', $managerial->ControlNo)
            ->get();

        // Get all employees in this office for name/rank/job_title lookup
        $allEmployees = Employee::where('office_id', $user->office_id)->get()->keyBy('ControlNo');

        /**
         * Sum the total targets of all performance standards that:
         * - belong to a direct report of $controlNo (matched via standard->supervisory_control_no)
         * - match the given $mfoKey
         */
     
      
            $getTotalClaimed = function (string $controlNo, string $mfoKey, ?string $outputKey) use (
                $allOtherTargetPeriods, $allEmployees
            ) {
                $claimed = 0;

                // Base natin dito kung managerial talaga ang "parent" (yung tinuturo ng supervisory_control_no)
                $parentEmployee     = $allEmployees->get($controlNo);
                $parentIsManagerial = $parentEmployee && $parentEmployee->rank === 'Managerial';

                foreach ($allOtherTargetPeriods as $reportPeriod) {
                    $matchedStandards = $reportPeriod->performanceStandards->filter(
                        function ($s) use ($mfoKey, $outputKey, $controlNo, $parentIsManagerial) {
                            if ($s->mfo !== $mfoKey) return false;
                            if ($s->supervisory_control_no !== $controlNo) return false;
                            if ($s->category === 'C. SUPPORT FUNCTION') return false;
                            if ($s->configurations->contains(fn($config) => $config->quantity_indicator === 'C')) {
                                return false;
                            }

                            // Managerial parent => mfo lang ang basehan.
                            // Non-managerial parent => kailangan match din ang output_name.
                            if (!$parentIsManagerial) {
                                return $s->output_name === $outputKey;
                            }

                            return true;
                        }
                    );

                    foreach ($matchedStandards as $standard) {
                        $claimed += $this->extractNumber($standard->success_indicator);
                    }
                }

                return $claimed;
            };

                // ⬇️ dagdag mo dito, pagkatapos ng $getTotalClaimed
        $isExcludedFromAvailable = function ($standard) {
            return $standard->category === 'C. SUPPORT FUNCTION'
                || $standard->configurations->contains(
                    fn($config) => $config->quantity_indicator === 'C'
                );
        };

        // Build managerial MFOs — claimed = sum of subordinates' standards pointing to this managerial
        $standards = $mfo
            ? $targetPeriod->performanceStandards->where('mfo', $mfo)
            : $targetPeriod->performanceStandards;

        $result = $standards->map(function ($standard) use ($getTotalClaimed, $managerial, $isExcludedFromAvailable) {
            $totalTarget = $this->extractNumber($standard->success_indicator);

            if ($isExcludedFromAvailable($standard)) {
                return [
                    'category'              => $standard->category,
                    'mfo'                   => $standard->mfo,
                    'output'                => $standard->output,
                    'output_name'           => $standard->output_name,
                    'performance_indicator' => $standard->performance_indicator,
                    'success_indicator'     => $standard->success_indicator,
                    'total_target'          => $totalTarget,
                    'claimed'               => 0,
                    'available'             => $totalTarget, // walang kaltas — buo pa rin
                ];
            }

            $claimed   = $getTotalClaimed($managerial->ControlNo, $standard->mfo, $standard->output_name);
            $available = $totalTarget - $claimed;

            return [
                'category'              => $standard->category,
                'mfo'                   => $standard->mfo,
                'output'                => $standard->output,
                'output_name'           => $standard->output_name,
                'performance_indicator' => $standard->performance_indicator,
                'success_indicator'     => $standard->success_indicator,
                'total_target'          => $totalTarget,
                'claimed'               => $claimed,
                'available'             => max(0, $available),
            ];
        });

        // Build subordinates list
        $subordinatesData = $allOtherTargetPeriods->map(function ($tp) use (
            $allEmployees,
            $getTotalClaimed,
            $mfo,
            $isExcludedFromAvailable 
        ) {
            $employee = $allEmployees->get($tp->control_no);

            $standards = $mfo
                ? $tp->performanceStandards->where('mfo', $mfo)
                : $tp->performanceStandards;

            if ($standards->isEmpty()) {
                return [
                    'controlNo'  => $tp->control_no,
                    'name'       => $employee?->name,
                    'rank'       => $employee?->rank,
                    'job_title'  => $employee?->job_title,
                    'mfos'       => null,
                ];
            }

            $mfos = $standards->map(function ($standard) use ($tp, $getTotalClaimed, $isExcludedFromAvailable) {
            $totalTarget = $this->extractNumber($standard->success_indicator);

            if ($isExcludedFromAvailable($standard)) {
                return [
                    'category'               => $standard->category,
                    'mfo'                    => $standard->mfo,
                    'output'                 => $standard->output,
                    'output_name'            => $standard->output_name,
                    'performance_indicator'  => $standard->performance_indicator,
                    'success_indicator'      => $standard->success_indicator,
                    'supervisory_control_no' => $standard->supervisory_control_no,
                    'total_target'           => $totalTarget,
                    'claimed'                => 0,
                    'available'              => $totalTarget, // walang kaltas
                ];
            }

            $claimed   = $getTotalClaimed($tp->control_no, $standard->mfo,$standard->output_name);
            $available = $totalTarget - $claimed;

            return [
                'category'               => $standard->category,
                'mfo'                    => $standard->mfo,
                'output'                 => $standard->output,
                'output_name'            => $standard->output_name,
                'performance_indicator'  => $standard->performance_indicator,
                'success_indicator'      => $standard->success_indicator,
                'supervisory_control_no' => $standard->supervisory_control_no,
                'total_target'           => $totalTarget,
                'claimed'                => $claimed,
                'available'              => max(0, $available),
            ];
        });

            return [
                'controlNo'  => $tp->control_no,
                'name'       => $employee?->name,
                'rank'       => $employee?->rank,
                'job_title'  => $employee?->job_title,
                'mfos'       => $mfos->values(),
            ];
        });

        return response()->json([
            'controlNo'     => $managerial->ControlNo,
            'name'          => $managerial->name,
            'rank'          => $managerial->rank,
            'job_title'     => $managerial->job_title,
            'office'        => $managerial->office,
            'year'          => $year,
            'semester'      => $semester,
            'mfos'          => $result->values(),
            'supervisories' => $subordinatesData->filter(function ($subordinate) use ($allEmployees) {
                $emp = $allEmployees->get($subordinate['controlNo']);
                return $emp && $emp->job_title !== 'Employee';
            })->values(),
        ], 200);
    }

    private function extractNumber(string $string): int
    {
        preg_match('/^\d+/', trim($string), $matches);
        return isset($matches[0]) ? (int) $matches[0] : 0;
    }


    //   public function supervisoryDeductionOfSuccessIndicator(int $year, string $semester, string $mfo)
    // {
    //     $user = Auth::user();

    //     // Get the managerial (Department Head) of this office
    //     $managerial = Employee::where('job_title', 'Department Head')
    //         ->where('office_id', $user->office_id)
    //         ->first();

    //     if (!$managerial) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No managerial employee found.'
    //         ], 404);
    //     }

    //     // Get the managerial's target period
    //     $targetPeriod = TargetPeriod::with('performanceStandards')
    //         ->where('control_no', $managerial->ControlNo)
    //         ->where('year', $year)
    //         ->where('semester', $semester)
    //         ->first();

    //     if (!$targetPeriod) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No target period found for this managerial.'
    //         ], 404);
    //     }

    //     // Get ALL target periods in this office for this year/semester (excluding Department Head)
    //     $allOtherTargetPeriods = TargetPeriod::with('performanceStandards')
    //         ->where('office_id', $user->office_id)
    //         ->where('year', $year)
    //         ->where('semester', $semester)
    //         ->where('control_no', '!=', $managerial->ControlNo)
    //         ->get();

    //     // Get all employees in this office for name/rank/job_title lookup
    //     $allEmployees = Employee::where('office_id', $user->office_id)->get()->keyBy('ControlNo');

    //     /**
    //      * Sum the total targets of all performance standards that:
    //      * - belong to a direct report of $controlNo (matched via standard->supervisory_control_no)
    //      * - match the given $mfoKey
    //      */
    //     $getTotalClaimed = function (string $controlNo, string $mfoKey) use (
    //         $allOtherTargetPeriods
    //     ) {
    //         $claimed = 0;

    //         foreach ($allOtherTargetPeriods as $reportPeriod) {
    //             // Filter standards where:
    //             // 1. mfo matches
    //             // 2. supervisory_control_no on the standard points to $controlNo
    //             $matchedStandards = $reportPeriod->performanceStandards->filter(
    //                 fn($s) => $s->mfo === $mfoKey
    //                     && $s->supervisory_control_no === $controlNo
    //             );

    //             foreach ($matchedStandards as $standard) {
    //                 $claimed += $this->extractNumber($standard->success_indicator);
    //             }
    //         }

    //         return $claimed;
    //     };

    //     // Build managerial MFOs — claimed = sum of subordinates' standards pointing to this managerial
    //     $standards =  $targetPeriod->performanceStandards->where('mfo', $mfo);      

    //     $result = $standards->map(function ($standard) use ($getTotalClaimed, $managerial) {
    //         $totalTarget = $this->extractNumber($standard->success_indicator);
    //         $claimed     = $getTotalClaimed($managerial->ControlNo, $standard->mfo);
    //         $available   = $totalTarget - $claimed;

    //         return [
    //             'category'              => $standard->category,
    //             'mfo'                   => $standard->mfo,
    //             'output'                => $standard->output,
    //             'output_name'           => $standard->output_name,
    //             'performance_indicator' => $standard->performance_indicator,
    //             'success_indicator'     => $standard->success_indicator,
    //             'total_target'          => $totalTarget,
    //             'claimed'               => $claimed,
    //             'available'             => max(0, $available),
    //         ];
    //     });

    //     // Build subordinates list
    //     $subordinatesData = $allOtherTargetPeriods->map(function ($tp) use (
    //         $allEmployees,
    //         $getTotalClaimed,
    //         $mfo
    //     ) {
    //         $employee = $allEmployees->get($tp->control_no);

    //         $standards = $mfo
    //             ? $tp->performanceStandards->where('mfo', $mfo)
    //             : $tp->performanceStandards;

    //         if ($standards->isEmpty()) {
    //             return [
    //                 'controlNo'  => $tp->control_no,
    //                 'name'       => $employee?->name,
    //                 'rank'       => $employee?->rank,
    //                 'job_title'  => $employee?->job_title,
    //                 'mfos'       => null,
    //             ];
    //         }

    //         $mfos = $standards->map(function ($standard) use ($tp, $getTotalClaimed) {
    //             $totalTarget = $this->extractNumber($standard->success_indicator);

    //             // Claimed = standards from others that point to THIS person's control_no
    //             $claimed   = $getTotalClaimed($tp->control_no, $standard->mfo);
    //             $available = $totalTarget - $claimed;

    //             return [
    //                 'category'               => $standard->category,
    //                 'mfo'                    => $standard->mfo,
    //                 'output'                 => $standard->output,
    //                 'output_name'            => $standard->output_name,
    //                 'performance_indicator'  => $standard->performance_indicator,
    //                 'success_indicator'      => $standard->success_indicator,
    //                 'supervisory_control_no' => $standard->supervisory_control_no,
    //                 'total_target'           => $totalTarget,
    //                 'claimed'                => $claimed,
    //                 'available'              => max(0, $available),
    //             ];
    //         });

    //         return [
    //             'controlNo'  => $tp->control_no,
    //             'name'       => $employee?->name,
    //             'rank'       => $employee?->rank,
    //             'job_title'  => $employee?->job_title,
    //             'mfos'       => $mfos->values(),
    //         ];
    //     });

    //     return response()->json([
    //         'controlNo'     => $managerial->ControlNo,
    //         'name'          => $managerial->name,
    //         'rank'          => $managerial->rank,
    //         'job_title'     => $managerial->job_title,
    //         'office'        => $managerial->office,
    //         'year'          => $year,
    //         'semester'      => $semester,
    //         'mfos'          => $result->values(),
    //         'supervisories' => $subordinatesData->filter(function ($subordinate) use ($allEmployees) {
    //             $emp = $allEmployees->get($subordinate['controlNo']);
    //             return $emp && $emp->job_title !== 'Employee';
    //         })->values(),
    //     ], 200);
    // }

    // private function extractNumber(string $string): int
    // {
    //     preg_match('/^\d+/', trim($string), $matches);
    //     return isset($matches[0]) ? (int) $matches[0] : 0;
    // }
}
