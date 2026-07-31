<?php

namespace App\Http\Controllers\office;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\MfoStoreRequest;
use App\Http\Requests\Library\MfoUpdateRequest;
use App\Models\F_category;
use App\Models\mfo;
use App\Models\User; // Ensure this is the Eloquent User model
use App\Services\MfoService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MfoController extends Controller
{

    use ApiResponseTrait;

    protected MfoService $mfoService;

    public function __construct(MfoService $mfoService)
    {
        $this->mfoService = $mfoService;
    }

    // getting the mfo  of the user
    public function Mfo()
    {

        $user = $this->mfoService->getUserMfo();

        return response()->json($user);
    }

    // addMfo
    public function addMfo(MfoStoreRequest $request)  // store
    {
        $validated = $request->validated();

        $mfo = $this->mfoService->store($validated);

        activity()
            ->performedOn($mfo)
            ->causedBy(Auth::user())
            ->withProperties(['name' => $mfo->name])
            ->log('MFO Created');

        return $this->successMessage($mfo, 'MFO created successfully', 201);
    }

    // update mfo
    public function updateMfo(MfoUpdateRequest $request, int $id) // update
    {
        $validated = $request->validated();

        $mfo = $this->mfoService->update($id, $validated);

        return $this->successMessage($mfo, 'MFO updated successfully', 200);
    }

    // Delete for MFO
    public function delete(int $id)
    {

        $mfos = mfo::findOrFail($id);
        $mfos->delete();

        activity()
            ->performedOn($mfos)
            ->causedBy(Auth::user())
            ->withProperties(['name' =>   $mfos->name])
            ->log('MFO soft deleted');

        return $this->successMessage(null, 'MFO soft deleted successfully', 200);
    }

    // fetch all mfo of Department Head
    public function fetchMfo(string $semester, int $year)
    {
        $mfo = $this->mfoService->getMfo($semester, $year);
        return $mfo;
    }
}
