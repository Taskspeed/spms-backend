<?php

namespace App\Http\Controllers;

use App\Http\Requests\opcrRequest;
use App\Http\Resources\OpcrResource;
use App\Models\opcr;
use App\Models\Employee;
use App\Services\IpcrService;
use App\Services\OpcrService;
use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller as BaseController;
class OpcrController extends BaseController
{

    use ApiResponseTrait;

    protected ? Authenticatable $user = null;
    protected ? int $officeId = null; 

    protected OpcrService $opcrService;
    protected IpcrService $ipcr;

    
    public function __construct(IpcrService $ipcr, OpcrService $opcrService)
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $this->officeId = $this->user->office_id;

            return $next($request);
        });

        $this->opcrService = $opcrService;
        $this->ipcr = $ipcr;
    }

    // get the opcr of the Department Head
    public function opcr(string $controlNo, string $semester,int $year)
    {

        $employeeOpcr = $this->ipcr->opcrOfficeHead($controlNo, $semester, $year);

        if (!$employeeOpcr) {
           return $this->infoMessage('No record found',200);
        }

        return new OpcrResource([
            'employee'    => $employeeOpcr['employee'],
            'opcr_status' => $employeeOpcr['opcr_status'],
            'average_rating' => $employeeOpcr['average_rating'],
        ]);
    }

    // saving the opcr of the Department Head
    public function opcrStore(opcrRequest $request)
    {
        $validated = $request->validated();

        $opcr = $this->opcrService->storeAllotedBudget($validated);

        return response()->json($opcr);
    }


    // saving the opcr of the Department Head
    public function opcrUpdate(opcrRequest $request)
    {
        $validated = $request->validated();

        $opcr = $this->opcrService->updateAllotedBudget($validated);//

        return $this->successMessage($opcr, 'Opcr update successfully', 200);
    }
}
