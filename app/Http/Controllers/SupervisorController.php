<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceRating;
use App\Models\RatingWeek;
use App\Models\TargetPeriod;
use App\Models\TargetPeriodRecord;
use App\Services\SupervisorService;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use function PHPUnit\Framework\returnCallback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupervisorController extends Controller
{
    //

    use ApiResponseTrait;

    protected SupervisorService $supervisorService;

    public function __construct(SupervisorService $supervisorService)
    {
        $this->supervisorService = $supervisorService;
    }

    // get the list of ipcr of my advisory
    public function getAdvisoryEmployeeIpcr(Request $request)
    {
        $supervisor = Auth::user();

        $year     = $request->input('year');
        $semester = $request->input('semester');

        // check if the supervisor is an Department Head
        $isOfficeHead = Employee::where('office_id', $supervisor->office_id)
            ->where('ControlNo', $supervisor->control_no)
            ->where('job_title', 'Department Head')
            ->exists();

        $query = TargetPeriod::select('id', 'control_no', 'year', 'semester', 'office_id', 'supervisory_control_no')
            ->where('office_id', $supervisor->office_id)
            ->where('year', $year)
            ->where('semester', $semester);

        if ($isOfficeHead) {
            // ✅ Department Head sees ALL employees in the office with Draft and Reviewed status
            $query->where('control_no', '!=', $supervisor->control_no) // ✅ exclude Department Head themselves
                ->whereHas('ipcrLastestRecord', function ($q) {
                    $q->whereIn(DB::raw('LOWER(status)'), ['Draft', 'Reviewed']); // ✅ lowercase
                });
        } else {
            // ✅ Supervisor only sees their advisory employees
            $query->where('supervisory_control_no', $supervisor->control_no)
                ->whereHas('ipcrLastestRecord', function ($q) {
                    $q->whereRaw("LOWER(status) = 'Draft'");
                });
        }

        $ipcr = $query->with(['employee:ControlNo,name', 'ipcrLastestRecord'])
            ->get()
            ->map(function ($item) {
                $item->name = $item->employee->name ?? null;
                unset($item->employee);

                return [
                    'ipcr_id'                => (int) $item->id,
                    'control_no'             => $item->control_no,
                    'year'                   => $item->year,
                    'semester'               => $item->semester,
                    'office_id'              => $item->office_id,
                    'supervisory_control_no' => $item->supervisory_control_no,
                    'name'                   => $item->name,
                    'ipcr_status'            => $item->ipcrLastestRecord->status ?? null,
                ];
            });

        if ($ipcr->isEmpty()) {
            return $this->infoMessage('No record found', 200);
        }

        return $this->successMessage($ipcr, 'Successfully fetch', 200);
    }
    // updating  ipcr status to reviewed
    public function updateIpcr(Request $request)
    {
        $supervisor = Auth::user();

        // ✅ check if the current user is an Department Head
        $isOfficeHead = Employee::where('office_id', $supervisor->office_id)
            ->where('ControlNo', $supervisor->control_no)
            ->where('job_title', 'Department Head')
            ->exists();

        $validated = $request->validate(
            [
                'ipcr_id'   => 'required|array',
                'ipcr_id.*' => 'required|exists:target_periods,id',
                'status'    => 'required|string',
                'remarks'   => 'nullable|string',
            ]
            // 'status.in' => "Status must be 'Reviewed' or 'Approved'.",
        );

        // block non-Department Head from using Approved status
        if ($validated['status'] === 'Approved' && ! $isOfficeHead) {
            return $this->errorMessage('Only the Department Head can Approve IPCR.', 403);
        }

        $records = [];

        foreach ($validated['ipcr_id'] as $ipcrId) {
            $records[] = TargetPeriodRecord::create([
                'target_period_id'  => $ipcrId,
                'date'              => Carbon::now()->format('Y-m-d'),
                'status'            => $validated['status'],
                'remarks'           => $validated['remarks'] ?? null,
                'processed_by'      => $supervisor->id,
                'processed_by_name' => $supervisor->name,
            ]);
        }

        return $this->successMessage($records, 'Successfully Updated', 200);
    }

    // get my supervisor and managerial
    public function getSupervisor(Request $request)
    {

        $user = Auth::user();

        if ($user->role_id != 4) {
            return response()->json([
                'message' => 'Unauthorized. Access restricted to authorized person only.'
            ], 403);
        }
        $year      = $request->input('year');
        $semester  = $request->input('semester');
        $controlNo = $user->control_no;

        $data =  $this->supervisorService->getListOfEmployeeBaseOnSupervisor($year, $semester, $controlNo, $user);

        return $this->successMessage($data, 'Successfully', 200);
    }

    // // updating performance rating of employee
    // public function updatePerformanceRatingEmployee(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'ratings'                            => 'required|array',
    //         'ratings.*.target_period_id'  => 'required|exists:target_periods,id',
    //         'ratings.*.week'                     => 'required|string',
    //         'ratings.*.status'                   => 'required|string',
    //     ]);

    //     foreach ($validatedData['ratings'] as $rating) {
    //         RatingWeek::updateOrCreate(
    //             [
    //                 'target_period_id' => $rating['target_period_id'],
    //                 'week'                    => $rating['week'],
    //             ],
    //             [
    //                 'status' => $rating['status'],
    //             ]
    //         );
    //     }

    //     return $this->successMessage('Performance rating status updated successfully.');
    // }

    public function updatePerformanceRatingEmployee(Request $request, int $performanceRatingId)
    {
        $validated = $request->validate([
             'status'  => ['required', 'string', Rule::in(['Approved', 'Disapproved'])],
             'remarks'     => 'nullable|string',
        ]);

        $rating  = PerformanceRating::find($performanceRatingId);

            if(!$rating){
                return $this->errorMessage('Rating not found',404);
            }
        $rating->update([
           'status'  => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
        ]);
  
        return $this->successMessage('Performance rating status updated successfully.');
    }
}
