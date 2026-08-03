<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OfficeOpcr;
use App\Models\OfficeOpcrRecord;
use App\Models\opcr;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class EmployeeSupervisorService
{
    /**
     * Create a new class instance.
     */

    use ApiResponseTrait;

    public function getListOfEmployeeBaseOnSupervisor(int $year, string $semester, string $controlNo)
    {

        // if (!$controlNo) return response()->json([]);
        if (!$controlNo) return null;  // or throw an exception

        // ── Fetch the logged-in supervisor's own employee record ──────────────────
        $selfEmployee = Employee::select( //vwEmployee
            'id',
            'name',
            'rank',
            'ControlNo',
            'position',
            'office',
            'office2',
            'group',
            'division',
            'section',
            'unit',
            'status',
            'job_title',
            'sg',
            'level',
            'office_id'
        )
            ->where('ControlNo', $controlNo)
            ->first();

        // if (!$selfEmployee) return response()->json([]);
        if (!$selfEmployee) return null;

        // ── Build hierarchy from the employee's own DB columns ───────────────────
        Log::info('before hierarchy', ['controlNo' => $selfEmployee->ControlNo]);
        $hierarchy = $this->buildHierarchyFromEmployee($selfEmployee);
        Log::info('hierarchy done');
        

        // ── Resolve signatories ───────────────────────────────────────────────────
  
        Log::info('before signatories');
        $signatories = $this->resolveSignatories($selfEmployee, $selfEmployee->office_id, $year, $semester);
        Log::info('signatories done');

        // ── Fetch existing target period for the logged-in supervisor ────────────
        $selfTargetPeriod = $selfEmployee->targetPeriods()
            ->select('id', 'control_no', 'semester', 'year', 'supervisory_control_no')
            ->where('year', $year)
            ->where('semester', $semester)
            ->with('ipcrLastestRecord')
            ->first();

        $hasTargetPeriod      = $selfTargetPeriod ? true : false;
        $existingTargetPeriod = null;

        if ($selfTargetPeriod) {
            $latestRecord = $selfTargetPeriod->ipcrLastestRecord;
            $existingTargetPeriod = [
                'id'                     => $selfTargetPeriod->id,
                'control_no'             => $selfTargetPeriod->control_no,
                'year'                   => $selfTargetPeriod->year,
                'semester'               => $selfTargetPeriod->semester,
                'supervisory_control_no' => $selfTargetPeriod->supervisory_control_no,
                'status'                 => $latestRecord?->status ?? null,
                'processed_by_name'      => $latestRecord?->processed_by_name ?? null,
                'date'                   => $latestRecord?->date ?? null,
            ];
        }

        // ── Build the single employee object (the logged-in supervisor) ───────────
        $employeeData = [
            'id'                     => (string) $selfEmployee->id,
            'controlNo'              => $selfEmployee->ControlNo ?? null,
            'name'                   => $selfEmployee->name,
            'label'                  => $selfEmployee->name,
            'position'               => $selfEmployee->position,
            'rank'                   => $selfEmployee->rank,
            'jobTitle'               => $selfEmployee->job_title,
            'sg'                     => $selfEmployee->sg ?? null,
            'level'                  => $selfEmployee->level ?? null,
            'status'                 => $selfEmployee->status,
            'office'                 => $selfEmployee->office,
            'office2'                => $selfEmployee->office2,
            'group'                  => $selfEmployee->group,
            'division'               => $selfEmployee->division,
            'section'                => $selfEmployee->section,
            'unit'                   => $selfEmployee->unit,
            'office_id'                   => $selfEmployee->office_id,
            'has_target_period'      => $hasTargetPeriod,
            'existing_target_period' => $existingTargetPeriod,
            'supervisorySignatory'   => $signatories['supervisorySignatory'],
            'managerialSignatory'    => $signatories['managerialSignatory'],
        ];

        // ── Get the full office structure for finding subordinates ────────────────
        Log::info('before structure');
        $structure = $this->getStructureOffice($selfEmployee);
        Log::info('structure done');

        $structureData = json_decode($structure->getContent(), true);

        // Get subordinates list (same node employees)
        Log::info('before findEmployeesSameNode');
        $employees = $this->findEmployeesSameNode($structureData, $controlNo);
        Log::info('findEmployeesSameNode done');

        // Safely extract control numbers
        $employeeControlNos = collect($employees)->map(function ($employee) {
            if (is_array($employee)) {
                return $employee['ControlNo']
                    ?? $employee['control_no']
                    ?? $employee['controlNo']
                    ?? null;
            }
            return $employee->ControlNo ?? $employee->control_no ?? null;
        })->filter()->values();

        // Fetch subordinates with their target periods
        $ipcrEmployee = Employee::with(['targetPeriods' => function ($query) use ($year, $semester) {
            $query->select('id', 'control_no', 'semester', 'year', 'supervisory_control_no')
                ->where('year', $year)
                ->where('semester', $semester)
                ->with('ipcrLastestRecord');
        }])
            ->select(
                'id',
                'name',
                'ControlNo',
                'status',
                'position',
                'job_title',
                'office',
                'office2',
                'group',
                'division',
                'section',
                'unit'
            )
            ->whereIn('ControlNo', $employeeControlNos)
            ->get()
            ->keyBy('ControlNo');

        // Map subordinates
        $employeesWithIpcr = collect($employees)->map(function ($employee) use ($ipcrEmployee) {
            if (is_array($employee)) {
                $empControlNo = $employee['ControlNo']
                    ?? $employee['control_no']
                    ?? $employee['controlNo']
                    ?? null;
            } else {
                $empControlNo = $employee->ControlNo ?? $employee->control_no ?? null;
            }

            $dbEmployee = $ipcrEmployee->get($empControlNo);
            $existing   = $dbEmployee?->targetPeriods->first();

            $hasTargetPeriod      = $existing ? true : false;
            $existingTargetPeriod = null;

            if ($existing) {
                $latestRecord = $existing->ipcrLastestRecord;
                $existingTargetPeriod = [
                    'id'                     => $existing->id,
                    'control_no'             => $existing->control_no,
                    'year'                   => $existing->year,
                    'semester'               => $existing->semester,
                    'supervisory_control_no' => $existing->supervisory_control_no ?? null,
                    'status'                 => $latestRecord?->status ?? null,
                    'processed_by_name'      => $latestRecord?->processed_by_name ?? null,
                    'date'                   => $latestRecord?->date ?? null,
                ];
            }

            return [
                'controlNo'              => $empControlNo,
                'name'                   => $dbEmployee?->name,
                'status'                 => $dbEmployee?->status,
                'position'               => $dbEmployee?->position,
                'job_title'              => $dbEmployee?->job_title,
                'office'                 => $dbEmployee?->office,
                'office2'                => $dbEmployee?->office2,
                'group'                  => $dbEmployee?->group,
                'division'               => $dbEmployee?->division,
                'section'                => $dbEmployee?->section,
                'unit'                   => $dbEmployee?->unit,
                'has_target_period'      => $hasTargetPeriod,
                'existing_target_period' => $existingTargetPeriod,
            ];
        });


        return [
            'hierarchy'    => $hierarchy,
            'employee'     => $employeeData,
            // 'subordinates' => $employeesWithIpcr,
        ];
    }

    private function findEmployeesSameNode(array $structure, string $controlNo): array
    {
        foreach ($structure as $officeData) {

            // Check office-level employees
            $found = $this->controlNoExistsIn($officeData['employees'], $controlNo);
            if ($found) {
                return $this->excludeSelf($officeData['employees'], $controlNo);
            }

            foreach ($officeData['office2'] as $office2Data) {

                // Check office2-level employees
                $found = $this->controlNoExistsIn($office2Data['employees'], $controlNo);
                if ($found) {
                    return $this->excludeSelf($office2Data['employees'], $controlNo);
                }

                foreach ($office2Data['groups'] as $groupData) {

                    // Check group-level employees
                    $found = $this->controlNoExistsIn($groupData['employees'], $controlNo);
                    if ($found) {
                        return $this->excludeSelf($groupData['employees'], $controlNo);
                    }

                    foreach ($groupData['divisions'] as $divisionData) {

                        // Check division-level employees
                        $found = $this->controlNoExistsIn($divisionData['employees'], $controlNo);
                        if ($found) {
                            return $this->excludeSelf($divisionData['employees'], $controlNo);
                        }

                        foreach ($divisionData['sections'] as $sectionData) {

                            // Check section-level employees
                            $found = $this->controlNoExistsIn($sectionData['employees'], $controlNo);
                            if ($found) {
                                return $this->excludeSelf($sectionData['employees'], $controlNo);
                            }

                            foreach ($sectionData['units'] as $unitData) {

                                // Check unit-level employees
                                $found = $this->controlNoExistsIn($unitData['employees'], $controlNo);
                                if ($found) {
                                    return $this->excludeSelf($unitData['employees'], $controlNo);
                                }
                            }
                        }
                    }
                }
            }
        }

        return [];
    }

    private function controlNoExistsIn(array $employees, string $controlNo): bool
    {
        return collect($employees)->contains('controlNo', $controlNo);
    }

    private function excludeSelf(array $employees, string $controlNo): array
    {
        // Find the current employee in the list to check their job_title
        $self = collect($employees)->firstWhere('controlNo', $controlNo);
        $isSupervisor = $self && in_array($self['job_title'], ['Section Head', 'Division Head', 'Department Head', 'Group Head', 'Office Head']);

        return collect($employees)
            ->filter(function ($e) use ($controlNo, $isSupervisor) {
                // Always filter to only CASUAL/REGULAR
                if (!in_array(strtoupper($e['status']), ['CASUAL', 'REGULAR'])) {
                    return false;
                }

                if ($isSupervisor) {
                    // Supervisor: show everyone except themselves
                    return $e['controlNo'] !== $controlNo;
                } else {
                    // Rank-and-file: show only themselves
                    return $e['controlNo'] === $controlNo;
                }
            })
            ->values()
            ->all();
    }
    // private function excludeSelf(array $employees, string $controlNo): array
    // {
    //     return collect($employees)
    //         // ->reject(fn($e) => $e['controlNo'] === $controlNo)
    //         ->filter(fn($e) => in_array(strtoupper($e['status']), ['CASUAL', 'REGULAR']))
    //         ->values()
    //         ->all();
    // }
    /**
     * Walk the structure tree and return the hierarchy labels
     * for the node where the given controlNo lives.
     */
    private function findEmployeeHierarchy(array $structure, string $controlNo): array
    {
        foreach ($structure as $officeData) {

            // Office-level
            if ($this->controlNoExistsIn($officeData['employees'], $controlNo)) {
                return $this->buildHierarchyResult($officeData['office'], null, null, null, null, null);
            }

            foreach ($officeData['office2'] as $office2Data) {

                // Office2-level
                if ($this->controlNoExistsIn($office2Data['employees'], $controlNo)) {
                    return $this->buildHierarchyResult($officeData['office'], $office2Data['office2'], null, null, null, null);
                }

                foreach ($office2Data['groups'] as $groupData) {

                    // Group-level
                    if ($this->controlNoExistsIn($groupData['employees'], $controlNo)) {
                        return $this->buildHierarchyResult($officeData['office'], $office2Data['office2'], $groupData['group'], null, null, null);
                    }

                    foreach ($groupData['divisions'] as $divisionData) {

                        // Division-level
                        if ($this->controlNoExistsIn($divisionData['employees'], $controlNo)) {
                            return $this->buildHierarchyResult($officeData['office'], $office2Data['office2'], $groupData['group'], $divisionData['division'], null, null);
                        }

                        foreach ($divisionData['sections'] as $sectionData) {

                            // Section-level
                            if ($this->controlNoExistsIn($sectionData['employees'], $controlNo)) {
                                return $this->buildHierarchyResult($officeData['office'], $office2Data['office2'], $groupData['group'], $divisionData['division'], $sectionData['section'], null);
                            }

                            foreach ($sectionData['units'] as $unitData) {

                                // Unit-level
                                if ($this->controlNoExistsIn($unitData['employees'], $controlNo)) {
                                    return $this->buildHierarchyResult($officeData['office'], $office2Data['office2'], $groupData['group'], $divisionData['division'], $sectionData['section'], $unitData['unit']);
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['hierarchy' => null];
    }

    /**
     * Build the hierarchy array shape matching your expected response.
     */
    private function buildHierarchyResult(?string $office, ?string $office2, ?string $group, ?string $division, ?string $section, ?string $unit): array
    {
        return [
            'hierarchy' => [
                'office'   => $office   ? ['label' => $office,   'type' => 'office']   : null,
                'office2'  => $office2  ? ['label' => $office2,  'type' => 'office2']  : null,
                'group'    => $group    ? ['label' => $group,    'type' => 'group']    : null,
                'division' => $division ? ['label' => $division, 'type' => 'division'] : null,
                'section'  => $section  ? ['label' => $section,  'type' => 'section']  : null,
                'unit'     => $unit     ? ['label' => $unit,     'type' => 'unit']     : null,
            ],
            // carry these for signatory resolution
            '_office'   => $office,
            '_office2'  => $office2,
            '_group'    => $group,
            '_division' => $division,
            '_section'  => $section,
            '_unit'     => $unit,
        ];
    }

    /**
     * Build hierarchy labels directly from the employee's own DB columns.
     * This is accurate regardless of where they appear in the structure tree.
     */
    private function buildHierarchyFromEmployee(Employee $employee): array
    {
        return [
            'office'  => $employee->office
                ? ['label' => $employee->office,  'type' => 'office']
                : null,
            'office2' => $employee->office2
                ? ['label' => $employee->office2, 'type' => 'office2']
                : null,
            'group'   => $employee->group
                ? ['label' => $employee->group,   'type' => 'group']
                : null,
            'division' => $employee->division
                ? ['label' => $employee->division, 'type' => 'division']
                : null,
            'section' => $employee->section
                ? ['label' => $employee->section,  'type' => 'section']
                : null,
            'unit'    => $employee->unit
                ? ['label' => $employee->unit,     'type' => 'unit']
                : null,
        ];
    }


    //managerial and supervisory
    private function resolveSignatories(Employee $employee, int $officeId, int $year, string $semester): array
    {

        // ── Check OPCR exists for this office/semester/year ───────────────────────
        $opcr = OfficeOpcr::where('office_id', $officeId)
            ->where('semester', $semester)
            ->where('year', $year)
            ->first();

        if (!$opcr) {
            throw new \Exception('No OPCR found for this office. Please create an OPCR first.');
        }

        // ── Check OPCR record is Approved ─────────────────────────────────────────
        $opcrRecord = OfficeOpcrRecord::where('id', $opcr->id)
            ->where('status', 'Approved');

        if (!$opcrRecord) {
            throw new \Exception('OPCR is not yet approved. Please have it approved before proceeding.');
        }

        // ── Always get the Department Head first ──────────────────────────────────────
        $officeHead = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
            ->where('office_id', $officeId)
            ->where('job_title', 'Department Head')
            ->where('ControlNo', '!=', $employee->ControlNo)
            ->first();

        $officeHeadData = $officeHead ? array_merge([
            'controlNo' => $officeHead->ControlNo ?? null,
            'name'      => $officeHead->name,
            'position'  => $officeHead->position,
            'rank'      => $officeHead->rank,
            'jobTitle'  => $officeHead->job_title,
        ], $this->getSignatoryTargetPeriod($officeHead->ControlNo, $year, $semester)) : null;

        // managerialSignatory is always the Department Head
        $managerialSignatory = $officeHeadData;

        // ── supervisorySignatory: Section Head → Division Head → Office2 Head → Department Head ──
        $supervisorySignatory = null;

        // 1️⃣ If employee is in a unit, look for Section Head in that section
        if ($employee->unit && $employee->job_title !== 'Unit Head') {
            $unitHead = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
                ->where('office_id', $officeId)
                ->where('unit', $employee->unit)
                ->where('job_title', 'Unit Head')
                ->where('ControlNo', '!=', $employee->ControlNo)
                ->first();

            if ($unitHead) {
                $supervisorySignatory = array_merge([
                    'controlNo' => $unitHead->ControlNo,
                    'name'      => $unitHead->name,
                    'position'  => $unitHead->position,
                    'rank'      => $unitHead->rank,
                    'jobTitle'  => $unitHead->job_title,
                ], $this->getSignatoryTargetPeriod($unitHead->ControlNo, $year, $semester));
            }
        }


        // 1️⃣ If employee is in a section, look for Section Head in that section
        if ($employee->section && $employee->job_title !== 'Section Head') {
            $sectionHead = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
                ->where('office_id', $officeId)
                ->where('section', $employee->section)
                ->where('job_title', 'Section Head')
                ->where('ControlNo', '!=', $employee->ControlNo)
                ->first();

            if ($sectionHead) {
                $supervisorySignatory = array_merge([
                    'controlNo' => $sectionHead->ControlNo,
                    'name'      => $sectionHead->name,
                    'position'  => $sectionHead->position,
                    'rank'      => $sectionHead->rank,
                    'jobTitle'  => $sectionHead->job_title,
                ], $this->getSignatoryTargetPeriod($sectionHead->ControlNo, $year, $semester));
            }
        }

        // 2️⃣ Fallback: Look for Division Head in the same division
        if (!$supervisorySignatory && $employee->division) {
            $divisionHead = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
                ->where('office_id', $officeId)
                ->where('division', $employee->division)
                ->where('job_title', 'Division Head')
                ->where('ControlNo', '!=', $employee->ControlNo)
                ->whereNull('section')
                ->whereNull('unit')
                ->first();

            if ($divisionHead) {
                $supervisorySignatory = array_merge([
                    'controlNo' => $divisionHead->ControlNo,
                    'name'      => $divisionHead->name,
                    'position'  => $divisionHead->position,
                    'rank'      => $divisionHead->rank,
                    'jobTitle'  => $divisionHead->job_title,
                ], $this->getSignatoryTargetPeriod($divisionHead->ControlNo, $year, $semester));
            }
        }

        // 3️⃣ Fallback: Look for a Office Head / Office2 Head in the same office2
        if (!$supervisorySignatory && $employee->office2) {
            $office2Head = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
                ->where('office_id', $officeId)
                ->where('office2', $employee->office2)
                ->where('job_title', 'Office Head') // adjust to your actual job_title value
                ->where('ControlNo', '!=', $employee->ControlNo)
                ->whereNull('division')
                ->whereNull('section')
                ->whereNull('unit')
                ->first();

            if ($office2Head) {
                $supervisorySignatory = array_merge([
                    'controlNo' => $sectionHead->ControlNo,
                    'name'      => $sectionHead->name,
                    'position'  => $sectionHead->position,
                    'rank'      => $sectionHead->rank,
                    'jobTitle'  => $sectionHead->job_title,
                ], $this->getSignatoryTargetPeriod($sectionHead->ControlNo, $year, $semester));
            }
        }

        // 3️⃣ Fallback: Look for a Office Head / Office2 Head in the same office2
        if (!$supervisorySignatory && $employee->group) {
            $groupHead = Employee::select('id', 'name', 'rank', 'ControlNo', 'position', 'status', 'job_title')
                ->where('office_id', $officeId)
                ->where('group', $employee->group)
                ->where('job_title', 'Group Head') // adjust to your actual job_title value
                ->where('ControlNo', '!=', $employee->ControlNo)
                ->whereNull('division')
                ->whereNull('section')
                ->whereNull('unit')
                ->first();

            if ($groupHead) {
                $supervisorySignatory = array_merge([
                    'controlNo' => $sectionHead->ControlNo,
                    'name'      => $sectionHead->name,
                    'position'  => $sectionHead->position,
                    'rank'      => $sectionHead->rank,
                    'jobTitle'  => $sectionHead->job_title,
                ], $this->getSignatoryTargetPeriod($sectionHead->ControlNo, $year, $semester));
            }
        }

        // 4️⃣ Final fallback: Department Head
        if (!$supervisorySignatory) {
            $supervisorySignatory = $officeHeadData;
        }

        return [
            'supervisorySignatory' => $supervisorySignatory,
            'managerialSignatory'  => $managerialSignatory,
        ];
    }

    private function getSignatoryTargetPeriod(string $controlNo, int $year, string $semester): array
    {
        $signatory = Employee::where('ControlNo', $controlNo)->first();

        if (!$signatory) {
            return [
                'has_target_period'      => false,
                'existing_target_period' => null,
            ];
        }

        $targetPeriod = $signatory->targetPeriods()
            ->select('id', 'control_no', 'semester', 'year', 'supervisory_control_no')
            ->where('year', $year)
            ->where('semester', $semester)
            ->with('ipcrLastestRecord')
            ->first();

        if (!$targetPeriod) {
            return [
                'has_target_period'      => false,
                'existing_target_period' => null,
            ];
        }

        $latestRecord = $targetPeriod->ipcrLastestRecord;

        return [
            'has_target_period'      => true,
            'existing_target_period' => [
                'id'                     => $targetPeriod->id,
                'control_no'             => $targetPeriod->control_no,
                'year'                   => $targetPeriod->year,
                'semester'               => $targetPeriod->semester,
                'supervisory_control_no' => $targetPeriod->supervisory_control_no,
                'status'                 => $latestRecord?->status ?? null,
                'processed_by_name'      => $latestRecord?->processed_by_name ?? null,
                'date'                   => $latestRecord?->date ?? null,
            ],
        ];
    }

    public function getStructureOffice(Employee $selfEmployee)
    {
        $user = Auth::user();
        $officeId = $selfEmployee->office_id;

        if (!$officeId) return response()->json([]);

        $officeName = DB::table('offices')->where('id', $officeId)->value('name');
        if (!$officeName) return response()->json([]);

        $rows = DB::table('employees as e')
            ->where('e.office', $officeName)
            ->select(
                'e.ControlNo',
                'e.ItemNo',
                'e.office',
                'e.office2',
                'e.group',
                'e.division',
                'e.section',
                'e.unit',
                'e.name',
                'e.status',
                'e.position',
                'e.job_title',

            )
            ->orderBy('e.office2')
            ->orderBy('e.group')
            ->orderBy('e.division')
            ->orderBy('e.section')
            ->orderBy('e.unit')
            ->orderBy('e.ItemNo')
            ->get();

        if ($rows->isEmpty()) return response()->json([]);

        $result = [];
        $officeGroups = $rows->groupBy('office');

        foreach ($officeGroups as $officeName => $officeRows) {
            $officeData = [
                'office'    => $officeName,
                'employees' => [],
                'office2'   => []
            ];

            $officeEmployees = $officeRows->filter(
                fn($r) => is_null($r->office2) && is_null($r->group) &&
                    is_null($r->division) && is_null($r->section) && is_null($r->unit)
            );

            $officeData['employees'] = $officeEmployees
                ->sortBy('ItemNo')
                ->map(fn($r) => $this->mapEmployee($r))
                ->values();

            $remainingOfficeRows = $officeRows->reject(
                fn($r) => is_null($r->office2) && is_null($r->group) &&
                    is_null($r->division) && is_null($r->section) && is_null($r->unit)
            );

            foreach ($remainingOfficeRows->groupBy('office2') as $office2Name => $office2Rows) {
                $office2Data = [
                    'office2'   => $office2Name,
                    'employees' => [],
                    'groups'    => []
                ];

                $office2Employees = $office2Rows->filter(
                    fn($r) => is_null($r->group) && is_null($r->division) &&
                        is_null($r->section) && is_null($r->unit)
                );

                $office2Data['employees'] = $office2Employees
                    ->sortBy('ItemNo')
                    ->map(fn($r) => $this->mapEmployee($r))
                    ->values();

                $remainingOffice2Rows = $office2Rows->reject(
                    fn($r) => is_null($r->group) && is_null($r->division) &&
                        is_null($r->section) && is_null($r->unit)
                );

                foreach ($remainingOffice2Rows->groupBy('group') as $groupName => $groupRows) {
                    $groupData = [
                        'group'     => $groupName,
                        'employees' => [],
                        'divisions' => []
                    ];

                    $groupEmployees = $groupRows->filter(
                        fn($r) => is_null($r->division) && is_null($r->section) && is_null($r->unit)
                    );

                    $groupData['employees'] = $groupEmployees
                        ->sortBy('ItemNo')
                        ->map(fn($r) => $this->mapEmployee($r))
                        ->values();

                    $remainingGroupRows = $groupRows->reject(
                        fn($r) => is_null($r->division) && is_null($r->section) && is_null($r->unit)
                    );

                    foreach ($remainingGroupRows->groupBy('division') as $divisionName => $divisionRows) {
                        $divisionData = [
                            'division'  => $divisionName,
                            'employees' => [],
                            'sections'  => []
                        ];

                        $divisionEmployees = $divisionRows->filter(
                            fn($r) => is_null($r->section) && is_null($r->unit)
                        );

                        $divisionData['employees'] = $divisionEmployees
                            ->sortBy('ItemNo')
                            ->map(fn($r) => $this->mapEmployee($r))
                            ->values();

                        $remainingDivisionRows = $divisionRows->reject(
                            fn($r) => is_null($r->section) && is_null($r->unit)
                        );

                        foreach ($remainingDivisionRows->groupBy('section') as $sectionName => $sectionRows) {
                            $sectionData = [
                                'section'   => $sectionName,
                                'employees' => [],
                                'units'     => []
                            ];

                            $sectionEmployees = $sectionRows->filter(fn($r) => is_null($r->unit));

                            $sectionData['employees'] = $sectionEmployees
                                ->sortBy('ItemNo')
                                ->map(fn($r) => $this->mapEmployee($r))
                                ->values();

                            $remainingSectionRows = $sectionRows->reject(fn($r) => is_null($r->unit));

                            foreach ($remainingSectionRows->groupBy('unit') as $unitName => $unitRows) {
                                $sectionData['units'][] = [
                                    'unit'      => $unitName,
                                    'employees' => $unitRows
                                        ->sortBy('ItemNo')
                                        ->map(fn($r) => $this->mapEmployee($r))
                                        ->values()
                                ];
                            }

                            $divisionData['sections'][] = $sectionData;
                        }

                        $groupData['divisions'][] = $divisionData;
                    }

                    $office2Data['groups'][] = $groupData;
                }

                $officeData['office2'][] = $office2Data;
            }

            $result[] = $officeData;
        }

        return response()->json($result);
    }

    private function mapEmployee(stdClass $row)
    {
        return [
            'controlNo' => $row->ControlNo,
            'name'      => $row->name,
            'status' => $row->status,
            'position' => $row->position,
            'job_title' => $row->job_title,
            'office' => $row->office,

        ];
    }
}
