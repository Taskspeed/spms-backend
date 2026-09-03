<?php

namespace App\Services\Erms;

use App\Models\Employee;
use App\Models\TargetPeriod;

class ErmsUnitWorkPlanService
{
   
    public function supervisoryDeductionOfSuccessIndicator(int $year, string $semester, string $mfo, int $officeId )
    {

        // Get the managerial (Department Head) of this office
        $managerial = Employee::where('job_title', 'Department Head')
            ->where('office_id', $officeId)
            ->first();

        if (!$managerial) {
            return response()->json([
                'success' => false,
                'message' => 'No managerial employee found.'
            ], 404);
        }

        // Get the managerial's target period
        $targetPeriod = TargetPeriod::with('performanceStandards')
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
        $allOtherTargetPeriods = TargetPeriod::with('performanceStandards')
            ->where('office_id', $officeId)
            ->where('year', $year)
            ->where('semester', $semester)
            ->where('control_no', '!=', $managerial->ControlNo)
            ->get();

        // Get all employees in this office for name/rank/job_title lookup
        $allEmployees = Employee::where('office_id',$officeId)->get()->keyBy('ControlNo');

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
}
