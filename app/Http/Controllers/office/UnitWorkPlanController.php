<?php


namespace App\Http\Controllers\office;

use App\Http\Requests\addEmployeeUnitWorkPlanRequest;
use App\Http\Requests\updateEmployeeUnitWorkPlanRequest;
use App\Http\Resources\UnitWorkPlanOrganizationResource;
use App\Http\Resources\UnitWorkPlanResource;
use App\Models\Employee;

use App\Services\UnitWorkPlanService;

use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;


class UnitWorkPlanController extends BaseController
{
    use ApiResponseTrait;
 
    protected ?Authenticatable $user = null;
    protected ?int $officeId = null;
    protected  UnitWorkPlanService $unitWorkPlanService;

    public function __construct(UnitWorkPlanService $unitWorkPlanService)
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $this->officeId = $this->user?->office_id;

            return $next($request);
        });

        $this->unitWorkPlanService = $unitWorkPlanService;
    }

    // storing unit work plan
    // storing unit work plan
    public function addUnitWorkPlan(addEmployeeUnitWorkPlanRequest $request)
    {
        $validated = $request->validated();

        try {
            $unitworkplan = $this->unitWorkPlanService->store($validated);

            return $this->successMessage($unitworkplan, 'Unit Work Plans for all employees created successfully.', 201);
            
        } catch (\Exception $e) {
            return $this->errorMessage('Failed to create Unit Work Plan.', 500);
        }
    }


    // updating unit work plan
    // args controlno, semester, year
    public function updateUnitWorkPlan(updateEmployeeUnitWorkPlanRequest $request)
    {

        $validated = $request->validated();

        $unitworkplan = $this->unitWorkPlanService->update($validated);

        return $this->successMessage($unitworkplan, 'Unit Work Plan updated successfully.', 200);
    }



    // find employee
    public function findEmployee(string $controlNo)
    {
        $employee = Employee::where('ControlNo', $controlNo)->with(['targetPeriods'])->first();


        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.'
            ], 404);
        }

        return $this->successMessage($employee, 'Employee found successfully.', 200);
    }

    
    // view the unitworkplant of the employee based on controlno , semester and year
    public function getUnitworkplan(string $controlNo, string $semester, int $year)
    {
        $employee = Employee::where('ControlNo', $controlNo)
            ->with([
                'targetPeriods' => function ($q) use ($year, $semester) {
                    $q->select('id', 'control_no', 'year', 'semester',)
                        ->where('year', $year)
                        ->where('semester', $semester)
                        ->with([
                            'performanceStandards.configurations',
                            'performanceStandards.standardOutcomes',
                        ]);
                }
            ])
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        return new UnitWorkPlanResource($employee);
    }

    // delete the unit work plan of the employee based on semester and year
    public function deleteUnitWorkPlan(string $controlNo, string $semester, int $year)
    {
        // ✅ STEP 1: Find employee with office restriction
        $employee = Employee::where('ControlNo', $controlNo)
            ->where('office_id', $this->officeId)
            ->firstOrFail();

        if (!$employee) {
            return $this->errorMessage('Employee not found or access denied', 404);
        }

        // ✅ STEP 2: Find target period (Unit Work Plan)
        $targetPeriod = $employee->targetPeriods()
            ->where('year', $year)
            ->where('semester', $semester)
            ->first();

        if (!$targetPeriod) {
            return $this->errorMessage('Unit Work Plan not found', 404);
        }

        // ✅ STEP 3: Delete target period
        $targetPeriod->delete();

        return $this->successMessage($targetPeriod, 'Unit Work Plan deleted successfully', 200);
    }

    //  get the organization of the office - division - section - unit
    public function getUniWorkPlanOfficeOrganization(Request $request)
    {

        $request->validate([
            'office_name' => 'required|string',
            'organization' => 'required|string',
            'semester' => 'required',
            'year' => 'required',
        ]);

        try {
            $organization = $this->unitWorkPlanService->organization($request);
            return new UnitWorkPlanOrganizationResource($organization);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ]);
        }
    }

    // find the Department Head and supervisory on office
    public function findManagerial(int $year, string $semester, string $mfo)
    {

        $result = $this->unitWorkPlanService->supervisoryDeductionOfSuccessIndicator($year, $semester, $mfo);

        return $result;
    }

}
