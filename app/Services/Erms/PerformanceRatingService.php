<?php

namespace App\Services\Erms;

use App\Models\PerformanceRating;
use Illuminate\Support\Facades\DB;

class PerformanceRatingService
{

    // store performance rating of employee
    public function performanceRatingStore(array $validated)
    {
        $saveRates = [];

        DB::transaction(function () use ($validated, &$saveRates) {

            foreach ($validated['performance_rate'] as $rateData) {

                // Extract dropdown before unsetting
                $dropdownData = $rateData['dropdown'];

                unset($rateData['dropdown']);

                $rateData['performance_standard_id'] = $rateData['performance_standards'];
                unset($rateData['performance_standards']);

                // Save parent
                // $performanceRating = PerformanceRating::create($rateData);
                $performanceRating = PerformanceRating::create(array_merge($rateData, [
                    'status' => 'Pending',
                ]));

                // Save children
                foreach ($dropdownData as $dropdown) {
                    $performanceRating->dropdownRating()->create($dropdown);
                }

                $saveRates[] = $performanceRating->load('dropdownRating');
            }
        });

        return $saveRates;
    }
}
