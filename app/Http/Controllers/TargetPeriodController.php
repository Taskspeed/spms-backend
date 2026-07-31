<?php

namespace App\Http\Controllers;

use App\Events\TargetPeriodLockEvent;
use App\Http\Requests\Library\TargetPeriodStoreRequest;
use App\Http\Requests\Library\TargetPeriodUpdateRequest;
use App\Models\TargetPeriodLib;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TargetPeriodController extends Controller
{
    use ApiResponseTrait;

      public function getTargetPeriods()
    {
        $targetPeriod = TargetPeriodLib::select('id','year','semester')->get();

        return $this->successMessage($targetPeriod, 'Target periods fetched successfully', 200);
    }

    // store target period
    public function storeTargetPeriod(TargetPeriodStoreRequest $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $validated = $request->validated();

        $targetPeriod = TargetPeriodLib::create($validated);

        // TargetPeriodLockEvent::dispatch($targetPeriod, $user);

        return $this->successMessage($targetPeriod, 'Target period created successfully', 201);
    }

    // updating target period
    public function updateTargetPeriod(TargetPeriodUpdateRequest $request ,int $targetPeriodId)
    {
        $validated = $request->validated();

        $targetPeriod = TargetPeriodLib::findOrFail($targetPeriodId);

        $targetPeriod->update($validated);

        return $this->successMessage($targetPeriod, 'Target period updated successfully', 200);
    }

    // delete target period
    public function deleteTargetPeriod(int $targetPeriodId)
    {
        $targetPeriod = TargetPeriodLib::findOrFail($targetPeriodId);

        $targetPeriod->delete();

        return $this->successMessage(null, 'Target period deleted successfully', 200);
    }
}
