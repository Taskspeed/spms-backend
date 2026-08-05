<?php

namespace App\Services;

use App\Events\OpcrEvent;
use App\Models\Employee;
use App\Models\OfficeOpcr;
use App\Models\OfficeOpcrRecord;
use App\Models\opcr;
use function Symfony\Component\Clock\now;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpcrService
{

    // store opcr
    public function storeAllotedBudget(?array $validatedData)
    {
        $user = Auth::user();
        $officeId = $user->office_id;

        DB::beginTransaction();

        try {

            $records = [];

            foreach ($validatedData['data'] as $data) {

                $records[] = opcr::create([
                    'office_id' => $officeId,
                    'performance_standard_id' => $data['performance_standard_id'],
                    'budget' => $data['budget'],
                    'accountable' => $data['accountable'],
                    // 'accomplishment' => $data['accomplishment'],
                ]);
            }

            DB::commit();

            //Execute
            // dispatch event
            OpcrEvent::dispatch(
                $records,
                $validatedData['year'],
                $validatedData['semester'],
                $user
            );


            return $records;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // update AllotedBudget
    public function updateAllotedBudget(?array $validatedData)
    {
        $user = Auth::user();
        $officeId = $user->office_id;

        DB::beginTransaction();

        try {
            $records = [];

            foreach ($validatedData['data'] as $data) {
                $record = opcr::updateOrCreate(
                    [
                        'office_id' => $officeId,
                        'performance_standard_id' => $data['performance_standard_id'],

                    ],
                    [
                        'budget' => $data['budget'],
                        'accountable' => $data['accountable'],
                        'accomplishment' => $data['accomplishment'] ?? null,//
                    ]

                );

                $records[] = $record;
            }

            DB::commit();
            return $records;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    // list of  opcr Received
    public function opcrReceived(string $semester, int $year)
    {

        // opcr of office
        $data = OfficeOpcr::select(
            'office_opcrs.id',
            'office_opcrs.office_id',
            'office_opcrs.office_name', // add your fields here
            'office_opcrs.semester',
            'office_opcrs.year'
        )->with([
            'officeOpcrRecordLastestRecord' => function ($query) {
                $query->select(
                    'office_opcrs_records.id',
                    'office_opcrs_records.office_opcr_id',
                    'office_opcrs_records.date',
                    'office_opcrs_records.status'
                );
            }, // eager load Department Head per office
            'officeHead' => function ($query) {
                $query->select(
                    'employees.id',
                    'employees.office_id',
                    'employees.name',
                    'employees.job_title',
                    'employees.ControlNo'
                );
            },

        ])
            ->where('semester', $semester)
            ->where('year', $year)
            ->whereHas('officeOpcrRecordLastestRecord', function ($query) {
                $query->whereIn('status', ['Received Target', 'Reviewed Target', 'Returned Target',]);
            })->get();

        return $data;
    }
}
