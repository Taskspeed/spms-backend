<?php

namespace App\Http\Controllers\office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\OutputRequest;

use App\Http\Requests\Library\OutputUpdateRequest;
use App\Models\F_outpot;
use App\Services\OutputService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Console\Output\Output;

class FOutpotController extends Controller
{
    use ApiResponseTrait;
    
    protected OutputService $outputService;

    public function __construct( OutputService $outputService)
    {
        $this->outputService = $outputService;
    }

    // storing output
    public function addOutput(OutputRequest $request)
    {
        $validated = $request->validated();

        $output = $this->outputService->store($validated);

        return $this->successMessage($output, 'Output created successfully', 201);
    }

    // update output
    public function updateOutput(OutputUpdateRequest $request, int $id)
    {

        $validated = $request->validated();

        $output = $this->outputService->update($validated, $id);

        return $this->successMessage($output, 'Output updated successfully', 200);
    }

    // Delete for outputs
    public function deleteOutput(int $id)
    {
        $output = F_outpot::findOrFail($id); // Ensure the model use
        $output->delete();

        activity()
            ->performedOn($output)
            ->causedBy(Auth::user())
            ->withProperties(['name' =>  $output->name])
            ->log('Output deleted');

      return $this->successMessage(null, 'Output deleted successfully', 200);
    }



}
