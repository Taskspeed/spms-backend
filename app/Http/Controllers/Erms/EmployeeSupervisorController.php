<?php

namespace App\Http\Controllers\Erms;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeSupervisorService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class EmployeeSupervisorController extends Controller
{
    // response format
    use ApiResponseTrait;

    protected EmployeeSupervisorService $employeeSupervisorService;


    // services
    public function __construct(EmployeeSupervisorService $employeeSupervisorService)
    {
        $this->employeeSupervisorService = $employeeSupervisorService;
    }

    // get employee information
    public function employeeInformation(string $controlNo)
    {
        $employee = Employee::select('id', 'ControlNo', 'name', 'job_title', 'status')
            ->where('ControlNo', $controlNo)
            ->first();

        if (!$employee) {
            return $this->successMessage(
                ['spms' => false],
                'The employee does not exist in the SPMS.',
                200
            );
        }

        $allowedStatuses = ['CASUAL', 'REGULAR', 'CO-TERMINOUS'];

        if (!in_array($employee->status, $allowedStatuses)) {
            return $this->successMessage(
                ['spms' => false],
                'The employee is not CASUAL, REGULAR, or CO-TERMINOUS.',
                200
            );
        }

        $data = $employee->only(['id', 'ControlNo', 'name', 'job_title']);
        $data['spms'] = true;

        return $this->successMessage($data, 'Successfully fetch', 200);
    }

    // get my Supervisor and managerial
    public function getMySupervisor(Request $request)
    {

        $controlNo     = $request->input('controlNo');
        $year     = $request->input('year');
        $semester     = $request->input('semester');


        try {
            $result = $this->employeeSupervisorService->getListOfEmployeeBaseOnSupervisor($year, $semester, $controlNo);
            return $this->successMessage($result, 'Successfully', 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
