<?php

namespace App\Http\Controllers\office;


use App\Http\Controllers\Controller;

use App\Http\Requests\addEmployeeRequest;
use App\Models\Employee;
use App\Models\JobTitle;

use App\Models\User;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class EmployeeController extends Controller
{
    use ApiResponseTrait;

    // arg  data,message

    protected EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    // add an employee on the plantilla structure
    public function addEmployee(addEmployeeRequest $request,)
    {
        $validated = $request->validated();

        $employee = $this->employeeService->storeEmployees($validated);

        return $this->successMessage($employee, 'Employees created successfully', 200);
    }
    // }

    // //rank update of employee
    public function updateRank(Request $request, int $id) // need to check  this code for review

    {
        $validated = $request->validate([
            'rank' => 'required|string'
        ]);

        $employee = Employee::findOrFail($id); // vwEmployee

        // Check if this is a Head promotion
        if ($validated['rank'] === 'Head') {
            $query = Employee::where('office_id', $employee->office_id)
                ->where('rank', 'Head')
                ->where('id', '!=', $employee->id);

            // Check based on organizational level
            if ($employee->unit) {
                $query->where('unit', $employee->unit);
            } elseif ($employee->section) {
                $query->where('section', $employee->section)
                    ->whereNull('unit');
            } elseif ($employee->division) {
                $query->where('division', $employee->division)
                    ->whereNull('section')
                    ->whereNull('unit');
            } else {
                $query->whereNull('division')
                    ->whereNull('section')
                    ->whereNull('unit');
            }

            $existingHead = $query->first();

            if ($existingHead) {
                return response()->json([
                    'success' => false,
                    'message' => 'There is already a Head in this organizational unit'
                ], 422);
            }
        }

        $employee->rank = $validated['rank'];
        $employee->save();

        activity()
            ->performedOn($employee)
            ->causedBy(Auth::user())
            ->withProperties(['new_rank' => $validated['rank']])
            ->log('Employee rank updated');

        return response()->json([
            'success' => true,
            'message' => 'Employee rank updated successfully'
        ]);
    }


    //fetch of list of jobtitle
    public function fetchJobTitle()
    {
        $job = JobTitle::all();

        return $job;
    }

    //Jobtitle update of employee
    public function updateJobTitle(Request $request, string $controlNo) // need to check  this code for review

    {
        $validated = $request->validate([
            'job_title' => 'required|string'
        ]);

        $job = $this->employeeService->jobTitle($controlNo, $validated);

        return $job;
    }

    //rank
    public function updateRankv2(Request $request, string $controlNo)

    {
        $validated = $request->validate([
            'rank' => 'required|string'
        ]);

        $job = $this->employeeService->rank($controlNo, $validated);

        return $job;
    }

    //remove employee on the plantilla
    public function deleteEmployee(int $employeeId)
    {

        $employee = Employee::findOrFail($employeeId); // vwEmployee

        $employee->delete();

        return response()->json(
            [
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]
        );
    }

    // fetch employee
    public function getEmployee()
    {
        $user = Auth::user();

        $officeId = $user->office_id;

        $employees = Employee::where('office_id', $officeId)
            ->get();
        return response()->json($employees);
    }

    // fetch employee
    // public function getEmployee()
    // {
    //     $user = Auth::user();
    //     $officeId = $user->office_id;

    //     $office = office::find($officeId);

    //     if (!$office) {

    //         return response()->json(['message' => 'Office not found'], 404);
    //     }

    //     try {
    //         $employees = vwEmployee::where('office', $office->name) // palitan base sa tamang column
    //             ->get();

    //         return response()->json($employees);
    //     } catch (\Throwable $e) {

    //         return response()->json(['message' => 'Failed to fetch employees'], 500);
    //     }
    // }



    // fetch the employee base on the user office
    public function listOfEmployee(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->name) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or no office assigned.'
            ], 403);
        }

        try {

            $result = $this->employeeService->employee($request, $user);

            return response()->json([
                'success' => true,
                'data' => $result['employees'],
                'user_office' => $result['office_name']
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // search employee on the list of employee
    public function searchEmployee(Request $request)
    {

        $employee = $this->employeeService->onSearchEmployee($request);

        return response()->json([
            'success' => true,
            'data' => $employee
        ]);
    }


    // list of the employee supervisory on the office
    public function listOfHead()
    {
        $user = Auth::user();

        // Filter out null values to prevent SQL NOT IN null issue
        $existingControlNos = User::where('role_id', 4)
            ->whereNotNull('control_no')
            ->pluck('control_no')
            ->toArray();

        $employee = Employee::select('name', 'position', 'ControlNo', 'office', 'job_title', 'status') // vwEmployee
            ->where('office_id', $user->office_id)
            ->whereNotIn('job_title', ['Employee'])
            ->whereNotIn('ControlNo', $existingControlNos)
            ->get();

        if ($employee->isEmpty()) {
            return $this->errorMessage('No head employees found for this office.', 200);
        }

        return $this->successMessage($employee, 'Fetch employee successful');
    }

    // fetch list employee for signatories
    public function getEmployeeListSignatories()
    {
        $user = Auth::user();

        $officeId = $user->office_id;

        $employees = Employee::select(   // vwEmployee
            'ControlNo',
            'name',
            'rank',
            'job_title',
            'position',
            'suffix',
            'prefix'
        )->where('office_id', $officeId)->where('job_title', '!=', 'Employee')
            ->get();
        return response()->json($employees);
    }

    // update employee title and suffix
    public function updateEmployeeComponent(int $employeeId, Request $request)
    {
        $validatedData = $request->validate([
            'suffix' => 'nullable|string',
            'prefix' => 'nullable|string',
        ]);

        $employee = Employee::find($employeeId); // vwEmployee

        if (! $employee) {
            return $this->errorMessage('Employee not found', 404);
        }

        $employee->update($validatedData);

        return $this->successMessage($employee->fresh(), 'success update', 200);
    }





    // job title update of employee v2
    // public function updateJobTitlev2(Request $request)
    // {
    //     $validated = $request->validate([
    //         'job_title' => 'required|string',
    //         'controlNo' => 'required|string'
    //     ]);

    //     $job = $this->employeeService->updateJobTitlev2($validated);

    //     return $job;
    // }

    // // fetch employee
    // // fetch employee
    // public function getEmployeev3()
    // {
    //     $employees = EmployeeAssign::with(['employeeReAssign' => function ($query) {
    //         $query->select(
    //             'control_no as ControlNo',
    //             'office',
    //             'office2',
    //             'group',
    //             'division',
    //             'section',
    //             'unit',
    //             're_assign_date',
    //             'active',
    //             'position',
    //             'name'
    //         )->where('active', 1);
    //     }])
    //         ->select(
    //             'control_no as ControlNo',
    //             'office as Office',
    //             'office2',
    //             'group',
    //             'division',
    //             'section',
    //             'unit',
    //             'position',
    //             'name',

    //         )
    //         ->get();

    //     $plantillaStructure = vwplantillastructure::with(['employeeReAssign' => function ($query) {
    //         $query->select(
    //             'control_no as ControlNo',
    //             'office',
    //             'office2',
    //             'group',
    //             'division',
    //             'section',
    //             'unit',
    //             're_assign_date',
    //             'active',
    //             'position',
    //             'name',
    //         )->where('active', 1);
    //     }])
    //         ->select('ControlNo', 'Office', 'office2', 'group', 'division', 'section', 'unit', 'position', 'name4 as name', 'ItemNo', 'PageNo', 'ID as tblStructureID', 'PositionID')
    //         ->whereNotNull('ControlNo')
    //         ->get();

    //     $merged = $plantillaStructure->concat($employees)->map(function ($item) {
    //         $active = $item->employeeReAssign->first();

    //         if ($active) {
    //             return [
    //                 'ControlNo'      => $active->control_no,
    //                 'Office'         => $active->office,
    //                 'office2'        => $active->office2,
    //                 'group'          => $active->group,
    //                 'division'       => $active->division,
    //                 'section'        => $active->section,
    //                 'unit'           => $active->unit,
    //                 'position'       => $active->position,
    //                 'name'           => $active->name,
    //                 're_assign_date' => $active->re_assign_date,
    //                 'active'         => $active->active,
    //             ];
    //         }

    //         return [
    //             'ControlNo'      => $item->ControlNo,
    //             'Office'         => $item->Office,
    //             'office2'        => $item->office2,
    //             'group'          => $item->group,
    //             'division'       => $item->division,
    //             'section'        => $item->section,
    //             'unit'           => $item->unit,
    //             'position'       => $item->position,
    //             'name'           => $item->name,
    //             'itemNo'         => $item->ItemNo,
    //             'pageNo'         => $item->PageNo,
    //             'tblStructureID' => $item->tblStructureID,
    //             'PositionID'    => $item->PositionID,
    //             're_assign_date' => null,
    //             'active'         => null,
    //         ];
    //     })->values();

    //     // ============================================================
    //     // ATTACH Status / Grades FROM vwActive
    //     // ============================================================
    //     $controlNos = $merged->pluck('ControlNo')->filter()->unique()->values();

    //     $employeeStatus = vwActive::whereIn('ControlNo', $controlNos)
    //         ->select('ControlNo', 'Status', 'Grades')
    //         ->get()
    //         ->keyBy('ControlNo');

    //     $merged = $merged->map(function ($row) use ($employeeStatus) {
    //         $detail = $employeeStatus->get($row['ControlNo']);

    //         $row['Status'] = $detail->Status ?? null;
    //         $row['Grades'] = $detail->Grades ?? null;

    //         return $row;
    //     });

    //     // ============================================================
    //     // APPLY SG / SGLevel MAPPING — CASUAL EMPLOYEES ONLY
    //     // ============================================================
    //     $gradeMap = [
    //         'C1' => '10',
    //         'C2' => '11',
    //         'C3' => '12',
    //         'C4' => '13',
    //         'C5' => '14',
    //         'C6' => '15',
    //         'C7' => '16',
    //         'C8' => '17',
    //         'C9' => '18',
    //         'D1' => '11',
    //         'D2' => '12',
    //         'D3' => '13',
    //         'D4' => '14',
    //         'D5' => '15',
    //         'D6' => '16',
    //         'D7' => '17',
    //         'D8' => '18',
    //         'D9' => '19',
    //         'E1' => '21',
    //         'E2' => '22',
    //         'E3' => '23',
    //         'E4' => '24',
    //         'E5' => '25',
    //         'E6' => '26',
    //         'E7' => '27',
    //         'E8' => '28',
    //         'E9' => '29',
    //     ];

    //     $merged = $merged->map(function ($row) use ($gradeMap) {
    //         if ($row['Status'] === 'CASUAL' && !empty($row['Grades'])) {
    //             $grade = strtoupper(trim($row['Grades']));

    //             if (isset($gradeMap[$grade])) {
    //                 $row['SG'] = $gradeMap[$grade];
    //                 $row['SGLevel'] = ((int) $row['SG'] <= 10) ? '1' : '2';
    //             }
    //         }

    //         return $row;
    //     });

    //     return response()->json($merged);
    // }
}
