<?php

use App\Http\Controllers\Activity_log_Controller;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Erms\EmployeeRatingController;
use App\Http\Controllers\Erms\EmployeeSupervisorController;
use App\Http\Controllers\Erms\ErmsMfoController;
use App\Http\Controllers\Erms\ErmsUnitWorkPlanController;
use App\Http\Controllers\Erms\TargetperiodController as ErmsTargetperiodController;
use App\Http\Controllers\Hr\dashboardController;
use App\Http\Controllers\Hr\IndicatorController;
use App\Http\Controllers\Hr\PmtController;
use App\Http\Controllers\Hr\PositionRankController;
use App\Http\Controllers\Hr\RankController;
use App\Http\Controllers\Hr\ReceivingController as HrReceivingController;
use App\Http\Controllers\Hr\SpmsController as HrSpmsController;
use App\Http\Controllers\Hr\UnitWorkPlanController as HrUnitWorkPlanController;
use App\Http\Controllers\office\DashboardController as OfficeDashboardController;
use App\Http\Controllers\office\EmployeeController;
use App\Http\Controllers\office\FOutpotController;
use App\Http\Controllers\office\IpcrController;
use App\Http\Controllers\office\MfoController;
use App\Http\Controllers\office\QpefController;
use App\Http\Controllers\office\UnitWorkPlanController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OpcrController;
use App\Http\Controllers\Planning\DashboardController as PlanningDashboardController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\SpmsController;
use App\Http\Controllers\SpmsProcessController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\TargetPeriodController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\vwActiveController;
use App\Http\Controllers\VwplantillastructureController;
use App\Models\PositionRank;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| IPCR Routes (Public)
|--------------------------------------------------------------------------
*/

Route::prefix('ipcr')->group(function () {
    Route::get('/employee/{ControlNo}/{year}/{semester}',           [IpcrController::class, 'getIpcrEmployee']);
    Route::get('/performance-standard/{targerperiodId}',           [IpcrController::class, 'getPerformanceStandard']);
    Route::get('/monthly-performance/{targerperiodId}',            [IpcrController::class, 'getMonthlyEmployee']);
    Route::get('/summary-monthly-performance/{targerperiodId}',    [IpcrController::class, 'getSummaryMonthlyEmployee']);
    Route::post('/attendance',                                      [IpcrController::class, 'attendance']);
});

/*
|--------------------------------------------------------------------------
| ERMS Routes (Public)
|--------------------------------------------------------------------------
*/

Route::get('employee/list/rated/{control_no}', [EmployeeRatingController::class, 'getListOfRatingEmployee']);

