<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Pmt\AccountEditRequest;
use App\Http\Requests\PmtCreateRequest;
use App\Http\Requests\SupervisorCreateRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
    use ApiResponseTrait;


    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {

        $this->authService = $authService;
    }

    // fetch user credentials
    public function userAccount()
    {
        $user = $this->authService->userAccount();

        return $user;
    }

    // user login
    public function login(LoginRequest $request)
    {

        $userLogin = $this->authService->login($request);

        return $userLogin;
    }

    // register account
    public function register(RegisterRequest $request)
    {
        $userRegister = $this->authService->register($request);

        return $userRegister;
    }

    // logout
    public function logout(Request $request)
    {
        $userLogout = $this->authService->logout($request);

        return $userLogout;
    }

    // Add this method to your AuthController.php
    public function changePassword(ChangePasswordRequest $request)
    {

        $userChangePassword = $this->authService->changePassword($request);

        return $userChangePassword;
    }

    // temporary password
    public function getTempPassword(Request $request)
    {
        $userGetTempPassword = $this->authService->getTempPassword($request);

        return $userGetTempPassword;
    }

    //  use edit and pmt account where they assign
    public function edit(AccountEditRequest $request)
    {
        $current_user = Auth::user();
        
        $validated = $request->validated();
        $userEdit = $this->authService->edit($validated, $current_user);

        return $userEdit;
    }

    //  excluded supervisor_admin
    public function adminRole()
    {

        $roles = Role::whereNotIn('name', ['supervisor_admin'])->get();

        return response()->json($roles);
    }

    // reset password for user
    public function resetPassword(int $userId)
    {

        $user = User::find($userId);

        $user->password = Hash::make('admin');
        $user->save();

        return response()->json($user);
    }

    // user account details
    public function viewDetailAccount(int $userId)
    {
        $user = User::with([
            'office',
            'role',
            'pmt_assign' => function ($query) {
                $query->select('id', 'user_id', 'office_id')
                    ->with(['office' => function ($q) {
                        $q->select('id', 'name');
                    }]);
            }
        ])->find($userId);

        if (empty($user)) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'User retrieved successfully.',
            'data' => $user,
        ], 200);
    }

    //create account supervisor admin
    public function createAccountSupervisor(SupervisorCreateRequest $request)
    {
        $validated = $request->validated();

        $userCreateAccountSupervisor = $this->authService->createAccountSupervisor($validated);

        return $userCreateAccountSupervisor;
    }

    //  supervisory_admin
    public function supervisoryRole()
    {

        $roles = Role::select('id as role_id', 'name')
            ->where('name', 'supervisor_admin')->get();


        if ($roles->isEmpty()) {
            return $this->infoMessage('No record foung', 200);
        }
        return $this->successMessage($roles, 'Fetch Successfully', 200);
    }


    // list of head account on the office
    public function headAccount()
    {
        $user = Auth::user();

        $account = User::where('role_id', 4)->where('office_id', $user->office_id)->get();

        if ($account->isEmpty()) {
            return $this->errorMessage('No data found', 404);
        }
        return $this->successMessage($account, 'Fetch Successfully', 200);
    }

    // delete user account
    public function userDelete(int $userId)
    {

        $user = User::find($userId);

        if (!$user) {
            return $this->errorMessage('User not found', 404);
        }

        $user->delete();

        return $this->successMessage($user, 'deleted successfully', 200);
    }

    // update the account of head account
    public function updateHeadAccount(Request $request)
    {
        $validated = $request->validate([
            'userId' => 'required|exists:users,id',
            'active' => 'required|boolean'
        ]);

        $userUpdateHead = $this->authService->updateHeadAccount($validated);

        return $userUpdateHead;
    }

    // create pmt account
    public function createPmtAccount(PmtCreateRequest $request)
    {

        $validated = $request->validated();

        // hash password
        $validated['password'] = Hash::make($validated['password']);

        $usercreatePmtAccount = $this->authService->createPmtAccount($validated);

        return $usercreatePmtAccount;
    }
}
