<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfficeOpcrPendingResource;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\User;
use App\Models\vwActive;
use App\Services\Hr\Pmt\PmtService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PmtController extends BaseController
{
    // response format
    use ApiResponseTrait;

    protected ?Authenticatable $user = null;
    protected PmtService $pmtService;

    public function __construct(PmtService $pmtService)
    {
        $this->pmtService = $pmtService;
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();


            return $next($request);
        });
    }

    // fetch the  list of office assign on the user pmt 
    public function office()
    {
        try {
            $data = $this->pmtService->getoffice($this->user);
            return $this->successMessage($data, 'Successfully fetched');
        } catch (\Exception $e) {
            return $this->errorMessage($e->getMessage());
        }
    }

    // fetch the list of employee ipcr
    public function listOfEmployeeIpcr(Request $request)
    {
        $year     = $request->input('year');
        $semester = $request->input('semester');
        $office   = $request->input('office');
       
        try{
            $data = $this->pmtService->EmployeeIpcr($this->user,$year,$semester,$office);
        return $this->successMessage($data, 'Successfully fetch');

        } catch (\Exception $e){
              return $this->errorMessage($e->getMessage());
        }
    }

    // list of the employee for pmt
    public function getOfficeEmployeePmt(Request $request)
    {
        $office = $request->query('office');
       try {
            $data = $this->pmtService->OfficeEmployeePmt($office);
            return $this->successMessage($data, 'Successfully fetched');
       } catch (\Exception $e) {
            return $this->errorMessage($e->getMessage());
       }
    }

          //list of the opcr draft
        // Controller
        public function listOfOpcrPmt(string $semester, int $year)
        {
            try {
                $result = $this->pmtService->opcr($semester, $year, $this->user);
            } catch (\Exception $e) {
                return $this->infoMessage($e->getMessage(), 200);
            }

            if ($result->isEmpty()) {
                return $this->infoMessage('No records found', 200);
            }

            return $this->successMessage(
                OfficeOpcrPendingResource::collection($result),
                'Successfully fetched',
                200
            );
        }

        public function listOfPmtMember(){

            $pmt = User::select('id','control_no','name','designation','pmt_type')->where('role_id',5)->get();

            return $this->successMessage($pmt,'List of Pmt member',200);

        }
}