Route::prefix('erms')->group(function () {
    Route::get('employee/supervisor',                                               [EmployeeSupervisorController::class, 'getMySupervisor']);
    Route::get('employee/{controlNo}',                                              [EmployeeSupervisorController::class, 'employeeInformation']);
    Route::get('employee/target-periods/{controlNo}',                               [EmployeeRatingController::class,    'targetPeriodEmployee']);
    Route::get('employee/target-periods/details/{targetperiodId}',                  [EmployeeRatingController::class,    'targetPeriodDetails']);
    Route::get('employee/list/rated/{control_no}',                                  [EmployeeRatingController::class,    'getListOfRatingEmployee']);
    Route::get('employee/performance-record/{targetPeriodId}',                      [EmployeeRatingController::class,    'performanceRatingRecord']);
    Route::get('/employee/target-periods/details/{targetperiodId}/{month}/{year}/{week}', [EmployeeRatingController::class, 'targetPeriod']);
    Route::get('/employee/target-periods/rating/{targetperiodId}/{month}/{year}',   [EmployeeRatingController::class,    'targetPeriodRating']);
    Route::get('/employee/target-periods/rating/weeks/{targetperiodId}/{month}/{year}',   [EmployeeRatingController::class,    'targetPeriodRatingWeek']);

    Route::post('employee/store/rating',                                            [EmployeeRatingController::class,    'performanceRating']);
    Route::get('/target-period',                                                    [ErmsTargetperiodController::class,  'lastestTargetPeriods']);
    Route::get('/mfo/{officeId}',                                                   [ErmsMfoController::class,           'getMfoErms']);
    Route::get('/head-mfo/{semester}/{year}/{officeId}',                            [ErmsMfoController::class,           'officeMfo']);
    Route::get('/managerial/{year}/{semester}/{mfo}/{officeId}',                    [ErmsUnitWorkPlanController::class,  'findManagerial']);
    // Route::post('upload/attachment/performance-rating',                    [EmployeeRatingController::class,  'uploadWeekAttachment']);

});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------------------------------------------------------
    | MFO & Output
    |----------------------------------------------------------------------
    */

    Route::post('/add_mfo',         [MfoController::class,     'addMfo']);
    Route::get('/mfos',             [MfoController::class,     'index']);
    Route::post('/mfos/{id}',       [MfoController::class,     'updateMfo']);
    Route::delete('/mfos/{id}',     [MfoController::class,     'delete']);
    Route::post('/add_output',      [FOutpotController::class, 'addOutput']);
    Route::post('/outputs/{id}',    [FOutpotController::class, 'updateOutput']);
    Route::delete('/outputs/{id}',  [FOutpotController::class, 'deleteOutput']);

    Route::get('/user_activity_log', [Activity_log_Controller::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Office
    |----------------------------------------------------------------------
    */

    Route::prefix('office')->group(function () {
        Route::get('/',                     [OfficeController::class,            'getOffices']);
        Route::get('/structure',            [OfficeController::class,            'officeStructure']);
        Route::get('/structure/number',     [OfficeController::class,            'officeStructure']);
        Route::get('/structure/count',      [VwplantillastructureController::class, 'plantillaStructureEmployee']);
         Route::get('/structure/test',      [VwplantillastructureController::class, 'testplantilla']);
        Route::get('/mfo',                  [MfoController::class,               'Mfo']);
        Route::get('/head-mfo/{semester}/{year}', [MfoController::class,         'fetchMfo']);
        Route::get('/employee-draft-rating/{semester}/{year}', [OfficeController::class, 'listOfEmployeeRatingDraft']);
        Route::get('/pmt/available',        [OfficeController::class,            'pmtOfficeAvailable']);
        Route::get('/ipcr',                 [IpcrController::class,              'listIpcr']);

        Route::prefix('dashboard')->group(function () {
            Route::get('/',                     [OfficeDashboardController::class, 'dashboardStatus']);
            Route::get('/employee/without-ipcr',[OfficeDashboardController::class, 'listOfEmployeeNoIpcr']);
        });
    });

    /*
    |----------------------------------------------------------------------
    | User
    |----------------------------------------------------------------------
    */

    Route::prefix('user')->group(function () {
        Route::get('/',                             [UserController::class, 'getUserData']);
        Route::post('/register',                    [AuthController::class, 'register']);
        Route::post('/logout',                      [AuthController::class, 'logout']);
        Route::post('/update/credentials',          [AuthController::class, 'changePassword']);
        Route::get('/account',                      [AuthController::class, 'userAccount']);
        Route::post('/edit',                        [AuthController::class, 'edit']);
        Route::delete('/delete/{userId}',           [AuthController::class, 'userdelete']);
        Route::get('/role',                         [AuthController::class, 'adminRole']);
        Route::get('/supervisor-role',              [AuthController::class, 'supervisoryRole']);
        Route::post('/reset-password/{userId}',     [AuthController::class, 'resetPassword']);
        Route::get('/view/account/{userId}',        [AuthController::class, 'viewDetailAccount']);
        Route::post('supervisory',                  [AuthController::class, 'createAccountSupervisor']);
        Route::get('/head-account',                 [AuthController::class, 'headAccount']);
        Route::post('/update/head-account',         [AuthController::class, 'updateHeadAccount']);
        Route::post('/create/pmt/account',          [AuthController::class, 'createPmtAccount']);
    });

    /*
    |----------------------------------------------------------------------
    | HR
    |----------------------------------------------------------------------
    */

    Route::prefix('hr')->group(function () {

        Route::prefix('dashboard')->group(function () {
      
            Route::get('/current/target-period',[dashboardController::class, 'currentTargetPeriod']);
            Route::get('/plantilla',            [dashboardController::class, 'plantillaEmployee']);
            Route::get('/employee',             [dashboardController::class, 'dashboardSummaryData']);
        });

        Route::prefix('unit-work-plan')->group(function () {
            Route::post('/update-status', [HrUnitWorkPlanController::class, 'updateUnitWorkPlan']);
        });

        Route::prefix('target-period')->group(function () {
            Route::post('/update-status', [HrUnitWorkPlanController::class, 'lockTargetPeriod']);
        });

        Route::prefix('receiving')->group(function () {
            Route::get('/ipcr',         [HrReceivingController::class, 'getApproveIpcr']);
            Route::get('/unitworkplan', [HrReceivingController::class, 'getUnitworkplan']);
            Route::get('/qpef', [HrReceivingController::class, 'getAllQpef']);

        });

        Route::prefix('indicator')->group(function(){
            Route::get('/',                        [IndicatorController::class, 'getIndicator'])->withoutMiddleware(['auth:sanctum']);
            Route::post('/store',                 [IndicatorController::class, 'storeIndicator']);
            Route::put('/update/{indicatorId}',   [IndicatorController::class, 'updateIndicator']);
            Route::delete('/delete/{indicatorId}',[IndicatorController::class, 'deleteIndicator']);
        });

        Route::prefix('rank')->group( function(){
        Route::get('/',                     [RankController::class, 'getRank']);
        Route::post('/store',              [RankController::class, 'storeRank']);
        Route::put('/update/{rankId}',     [RankController::class, 'updateRank']);
        Route::delete('/delete/{rankId}',  [RankController::class, 'deleteRank']);

        });
        Route::prefix('position')->group( function(){
        Route::get('/',                     [PositionRankController::class, 'getPositionRank']);
        Route::post('/store',              [PositionRankController::class, 'storePositionRank']);
        Route::put('/update/{positionRankId}',     [PositionRankController::class, 'updatePositionRank']);
        Route::delete('/delete/{positionRankId}',  [PositionRankController::class, 'deletePositionRank']);

        });

        Route::get('/category', [CategoryController::class, 'fetchCategory']);

        Route::prefix('spms')->group(function(){
            Route::get('/ipcr',            [HrSpmsController::class, 'listOfIpcr']);
            Route::get('/opcr',            [HrSpmsController::class, 'listOfOpcr']);
            Route::get('/unit-work-plan',  [HrSpmsController::class, 'listOfUnitWorkPlan']);
        });
    });

    /*
    |----------------------------------------------------------------------
    | Planning
    |----------------------------------------------------------------------
    */

    Route::prefix('planning')->group(function () {

        Route::prefix('dashboard')->group(function () {
            Route::get('/status/{semester}/{year}', [PlanningDashboardController::class, 'numberOfStatus']);
        });

        Route::prefix('opcr')->group(function () {
            Route::get('/list-received-opcr/{semester}/{year}', [PlanningDashboardController::class, 'listOfOpcrReceived']);
        });

        Route::prefix('receiving')->group(function () {
            Route::get('/list-pending-opcr/{semester}/{year}', [PlanningDashboardController::class, 'listOfOpcrPending']);
        });
    });

    /*
    |----------------------------------------------------------------------
    | SPMS
    |----------------------------------------------------------------------
    */

    Route::prefix('spms')->group(function () {
        Route::get('/employees-requested',              [SpmsController::class,        'getEmployeeRequested']);
        Route::get('/fetch_employees',                  [SpmsController::class,        'getEmployees']);
        Route::get('/office/structure',                 [SpmsController::class,        'officePlantilla']);
        Route::get('/target_periods/semester-year',     [SpmsController::class,        'getTargetPeriodsSemesterYear']);
        Route::get('/employee/{ControlNo}/{semester}/{year}', [UnitWorkPlanController::class, 'getUnitworkplan'])->withoutMiddleware(['auth:sanctum']);
        Route::post('/update/unitworkplan',             [SpmsProcessController::class, 'updateUnitworkplan']);
        Route::post('/update/opcr',                     [SpmsProcessController::class, 'updateOpcr']);
        Route::post('/update/ipcr',                     [SpmsProcessController::class, 'updateIpcr']);
        Route::post('/unit-workplan/sync-ipcr-opcr',    [SpmsProcessController::class, 'syncUnitWorkPlanIpcrOpcr']);
    });

    /*E
    |----------------------------------------------------------------------
    | Unit Work Plan
    |----------------------------------------------------------------------
    */

    Route::prefix('unit_work_plan')->group(function () {
        Route::get('/managerial/{year}/{semester}/{mfo}', [UnitWorkPlanController::class, 'findManagerial']);
        Route::post('/',        [UnitWorkPlanController::class, 'getUniWorkPlanOfficeOrganization']);
        Route::post('/store',   [UnitWorkPlanController::class, 'addUnitWorkPlan'])->withoutMiddleware(['auth:sanctum']);
        Route::post('/update',  [UnitWorkPlanController::class, 'updateUnitWorkPlan'])->withoutMiddleware(['auth:sanctum']);
        Route::delete('/delete/{controlNo}/{semester}/{year}', [UnitWorkPlanController::class, 'deleteUnitWorkPlan']);
        Route::delete('/delete/performance-standard/{performanceStandardId}', [UnitWorkPlanController::class, 'deletePerformanceStandard']);

    });

    /*
    |----------------------------------------------------------------------
    | Employee
    |----------------------------------------------------------------------
    */

    Route::prefix('employee')->group(function () {
        Route::get('/',                         [EmployeeController::class,  'getEmployee']);
        Route::get('/office-employee',          [vwActiveController::class,  'getOfficeEmployee']);
        Route::get('/by-office',                [EmployeeController::class,  'listOfEmployee']);
        Route::post('/store',                   [EmployeeController::class,  'addEmployee']);
        Route::post('/rank/{id}',               [EmployeeController::class,  'updateRank']);
        Route::post('/title/{controlNo}',      [EmployeeController::class,  'updateJobTitle']);
        Route::get('/search',                   [EmployeeController::class,  'searchEmployee']);
        Route::delete('/delete/{id}',           [EmployeeController::class,  'deleteEmployee']);
        Route::get('/list-of-Head',             [EmployeeController::class,  'listOfHead']);
        Route::get('/head',                     [SpmsController::class,      'getEmployeeUnderOfHead']);
        Route::get('/signatory',                         [EmployeeController::class,  'getEmployeeListSignatories']);
        Route::put('/component/{employeeId}',                         [EmployeeController::class,  'updateEmployeeComponent']);
        Route::get('/{controlNo}',              [UnitWorkPlanController::class, 'findEmployee']);
    });




    /*
    |----------------------------------------------------------------------
    | Target Period Library
    |----------------------------------------------------------------------
    */

    Route::prefix('targetPeriod')->group(function () {
        Route::get('/',                             [TargetPeriodController::class, 'getTargetPeriods']);
        Route::post('/store',                       [TargetPeriodController::class, 'storeTargetPeriod']);
        Route::put('/update/{targetPeriodId}',      [TargetPeriodController::class, 'updateTargetPeriod']);
        Route::delete('/delete/{targetPeriodId}',   [TargetPeriodController::class, 'deleteTargetPeriod']);
    });

    /*
    |----------------------------------------------------------------------
    | QPEF
    |----------------------------------------------------------------------
    */

    Route::prefix('qpef')->group(function () {
        Route::get('/all-quarter/{control_no}/{year}',  [QpefController::class, 'employeeQpefAllQuarter']);
        Route::get('/{control_no}/{quarterly}/{year}',  [QpefController::class, 'employeeQpef']);
        Route::post('/employee/quarter',                [QpefController::class, 'getAllEmployeeQpefQuater']);
        Route::post('/store',                           [QpefController::class, 'qpefStore']);
        Route::put('/update/{qpefId}',                  [QpefController::class, 'qpefUpdate']);
        Route::put('/update/status/{qpefId}',                  [QpefController::class, 'updateQpef']); 
    });

    /*
    |----------------------------------------------------------------------
    | OPCR
    |----------------------------------------------------------------------
    */

    Route::prefix('opcr')->group(function () {
        Route::get('/{controlNo}/{semester}/{year}', [OpcrController::class, 'opcr']);
        Route::post('/store',                        [OpcrController::class, 'opcrStore']);
        Route::put('/update',                        [OpcrController::class, 'opcrUpdate']);
    });

    /*
    |----------------------------------------------------------------------
    | PMT
    |----------------------------------------------------------------------
    */

    Route::prefix('pmt')->group(function () {
        Route::get('/opcr/{semester}/{year}',             [PmtController::class, 'listOfOpcrPmt']);
        Route::get('/office',           [PmtController::class, 'office']);
        Route::get('/ipcr',             [PmtController::class, 'listOfEmployeeIpcr']);
        Route::get('/office-employee',  [PmtController::class, 'getOfficeEmployeePmt']);
    });

    /*
    |----------------------------------------------------------------------
    | Supervisor
    |----------------------------------------------------------------------
    */

    Route::prefix('supervisor')->group(function () {
        Route::get('/ipcr',                 [SupervisorController::class, 'getAdvisoryEmployeeIpcr']);
        Route::post('/update/ipcr',         [SupervisorController::class, 'updateIpcr']);
        Route::get('/list/employee/ipcr',   [SupervisorController::class, 'getSupervisor']);
        Route::post('/update/performance-rating',   [SupervisorController::class, 'updatePerformanceRatingEmployee']); // approve the performance rating of employee
    });


    Route::prefix('ipcr')->group(function () {
    Route::post('/store',                                      [IpcrController::class, 'storeDocumentSignatories']);
    Route::get('/view/signatory/{controlNo}',                                      [IpcrController::class, 'viewDocumentSignatories']);

});
    Route::get('/test/plantilla',                                      [SpmsController::class, 'getStructureOffice']);

});