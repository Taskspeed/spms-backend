<?php

namespace App\Http\Controllers\Erms;

use App\Http\Controllers\Controller;
use App\Http\Requests\performanceRatingStoreRequest;
use App\Http\Requests\UploadAttachmentRatingRequest;
use App\Http\Resources\TargetPeriodDetailsResource;
use App\Http\Resources\TargetPeriodRatingResource;
use App\Http\Resources\TargetPeriodRatingWeeksResource;
use App\Models\PerformanceRating;
use App\Models\TargetPeriod;
use App\Services\Erms\PerformanceRatingService;
use App\Services\TargetPeriodService;
use App\Traits\ApiResponseTrait;

class EmployeeRatingController extends Controller
{
    use ApiResponseTrait;

    // service
    protected TargetPeriodService $targetperiodService;
    protected  PerformanceRatingService $performanceRatingService;

    public function __construct(TargetPeriodService $targetperiodService,PerformanceRatingService $performanceRatingService)
    {
       $this->targetperiodService = $targetperiodService;
       $this->performanceRatingService = $performanceRatingService;
    }

    // target period of employee
    public function targetPeriodEmployee(string $controlNo)
    {

        $result = $this->targetperiodService->targetPeriod($controlNo);

        return $result;
    }

    //  get the target peroid details the performance standard and standard outcome
    public function targetPeriodDetails(int $targetPeriodId)
    {
        
        $result = $this->targetperiodService->targetPeriodDetails($targetPeriodId);
    
        return $result;
    
    }

    //  get the target peroid details the performance standard and standard outcome
    public function targetPeriod(int $targetPeriodId, $month = null, $year = null,$week= null)
    {

        $data = $this->targetperiodService->getTargetPeriodWithStandardsAndRatings($targetPeriodId,$month,$year,$week);

       return new TargetPeriodDetailsResource($data);
    }

    // employee store his rate
    public function performanceRating(performanceRatingStoreRequest $request)
    {
        $validated = $request->validated();

        $rating = $this->performanceRatingService->performanceRatingStore($validated);

        return response()->json([
            'status' => true,
            'message' => 'Rate(s) successfully saved',
            'rates' => $rating
        ]);
    }

    // get the list of the employee the rate of date
    public function getListOfRatingEmployee(string $controlNo)
    {

        $list = PerformanceRating::select(
            'id',
            'performance_standard_id',
            'control_no',
            'date'
        )
            ->where('control_no', $controlNo)
            ->orderBy('date', 'asc')
            ->get();

        if ($list->isEmpty()) {
            return response()->json([
                'message' => 'Employee does not have ratings yet'
            ], 404);
        }

        return response()->json($list, 200);
    }

    //performance rating record
    public function performanceRatingRecord(int $targetPeriodId)
    {

        $record = $this->targetperiodService->performanceRatingRecord($targetPeriodId);

        return $record;
    
    }

    //  get the target peroid rating
    public function targetPeriodRating(int $targetPeriodId, $month = null, $year = null)
    {
        $data = $this->targetperiodService->getTargetPeriodRatings($targetPeriodId, $month, $year);

        if (!$data) {
            return $this->errorMessage('Target period not found.', 404);
        }

        return new TargetPeriodRatingResource($data,$month,$year);
    }

    // uploading attachment for performance rating 
    public function uploadWeekAttachment(UploadAttachmentRatingRequest $request){

        $validatedData = $request->validated();


        $attachment = $this->targetperiodService->uploadAttachmentPerformanceRating($validatedData);

        return $this->successMessage( $attachment,'Attachment uploaded.',200);


    }

    //   //  get the target peroid rating
    public function targetPeriodRatingWeek(int $targetPeriodId, $month = null, $year = null)
    {
        $data = $this->targetperiodService->getTargetPeriodRatingWeeks($targetPeriodId, $month, $year);

        if (!$data) {
            return $this->errorMessage('Target period not found.', 404);
        }

        return new TargetPeriodRatingWeeksResource($data,$month,$year);
    }
    
}
