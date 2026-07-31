<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListOfEmployeeDraftRatingResource;
use App\Models\office;
use App\Models\UserOfficeAssign;
use App\Services\OfficeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OfficeController extends Controller

{

    protected OfficeService $officeService;

    public function __construct(OfficeService $officeService)
    {

        $this->officeService = $officeService;
    }

    // getting the all office on the office table but need to change on the vwofficearrangement
    public function getOffices()
    {

        $data = DB::table('offices')->select('id', 'name')->get();

        return response()->json($data);
    }

    // fetch available office on the pmt
    public function pmtOfficeAvailable()
    {
        // Get all office IDs already assigned in user_office_assigns
        $assignedOfficeIds = UserOfficeAssign::pluck('office_id')->unique()->toArray();

        // Fetch only offices NOT in the assigned list
        $data = DB::table('offices')
            ->select('id', 'name')
            ->whereNotIn('id', $assignedOfficeIds)
            ->get();

        return response()->json($data);
    }

    // office structure
    public function officeStructure()
    {

        $officeStructure = $this->officeService->structure();

        return response()->json([$officeStructure]);
    }

    public function listOfEmployeeRatingDraft(string $semester, int $year)
    {

        $data = $this->officeService->employeeRatingDraft($semester, $year);
        return $data;
    }
}
