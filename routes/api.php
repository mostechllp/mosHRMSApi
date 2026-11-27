<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\ForgotPasswordApiController;
use App\Http\Controllers\Api\Admin\OffboardingApiController;
use App\Http\Controllers\Api\Admin\OffboardingChecklistApiController;
use App\Http\Controllers\Api\Admin\EmployeeApiController;
use App\Http\Controllers\Api\Admin\OrganizationApiController;
use App\Http\Controllers\Api\Admin\CompanyApiController;
use App\Http\Controllers\Api\Admin\HRApiController;
use App\Http\Controllers\Api\Admin\TaskApiController;
use App\Http\Controllers\Api\Admin\HolidayApiController;
use App\Http\Controllers\Api\Admin\DashboardApiController;
use App\Http\Controllers\Api\Admin\PartyApiController;
use App\Http\Controllers\Api\Admin\Accounts\ContactPersonApiController;
use App\Http\Controllers\Api\Admin\Accounts\CustomerApiController as AccountsCustomerApiController;
use App\Http\Controllers\Api\Admin\Accounts\ItemApiController;
use App\Http\Controllers\Api\Admin\Accounts\VendorApiController;
use App\Http\Controllers\Api\Admin\DocumentApiController;
use App\Http\Controllers\Api\Admin\FolderApiController;
use App\Http\Controllers\Api\Admin\LeaveApiController;
use App\Http\Controllers\Api\Admin\WfhApiController;
use App\Http\Controllers\Api\Admin\AttendanceApiController;
use App\Http\Controllers\Api\Admin\LeaveTypeApiController;
use App\Http\Controllers\Api\Admin\ReportApiController;
use App\Http\Controllers\Api\Admin\TaskReportApiController as AdminTaskReportApiController;
use App\Http\Controllers\Api\Admin\AttendanceRequestApiController as AdminAttendanceRequestApiController;
use App\Http\Controllers\Api\Admin\LeaveAllocationApiController;
use App\Http\Controllers\Api\Employee\EmployeePortalApiController;
use App\Http\Controllers\Api\Employee\EmployeeTaskApiController;
use App\Http\Controllers\Api\Employee\ProfileApiController;
use App\Http\Controllers\Api\Employee\AttendanceRequestApiController as EmployeeAttendanceRequestApiController;
use App\Http\Controllers\Api\Admin\FileApiController;
use App\Http\Controllers\Api\Admin\RoleApiController;
use App\Http\Controllers\Api\Admin\ModuleApiController;
use App\Http\Controllers\Api\Admin\UserApiController;
use App\Http\Controllers\Api\Admin\LeavePolicyController;
use App\Http\Controllers\Api\Admin\ProjectApiController;
use App\Http\Controllers\Api\Admin\ProjectAssignmentApiController;
use App\Http\Controllers\Api\Admin\ProjectCostApiController;
use App\Http\Controllers\Api\Admin\EmployeeBankDetailApiController;
use App\Http\Controllers\Api\Admin\EmployeeSalaryComponentApiController;
use App\Http\Controllers\Api\Admin\EmployeeOnboardingApiController;
use App\Http\Controllers\Api\Admin\WorkingHourApiController;
use App\Http\Controllers\Api\Admin\AssetTypeApiController;
use App\Http\Controllers\Api\Admin\AssetApiController;
use App\Http\Controllers\Api\Admin\PayrollController;
use App\Http\Controllers\Api\Admin\OffboardingChecklistCategoryController;
use App\Http\Controllers\Api\Admin\WarningApiController;
use App\Http\Controllers\Api\Admin\LeadApiController;
use App\Http\Controllers\Api\Admin\LeadFollowUpApiController;
use App\Http\Controllers\Api\Admin\LeadSettingsApiController;
use App\Http\Controllers\Api\Admin\CustomerApiController;
use App\Http\Controllers\Api\Admin\ActivityController;
use App\Http\Controllers\Api\Admin\QuotationController;
use App\Http\Controllers\Api\Admin\OpportunityApiController;
use App\Http\Controllers\Api\Admin\ProposalApiController;
use App\Http\Controllers\Api\Admin\ProductApiController;
use App\Http\Controllers\Api\Admin\OpportunitySettingsApiController;
use App\Http\Controllers\Api\Admin\CustomerSettingsApiController;
use App\Http\Controllers\Api\Admin\ActivitySettingsApiController;
use App\Http\Controllers\Api\Admin\ProductSettingsApiController;
use App\Http\Controllers\Api\Admin\CrmReportApiController;
use App\Http\Controllers\Api\Admin\CrmDashboardApiController;
use App\Http\Controllers\Api\Admin\CrmPermissionSettingsApiController;
use App\Http\Controllers\Api\Admin\QuotationSettingsApiController;
use App\Http\Controllers\Api\Admin\MeetingApiController;
use App\Http\Controllers\Api\CRM\LeadWebhookApiController;




/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Unified Auth Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('login/send-otp', [LoginController::class, 'sendLoginOtp']);
    Route::post('login/verify-otp', [LoginController::class, 'loginWithOtp']);
    Route::post('login-otp', [LoginController::class, 'loginWithOtp']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:api');
    Route::get('me', [LoginController::class, 'me'])->middleware('auth:api');
    Route::post('refresh', [LoginController::class, 'refresh']);
    Route::get('me/permissions', [LoginController::class, 'getMyPermissions'])->middleware('auth:api');
    Route::get('me/sidebar-modules', [LoginController::class, 'getMySidebarModules'])->middleware('auth:api');

    Route::post('forgot-password', [ForgotPasswordApiController::class, 'sendResetCode']);
    Route::post('reset-password', [ForgotPasswordApiController::class, 'resetPassword']);
});

// CRM Inbound Lead Webhooks & Integrations (Facebook, Instagram, LinkedIn, WhatsApp, Zapier/Make)
Route::group(['prefix' => 'crm'], function () {
    // Meta Webhooks (Facebook Lead Ads, Instagram Lead Ads, WhatsApp Cloud API)
    Route::get('webhooks/meta', [LeadWebhookApiController::class, 'verifyMetaWebhook']);
    Route::post('webhooks/meta', [LeadWebhookApiController::class, 'handleMetaWebhook']);

    // Universal Secure Lead Webhook / Ingest API (Protected by CRM Secret Key / Token)
    Route::post('leads/webhook', [LeadWebhookApiController::class, 'ingestLead']);
    Route::post('leads/ingest', [LeadWebhookApiController::class, 'ingestLead']);
});

// RBAC Management Routes
Route::group(['middleware' => 'auth:api', 'prefix' => 'admin'], function () {
    // Roles
    Route::apiResource('roles', RoleApiController::class);
    Route::get('roles/{role}/permissions', [RoleApiController::class, 'getPermissions']);
    Route::post('roles/{role}/permissions', [RoleApiController::class, 'updatePermissions']);

    // Modules
    Route::apiResource('modules', ModuleApiController::class);

    // Users
    Route::apiResource('users', UserApiController::class);
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'admin'], function () {
    // General File Download
    Route::get('download', [FileApiController::class, 'download']);

    // Dashboard
    Route::get('dashboard', [DashboardApiController::class, 'index'])->middleware('permission:dashboard.read');
    Route::get('dashboard/summary', [DashboardApiController::class, 'getSummaryStats'])->middleware('permission:dashboard.read');
    Route::get('dashboard/charts', [DashboardApiController::class, 'getDetailedChartData'])->middleware('permission:dashboard.read');
    Route::get('notifications', [DashboardApiController::class, 'getNotifications'])->middleware('permission:dashboard.read');
    Route::get('notifications/all', [DashboardApiController::class, 'getAllNotifications'])->middleware('permission:dashboard.read');
    Route::get('notifications/read', [DashboardApiController::class, 'getReadNotifications'])->middleware('permission:dashboard.read');
    Route::post('notifications/mark-all-as-read', [DashboardApiController::class, 'markAllAsRead'])->middleware('permission:dashboard.edit');
    Route::get('notifications/{id}', [DashboardApiController::class, 'showNotification'])->middleware('permission:dashboard.read');
    Route::post('notifications/{id}/mark-as-read', [DashboardApiController::class, 'markAsRead'])->middleware('permission:dashboard.edit');
    // Employees
    Route::get('employees', [EmployeeApiController::class, 'index'])->middleware('permission:employees.read');
    Route::get('active-employees', [EmployeeApiController::class, 'getActiveEmployees'])->middleware('permission:employees.read');
    Route::get('employees/{employee}', [EmployeeApiController::class, 'show'])->middleware('permission:employees.read');
    Route::post('employees', [EmployeeApiController::class, 'store'])->middleware('permission:employees.edit');
    Route::put('employees/{employee}', [EmployeeApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('employees/{employee}', [EmployeeApiController::class, 'destroy'])->middleware('permission:employees.delete');

    Route::post('employees/upload-temp', [EmployeeApiController::class, 'uploadTemp']);
    Route::post('employees/{employee}/update-status', [EmployeeApiController::class, 'updateStatus'])->middleware('permission:employees.edit');

    // Warning Messages
    Route::group(['prefix' => 'warnings', 'middleware' => 'permission:employees.edit'], function () {
        Route::get('/', [WarningApiController::class, 'index']);
        Route::post('/', [WarningApiController::class, 'store']);
        Route::get('/{id}', [WarningApiController::class, 'show']);
        Route::put('/{id}', [WarningApiController::class, 'update']);
        Route::delete('/{id}', [WarningApiController::class, 'destroy']);
        Route::post('/{id}/send-email', [WarningApiController::class, 'sendEmail']);
        Route::get('/{id}/attachment', [WarningApiController::class, 'downloadAttachment']);
    });

    // Onboarding
    Route::get('employees/onboard/details/{id?}', [EmployeeOnboardingApiController::class, 'getDetails'])->middleware('permission:employees.read');
    Route::post('employees/onboard/details/{id?}', [EmployeeOnboardingApiController::class, 'saveDetails'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/details/{id?}', [EmployeeOnboardingApiController::class, 'updateDetails'])->middleware('permission:employees.edit');
    Route::get('employees/onboard/verification/{id?}', [EmployeeOnboardingApiController::class, 'getVerification'])->middleware('permission:employees.read');
    Route::post('employees/onboard/verification/{id?}', [EmployeeOnboardingApiController::class, 'saveVerification'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/verification/{id?}', [EmployeeOnboardingApiController::class, 'updateVerification'])->middleware('permission:employees.edit');
    Route::post('employees/onboard/salary/{id?}', [EmployeeOnboardingApiController::class, 'saveSalary'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/salary/{id?}', [EmployeeOnboardingApiController::class, 'saveSalary'])->middleware('permission:employees.edit');
    Route::get('employees/onboard/salary/{id?}', [EmployeeOnboardingApiController::class, 'getSalary'])->middleware('permission:employees.read');
    Route::post('employees/onboard/banks/{id?}', [EmployeeOnboardingApiController::class, 'saveBanks'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/banks/{id?}', [EmployeeOnboardingApiController::class, 'saveBanks'])->middleware('permission:employees.edit');
    Route::get('employees/onboard/banks/{id?}', [EmployeeOnboardingApiController::class, 'getBanks'])->middleware('permission:employees.read');
    Route::get('employees/onboard/checklist/{id?}', [EmployeeOnboardingApiController::class, 'getChecklist'])->middleware('permission:employees.read');
    Route::post('employees/onboard/checklist/{id?}', [EmployeeOnboardingApiController::class, 'saveChecklist'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/checklist/{id?}', [EmployeeOnboardingApiController::class, 'saveChecklist'])->middleware('permission:employees.edit');
    Route::post('employees/onboard/complete/{id?}', [EmployeeOnboardingApiController::class, 'complete'])->middleware('permission:employees.edit');
    Route::put('employees/onboard/complete/{id?}', [EmployeeOnboardingApiController::class, 'complete'])->middleware('permission:employees.edit');
    Route::get('employees/onboard/progress/{id?}', [EmployeeOnboardingApiController::class, 'getProgress'])->middleware('permission:employees.read');

    // Employee Bank Details
    Route::put('bank-details/{id}', [EmployeeBankDetailApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('bank-details/{id}', [EmployeeBankDetailApiController::class, 'destroy'])->middleware('permission:employees.edit');

    // Employee Salary Components
    Route::post('salary-components', [EmployeeSalaryComponentApiController::class, 'store'])->middleware('permission:employees.edit');
    Route::put('salary-components/{id}', [EmployeeSalaryComponentApiController::class, 'update'])->middleware('permission:employees.edit');
    Route::delete('salary-components/{id}', [EmployeeSalaryComponentApiController::class, 'destroy'])->middleware('permission:employees.edit');
    Route::get('salary-components/employee/{id}', [EmployeeSalaryComponentApiController::class, 'getSalaryComponents'])->middleware('permission:employees.edit');

    // Attendance
    Route::get('attendance/stats', [AttendanceApiController::class, 'stats'])->middleware('permission:attendance.read');
    Route::get('attendance', [AttendanceApiController::class, 'index'])->middleware('permission:attendance.read');
    Route::post('attendance', [AttendanceApiController::class, 'store'])->middleware('permission:attendance.edit');
    Route::put('attendance/{id}', [AttendanceApiController::class, 'update'])->middleware('permission:attendance.edit');
    Route::delete('attendance/{id}', [AttendanceApiController::class, 'destroy'])->middleware('permission:attendance.delete');
    Route::get('attendance/{attendanceId}/breaks', [AttendanceApiController::class, 'getBreaksByAttendanceId'])->middleware('permission:attendance.read');
    Route::get('attendance-breaks/{attendanceId}', [AttendanceApiController::class, 'getBreaksByAttendanceId'])->middleware('permission:attendance.read');
    Route::put('attendance/breaks/{id}', [AttendanceApiController::class, 'updateBreak'])->middleware('permission:attendance.edit');
    Route::delete('attendance/breaks/{id}', [AttendanceApiController::class, 'destroyBreak'])->middleware('permission:attendance.delete');
    Route::get('attendance/breaks/{id}', [AttendanceApiController::class, 'showBreak'])->middleware('permission:attendance.read');
    Route::post('attendance/upload', [AttendanceApiController::class, 'upload'])->middleware('permission:attendance.edit');
    Route::get('attendance/upload-status/{id}', [AttendanceApiController::class, 'uploadStatus'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-in-today', [AttendanceApiController::class, 'punchInToday'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-in-yesterday', [AttendanceApiController::class, 'punchInYesterday'])->middleware('permission:attendance.read');
    Route::get('attendance/punch-out-today', [AttendanceApiController::class, 'punchOutToday'])->middleware('permission:attendance.read');
    Route::get('attendance/late-comers', [AttendanceApiController::class, 'lateComers'])->middleware('permission:attendance.read');
    Route::get('attendance/absentees', [AttendanceApiController::class, 'absentees'])->middleware('permission:attendance.read');

    // Attendance Requests
    Route::get('attendance-requests', [AdminAttendanceRequestApiController::class, 'index'])->middleware('permission:attendance.read');
    Route::post('attendance-requests/{attendanceRequest}/status', [AdminAttendanceRequestApiController::class, 'updateStatus'])->middleware('permission:attendance.edit');
    Route::put('attendance-requests/{attendanceRequest}', [AdminAttendanceRequestApiController::class, 'update'])->middleware('permission:attendance.edit');
    Route::delete('attendance-requests/{attendanceRequest}', [AdminAttendanceRequestApiController::class, 'destroy'])->middleware('permission:attendance.delete');

    // Organizations & Companies
    Route::apiResource('organizations', OrganizationApiController::class);
    Route::apiResource('companies', CompanyApiController::class);
    Route::apiResource('parties', PartyApiController::class);
    Route::apiResource('documents', DocumentApiController::class);
    Route::apiResource('folders', FolderApiController::class);
    Route::post('documents/upload', [DocumentApiController::class, 'upload']);
    Route::get('documents-folders', [DocumentApiController::class, 'getFolders']);
    Route::get('shareable-users', [DocumentApiController::class, 'getShareableUsers']);

    // Projects
    Route::get('projects/eligible-managers', [ProjectApiController::class, 'getEligibleManagers']);
    Route::get('projects/statuses', [ProjectApiController::class, 'getStatuses']);
    Route::get('projects/expiry-notifications', [ProjectApiController::class, 'getExpiryNotifications']);
    Route::post('projects/send-expiry-notifications', [ProjectApiController::class, 'sendExpiryNotifications']);
    Route::apiResource('projects', ProjectApiController::class);
    Route::get('project-assignments', [ProjectAssignmentApiController::class, 'index']);
    Route::get('project-assignments/{id}', [ProjectAssignmentApiController::class, 'show']);
    Route::post('employees/projects', [ProjectAssignmentApiController::class, 'assign']);

    // Project Cost & Financial Analysis
    Route::get('projects/cost/export', [ProjectCostApiController::class, 'export']);
    Route::get('projects/cost/export/excel', [ProjectCostApiController::class, 'exportExcel']);
    Route::get('projects/cost/export/pdf', [ProjectCostApiController::class, 'exportPdf']);
    Route::get('projects/cost', [ProjectCostApiController::class, 'index']);
    Route::get('projects/cost/{projectId}', [ProjectCostApiController::class, 'show']);

    Route::get('project-cost/export', [ProjectCostApiController::class, 'export']);
    Route::get('project-cost/export/excel', [ProjectCostApiController::class, 'exportExcel']);
    Route::get('project-cost/export/pdf', [ProjectCostApiController::class, 'exportPdf']);
    Route::get('project-cost', [ProjectCostApiController::class, 'index']);
    Route::get('project-cost/{projectId}', [ProjectCostApiController::class, 'show']);

    // Tasks
    Route::get('tasks/employees', [TaskApiController::class, 'listEmployees']);
    Route::apiResource('tasks', TaskApiController::class);
    Route::get('projects/{projectId}/tasks', [TaskApiController::class, 'tasksByProject']);
    Route::patch('tasks/{taskId}/status', [EmployeeTaskApiController::class, 'updateStatus']);

    // HR Modules
    Route::get('designations', [HRApiController::class, 'indexDesignations']);
    Route::post('designations', [HRApiController::class, 'storeDesignation']);
    Route::get('designations/{designation}', [HRApiController::class, 'showDesignation']);
    Route::put('designations/{designation}', [HRApiController::class, 'updateDesignation']);
    Route::delete('designations/{designation}', [HRApiController::class, 'destroyDesignation']);

    Route::get('departments', [HRApiController::class, 'indexDepartments']);
    Route::post('departments', [HRApiController::class, 'storeDepartment']);
    Route::get('departments/{department}', [HRApiController::class, 'showDepartment']);
    Route::put('departments/{department}', [HRApiController::class, 'updateDepartment']);
    Route::delete('departments/{department}', [HRApiController::class, 'destroyDepartment']);

    // Leave Management (Admin side)
    Route::get('leaves', [LeaveApiController::class, 'index'])->middleware('permission:leaves.read');
    Route::get('leaves/{leaveRequest}', [LeaveApiController::class, 'show'])->middleware('permission:leaves.read');
    Route::post('leaves/{leaveRequest}/status', [LeaveApiController::class, 'updateStatus'])->middleware('permission:leaves.edit');

    // WFH Requests (Admin side)
    Route::get('wfh-requests', [WfhApiController::class, 'index'])->middleware('permission:leaves.read');
    Route::get('wfh-requests/{wfhRequest}', [WfhApiController::class, 'show'])->middleware('permission:leaves.read');
    Route::put('wfh-requests/{wfhRequest}', [WfhApiController::class, 'update'])->middleware('permission:leaves.edit');
    Route::delete('wfh-requests/{wfhRequest}', [WfhApiController::class, 'destroy'])->middleware('permission:leaves.delete');
    Route::post('wfh-requests/{wfhRequest}/status', [WfhApiController::class, 'updateStatus'])->middleware('permission:leaves.edit');

    // Leave Types (Admin side)
    Route::apiResource('leave-types', LeaveTypeApiController::class)->middleware('permission:leaves.read');
    Route::get('get-active-leave-types', [LeaveTypeApiController::class, 'getLeaveTypes'])->middleware('permission:leaves.read');
    Route::post('leave-types/{leaveType}/status', [LeaveTypeApiController::class, 'updateStatus'])->middleware('permission:leaves.edit');

    // Leave policy
    Route::get('/leave-policies', [LeavePolicyController::class, 'index'])->middleware('permission:leaves.read');
    Route::post('/leave-policies', [LeavePolicyController::class, 'store'])->middleware('permission:leaves.edit');
    Route::get('/leave-policies/{id}', [LeavePolicyController::class, 'show'])->middleware('permission:leaves.read');
    Route::put('/leave-policies/{id}', [LeavePolicyController::class, 'update'])->middleware('permission:leaves.edit');
    Route::delete('/leave-policies/{id}', [LeavePolicyController::class, 'destroy'])->middleware('permission:leaves.delete');

    // Working Hours
    Route::get('working-hours', [WorkingHourApiController::class, 'index'])->middleware('permission:settings.read');
    Route::post('working-hours', [WorkingHourApiController::class, 'store'])->middleware('permission:settings.edit');

    // Leave Allocations
    Route::get('leave-allocations', [LeaveAllocationApiController::class, 'index'])->middleware('permission:leaves.read');
    Route::get('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'show'])->middleware('permission:leaves.read');
    Route::post('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'update'])->middleware('permission:leaves.edit');

    // Task Reports (Admin)
    Route::apiResource('task-reports', AdminTaskReportApiController::class)->middleware('permission:reports.read');

    // Holidays (Admin)
    Route::apiResource('holidays', HolidayApiController::class)->middleware('permission:settings.read');

    Route::group(['prefix' => 'payroll', 'middleware' => 'permission:payroll.read'], function () {
        Route::get('stats', [PayrollController::class, 'stats']);
        Route::get('employees', [PayrollController::class, 'getEmployees']);
        Route::get('/', [PayrollController::class, 'index']);
        Route::get('history', [PayrollController::class, 'history']);
        Route::get('working-days', [PayrollController::class, 'getWorkingDays']);
        Route::get('leaves', [PayrollController::class, 'getLeaves']);
        Route::get('draft/{employee_id}', [PayrollController::class, 'getDraft']);
        Route::get('employee-summary/{employee_id}', [PayrollController::class, 'employeeSalarySummary']);
        Route::get('{id}/download', [PayrollController::class, 'downloadPayslip']);
        Route::get('{id}', [PayrollController::class, 'show']);

        Route::post('calculate', [PayrollController::class, 'calculateMonthlySalary'])->middleware('permission:payroll.edit');
        Route::post('overtime', [PayrollController::class, 'calculateOvertime'])->middleware('permission:payroll.edit');
        Route::post('summary', [PayrollController::class, 'calculateTotals'])->middleware('permission:payroll.edit');
        Route::post('save-step', [PayrollController::class, 'saveStep'])->middleware('permission:payroll.edit');
        Route::post('submit', [PayrollController::class, 'submitPayroll'])->middleware('permission:payroll.edit');
        Route::post('generate', [PayrollController::class, 'generatePayroll'])->middleware('permission:payroll.edit');
        Route::post('convert-salary', [PayrollController::class, 'convertSalary'])->middleware('permission:payroll.edit');
        Route::post('{id}/send-payslip', [PayrollController::class, 'sendPayslip'])->middleware('permission:payroll.edit');
        Route::put('{id}', [PayrollController::class, 'update'])->middleware('permission:payroll.edit');
        Route::delete('{id}', [PayrollController::class, 'destroy'])->middleware('permission:payroll.edit');
    });

    // Reports
    Route::group(['prefix' => 'reports', 'middleware' => 'permission:reports.read'], function () {
        Route::get('attendance', [ReportApiController::class, 'attendanceReport']);
        Route::get('leaves', [ReportApiController::class, 'leaveReport']);
        Route::get('employees', [ReportApiController::class, 'employeeReport']);
        Route::get('employee-details', [ReportApiController::class, 'employeeDetails']);
        Route::get('employee-nearest-expiry', [ReportApiController::class, 'employeeNearestExpiry']);
        Route::get('employee-upcoming-renewals', [ReportApiController::class, 'employeeUpcomingRenewals']);
        Route::get('company-nearest-expiry', [ReportApiController::class, 'companyNearestExpiry']);
        Route::get('company-upcoming-renewals', [ReportApiController::class, 'companyUpcomingRenewals']);
        Route::get('pending-leaves', [ReportApiController::class, 'pendingLeavesReport']);
        Route::get('task-reports', [ReportApiController::class, 'taskReport']);
        Route::get('task-reports/export', [ReportApiController::class, 'taskReportExport']);
        Route::post('export', [ReportApiController::class, 'export'])->middleware('permission:reports.read');
    });

    // Offboarding Routes
    Route::group(['prefix' => 'offboarding'], function () {
        Route::get('/', [OffboardingApiController::class, 'index'])->middleware('permission:offboarding.read');
        Route::post('/initiate', [OffboardingApiController::class, 'initiate'])->middleware('permission:offboarding.edit');
        Route::post('/save-handover', [OffboardingApiController::class, 'saveHandover'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/handover', [OffboardingApiController::class, 'getHandover'])->middleware('permission:offboarding.read');
        Route::post('/{id}/handover', [OffboardingApiController::class, 'saveHandover'])->middleware('permission:offboarding.edit');
        Route::post('/save-leave-verification', [OffboardingApiController::class, 'saveLeaveVerification'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/leave-verification', [OffboardingApiController::class, 'getLeaveVerification'])->middleware('permission:offboarding.read');
        Route::post('/{id}/leave-verification', [OffboardingApiController::class, 'saveLeaveVerification'])->middleware('permission:offboarding.edit');
        Route::post('/save-access-removal', [OffboardingApiController::class, 'saveAccessRemoval'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/access-removal', [OffboardingApiController::class, 'getAccessRemoval'])->middleware('permission:offboarding.read');
        Route::post('/{id}/access-removal', [OffboardingApiController::class, 'saveAccessRemoval'])->middleware('permission:offboarding.edit');
        Route::put('/update-initiate', [OffboardingApiController::class, 'initiate'])->middleware('permission:offboarding.edit');
        Route::get('/reporting-managers', [OffboardingApiController::class, 'reportingManagers'])->middleware('permission:offboarding.read');
        Route::get('/employees', [OffboardingApiController::class, 'getAllEmployees'])->middleware('permission:offboarding.read');
        Route::get('/stats', [OffboardingApiController::class, 'getStats'])->middleware('permission:offboarding.read');
        Route::get('/employees/salary-packages/{id}', [OffboardingApiController::class, 'getSalaryPackages'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/visa-status', [OffboardingApiController::class, 'getVisaStatus'])->middleware('permission:offboarding.read');
        Route::post('/{id}/visa-status', [OffboardingApiController::class, 'updateVisaStatus'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/visa-status/complete', [OffboardingApiController::class, 'completeVisaStatus'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/checklists', [OffboardingApiController::class, 'updateChecklist'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/assets', [OffboardingApiController::class, 'updateAssets'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/interview', [OffboardingApiController::class, 'submitInterview'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/settlement', [OffboardingApiController::class, 'getSettlement'])->middleware('permission:offboarding.read');
        Route::post('/{id}/settlement', [OffboardingApiController::class, 'updateSettlement'])->middleware('permission:offboarding.edit');
        Route::post('/save-settlement', [OffboardingApiController::class, 'updateSettlement'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/letters', [OffboardingApiController::class, 'generateLetters'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/letters/upload', [OffboardingApiController::class, 'uploadLetter'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/letters/complete', [OffboardingApiController::class, 'updateLetters'])->middleware('permission:offboarding.edit');
        Route::post('/{id}/complete', [OffboardingApiController::class, 'completeOffboarding'])->middleware('permission:offboarding.edit');
        Route::get('/{id}/progress', [OffboardingApiController::class, 'getProgress'])->middleware('permission:offboarding.read');
        Route::get('/{id}', [OffboardingApiController::class, 'show'])->middleware('permission:offboarding.read');
        Route::delete('/{id}', [OffboardingApiController::class, 'destroy'])->middleware('permission:offboarding.delete');
    });

    //Checklists
    Route::prefix('checklists')->group(function () {
        Route::get('/{id}', [OffboardingChecklistApiController::class, 'index']);
        Route::post('/{id}', [OffboardingChecklistApiController::class, 'store']);
        Route::put('/item/{id}', [OffboardingChecklistApiController::class, 'update']);
        Route::patch('/item/{id}/status', [OffboardingChecklistApiController::class, 'updateStatus']);
        Route::delete('/item/{id}', [OffboardingChecklistApiController::class, 'destroy']);
    });

    Route::prefix('checklist-categories')->group(function () {
        Route::get('/', [OffboardingChecklistCategoryController::class, 'index']);
        Route::post('/', [OffboardingChecklistCategoryController::class, 'store']);
        Route::get('/{id}', [OffboardingChecklistCategoryController::class, 'show']);
        Route::put('/{id}', [OffboardingChecklistCategoryController::class, 'update']);
        Route::delete('/{id}', [OffboardingChecklistCategoryController::class, 'destroy']);
    });

    // Asset Management
    Route::group(['prefix' => 'assets'], function () {
        Route::apiResource('types', AssetTypeApiController::class);

        Route::get('/', [AssetApiController::class, 'index']);
        Route::post('/', [AssetApiController::class, 'store']);
        Route::get('/{id}', [AssetApiController::class, 'show']);
        Route::put('/{id}', [AssetApiController::class, 'update']);
        Route::delete('/{id}', [AssetApiController::class, 'destroy']);

        Route::post('/{id}/assign', [AssetApiController::class, 'assign']);
        Route::post('/{id}/revoke', [AssetApiController::class, 'revoke']);
    });

    // ── Meeting Management ────────────────────────────────────────────────
    Route::get('meetings/form-options', [MeetingApiController::class, 'formOptions'])->middleware('permission:crm.read');
    Route::get('meetings/participants/search', [MeetingApiController::class, 'searchParticipants'])->middleware('permission:crm.read');
    Route::patch('meetings/{meeting}/status', [MeetingApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
    Route::post('meetings/{meeting}/status', [MeetingApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
    Route::get('meetings', [MeetingApiController::class, 'index'])->middleware('permission:crm.read');
    Route::post('meetings', [MeetingApiController::class, 'store'])->middleware('permission:crm.edit');
    Route::get('meetings/{meeting}', [MeetingApiController::class, 'show'])->middleware('permission:crm.read');
    Route::put('meetings/{meeting}', [MeetingApiController::class, 'update'])->middleware('permission:crm.edit');
    Route::patch('meetings/{meeting}', [MeetingApiController::class, 'update'])->middleware('permission:crm.edit');
    Route::delete('meetings/{meeting}', [MeetingApiController::class, 'destroy'])->middleware('permission:crm.delete');


    // CRM Leads Management
    Route::group(['prefix' => 'crm'], function () {
        // CRM Dashboard
        Route::get('dashboard', [CrmDashboardApiController::class, 'index'])->middleware('permission:crm.read');

        Route::get('leads/form-options', [LeadApiController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('leads/stats', [LeadApiController::class, 'stats'])->middleware('permission:crm.read');
        Route::post('leads/{lead}/convert', [LeadApiController::class, 'convert'])->middleware('permission:crm.edit');
        Route::post('leads/{lead}/update-status', [LeadApiController::class, 'updateStatus'])->middleware('permission:crm.edit');

        // Export Leads (Excel, CSV, PDF)
        Route::get('leads/export', [LeadApiController::class, 'export'])->middleware('permission:crm.read');
        Route::get('leads/export/excel', [LeadApiController::class, 'exportExcel'])->middleware('permission:crm.read');
        Route::get('leads/export/pdf', [LeadApiController::class, 'exportPdf'])->middleware('permission:crm.read');

        // Lead Resource Routes
        Route::get('leads', [LeadApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('leads', [LeadApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::get('leads/{lead}', [LeadApiController::class, 'show'])->middleware('permission:crm.read');
        Route::put('leads/{lead}', [LeadApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('leads/{lead}', [LeadApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // Lead Follow-ups
        Route::get('follow-ups/pending', [LeadFollowUpApiController::class, 'pending'])->middleware('permission:crm.read');
        Route::get('leads/{lead}/follow-ups', [LeadFollowUpApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('leads/{lead}/follow-ups', [LeadFollowUpApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::put('leads/follow-ups/{followUp}', [LeadFollowUpApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('leads/follow-ups/{followUp}', [LeadFollowUpApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // ── Lead Settings (Statuses / Sources / Priorities / Qualification Rules) ─
        Route::get('lead-settings', [LeadSettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('lead-settings/seed-defaults', [LeadSettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Lead Statuses
        Route::get('lead-settings/statuses', [LeadSettingsApiController::class, 'indexStatuses'])->middleware('permission:crm.read');
        Route::post('lead-settings/statuses', [LeadSettingsApiController::class, 'storeStatus'])->middleware('permission:crm.edit');
        Route::get('lead-settings/statuses/{id}', [LeadSettingsApiController::class, 'showStatus'])->middleware('permission:crm.read');
        Route::put('lead-settings/statuses/{id}', [LeadSettingsApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
        Route::delete('lead-settings/statuses/{id}', [LeadSettingsApiController::class, 'destroyStatus'])->middleware('permission:crm.delete');

        // Lead Sources
        Route::get('lead-settings/sources', [LeadSettingsApiController::class, 'indexSources'])->middleware('permission:crm.read');
        Route::post('lead-settings/sources', [LeadSettingsApiController::class, 'storeSource'])->middleware('permission:crm.edit');
        Route::get('lead-settings/sources/{id}', [LeadSettingsApiController::class, 'showSource'])->middleware('permission:crm.read');
        Route::put('lead-settings/sources/{id}', [LeadSettingsApiController::class, 'updateSource'])->middleware('permission:crm.edit');
        Route::delete('lead-settings/sources/{id}', [LeadSettingsApiController::class, 'destroySource'])->middleware('permission:crm.delete');

        // Lead Priorities
        Route::get('lead-settings/priorities', [LeadSettingsApiController::class, 'indexPriorities'])->middleware('permission:crm.read');
        Route::post('lead-settings/priorities', [LeadSettingsApiController::class, 'storePriority'])->middleware('permission:crm.edit');
        Route::get('lead-settings/priorities/{id}', [LeadSettingsApiController::class, 'showPriority'])->middleware('permission:crm.read');
        Route::put('lead-settings/priorities/{id}', [LeadSettingsApiController::class, 'updatePriority'])->middleware('permission:crm.edit');
        Route::delete('lead-settings/priorities/{id}', [LeadSettingsApiController::class, 'destroyPriority'])->middleware('permission:crm.delete');

        // Lead Qualification Rules
        Route::get('lead-settings/qualification-rules', [LeadSettingsApiController::class, 'indexQualificationRules'])->middleware('permission:crm.read');
        Route::post('lead-settings/qualification-rules', [LeadSettingsApiController::class, 'storeQualificationRule'])->middleware('permission:crm.edit');
        Route::get('lead-settings/qualification-rules/{id}', [LeadSettingsApiController::class, 'showQualificationRule'])->middleware('permission:crm.read');
        Route::put('lead-settings/qualification-rules/{id}', [LeadSettingsApiController::class, 'updateQualificationRule'])->middleware('permission:crm.edit');
        Route::patch('lead-settings/qualification-rules/{id}/toggle', [LeadSettingsApiController::class, 'toggleQualificationRule'])->middleware('permission:crm.edit');
        Route::delete('lead-settings/qualification-rules/{id}', [LeadSettingsApiController::class, 'destroyQualificationRule'])->middleware('permission:crm.delete');

        // ── Customer Management ──────────────────────────────────────────────
        Route::get('customers/form-options', [CustomerApiController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('customers/stats', [CustomerApiController::class, 'stats'])->middleware('permission:crm.read');
        Route::post('customers/{customer}/update-status', [CustomerApiController::class, 'updateStatus'])->middleware('permission:crm.edit');

        // Export Customers (Excel, CSV, PDF)
        Route::get('customers/export', [CustomerApiController::class, 'export'])->middleware('permission:crm.read');
        Route::get('customers/export/excel', [CustomerApiController::class, 'exportExcel'])->middleware('permission:crm.read');
        Route::get('customers/export/pdf', [CustomerApiController::class, 'exportPdf'])->middleware('permission:crm.read');

        // Customer Resource Routes
        Route::get('customers', [CustomerApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('customers', [CustomerApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::get('customers/{customer}', [CustomerApiController::class, 'show'])->middleware('permission:crm.read');
        Route::put('customers/{customer}', [CustomerApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('customers/{customer}', [CustomerApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // ── Customer Settings (Categories / Industries / Statuses / Types) ────
        Route::get('customer-settings', [CustomerSettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('customer-settings/seed-defaults', [CustomerSettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Customer Categories
        Route::get('customer-settings/categories', [CustomerSettingsApiController::class, 'indexCategories'])->middleware('permission:crm.read');
        Route::post('customer-settings/categories', [CustomerSettingsApiController::class, 'storeCategory'])->middleware('permission:crm.edit');
        Route::get('customer-settings/categories/{id}', [CustomerSettingsApiController::class, 'showCategory'])->middleware('permission:crm.read');
        Route::put('customer-settings/categories/{id}', [CustomerSettingsApiController::class, 'updateCategory'])->middleware('permission:crm.edit');
        Route::delete('customer-settings/categories/{id}', [CustomerSettingsApiController::class, 'destroyCategory'])->middleware('permission:crm.delete');

        // Customer Industries
        Route::get('customer-settings/industries', [CustomerSettingsApiController::class, 'indexIndustries'])->middleware('permission:crm.read');
        Route::post('customer-settings/industries', [CustomerSettingsApiController::class, 'storeIndustry'])->middleware('permission:crm.edit');
        Route::get('customer-settings/industries/{id}', [CustomerSettingsApiController::class, 'showIndustry'])->middleware('permission:crm.read');
        Route::put('customer-settings/industries/{id}', [CustomerSettingsApiController::class, 'updateIndustry'])->middleware('permission:crm.edit');
        Route::delete('customer-settings/industries/{id}', [CustomerSettingsApiController::class, 'destroyIndustry'])->middleware('permission:crm.delete');

        // Customer Statuses
        Route::get('customer-settings/statuses', [CustomerSettingsApiController::class, 'indexStatuses'])->middleware('permission:crm.read');
        Route::post('customer-settings/statuses', [CustomerSettingsApiController::class, 'storeStatus'])->middleware('permission:crm.edit');
        Route::get('customer-settings/statuses/{id}', [CustomerSettingsApiController::class, 'showStatus'])->middleware('permission:crm.read');
        Route::put('customer-settings/statuses/{id}', [CustomerSettingsApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
        Route::delete('customer-settings/statuses/{id}', [CustomerSettingsApiController::class, 'destroyStatus'])->middleware('permission:crm.delete');

        // Customer Types
        Route::get('customer-settings/types', [CustomerSettingsApiController::class, 'indexTypes'])->middleware('permission:crm.read');
        Route::post('customer-settings/types', [CustomerSettingsApiController::class, 'storeType'])->middleware('permission:crm.edit');
        Route::get('customer-settings/types/{id}', [CustomerSettingsApiController::class, 'showType'])->middleware('permission:crm.read');
        Route::put('customer-settings/types/{id}', [CustomerSettingsApiController::class, 'updateType'])->middleware('permission:crm.edit');
        Route::delete('customer-settings/types/{id}', [CustomerSettingsApiController::class, 'destroyType'])->middleware('permission:crm.delete');

        // Activities
        Route::get('activities/form-options', [ActivityController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('activities/employees', [ActivityController::class, 'getEmployees'])->middleware('permission:crm.read');
        Route::get('activities/export', [ActivityController::class, 'export'])->middleware('permission:crm.read');
        Route::apiResource('activities', ActivityController::class)->except(['create', 'edit']);

        // ── Activity Settings (Types / Reminders / Priorities / Duration) ─────
        Route::get('activity-settings', [ActivitySettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('activity-settings/seed-defaults', [ActivitySettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Activity Types
        Route::get('activity-settings/types', [ActivitySettingsApiController::class, 'indexTypes'])->middleware('permission:crm.read');
        Route::post('activity-settings/types', [ActivitySettingsApiController::class, 'storeType'])->middleware('permission:crm.edit');
        Route::get('activity-settings/types/{id}', [ActivitySettingsApiController::class, 'showType'])->middleware('permission:crm.read');
        Route::put('activity-settings/types/{id}', [ActivitySettingsApiController::class, 'updateType'])->middleware('permission:crm.edit');
        Route::delete('activity-settings/types/{id}', [ActivitySettingsApiController::class, 'destroyType'])->middleware('permission:crm.delete');

        // Activity Reminder Options
        Route::get('activity-settings/reminders', [ActivitySettingsApiController::class, 'indexReminders'])->middleware('permission:crm.read');
        Route::post('activity-settings/reminders', [ActivitySettingsApiController::class, 'storeReminder'])->middleware('permission:crm.edit');
        Route::get('activity-settings/reminders/{id}', [ActivitySettingsApiController::class, 'showReminder'])->middleware('permission:crm.read');
        Route::put('activity-settings/reminders/{id}', [ActivitySettingsApiController::class, 'updateReminder'])->middleware('permission:crm.edit');
        Route::delete('activity-settings/reminders/{id}', [ActivitySettingsApiController::class, 'destroyReminder'])->middleware('permission:crm.delete');

        // Activity Priorities
        Route::get('activity-settings/priorities', [ActivitySettingsApiController::class, 'indexPriorities'])->middleware('permission:crm.read');
        Route::post('activity-settings/priorities', [ActivitySettingsApiController::class, 'storePriority'])->middleware('permission:crm.edit');
        Route::get('activity-settings/priorities/{id}', [ActivitySettingsApiController::class, 'showPriority'])->middleware('permission:crm.read');
        Route::put('activity-settings/priorities/{id}', [ActivitySettingsApiController::class, 'updatePriority'])->middleware('permission:crm.edit');
        Route::delete('activity-settings/priorities/{id}', [ActivitySettingsApiController::class, 'destroyPriority'])->middleware('permission:crm.delete');

        // Default Activity Duration
        Route::get('activity-settings/duration', [ActivitySettingsApiController::class, 'getDuration'])->middleware('permission:crm.read');
        Route::put('activity-settings/duration', [ActivitySettingsApiController::class, 'updateDuration'])->middleware('permission:crm.edit');

        // Quotations
        Route::get('quotations/form-options', [QuotationController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('quotations/export', [QuotationController::class, 'export'])->middleware('permission:crm.read');
        Route::get('quotations/{quotation}/email-preview', [QuotationController::class, 'emailPreview'])->middleware('permission:crm.read');
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'downloadPdf'])->middleware('permission:crm.read');
        Route::get('quotations/{quotation}/download-pdf', [QuotationController::class, 'downloadPdf'])->middleware('permission:crm.read');
        Route::post('quotations/{quotation}/send-email', [QuotationController::class, 'sendEmail'])->middleware('permission:crm.edit');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'sendEmail'])->middleware('permission:crm.edit');
        Route::post('quotations/{quotation}/update-status', [QuotationController::class, 'updateStatus'])->middleware('permission:crm.edit');
        Route::apiResource('quotations', QuotationController::class)->except(['create', 'edit']);

        // ── Opportunity Management ───────────────────────────────────────────
        Route::get('opportunities/form-options', [OpportunityApiController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('opportunities/stats', [OpportunityApiController::class, 'stats'])->middleware('permission:crm.read');
        Route::get('opportunities/pipeline', [OpportunityApiController::class, 'pipeline'])->middleware('permission:crm.read');
        Route::get('opportunities/kanban', [OpportunityApiController::class, 'pipeline'])->middleware('permission:crm.read');

        // Export Opportunities (Excel, CSV, PDF)
        Route::get('opportunities/export', [OpportunityApiController::class, 'export'])->middleware('permission:crm.read');
        Route::get('opportunities/export/excel', [OpportunityApiController::class, 'exportExcel'])->middleware('permission:crm.read');
        Route::get('opportunities/export/csv', [OpportunityApiController::class, 'exportCsv'])->middleware('permission:crm.read');
        Route::get('opportunities/export/pdf', [OpportunityApiController::class, 'exportPdf'])->middleware('permission:crm.read');

        // Stage & Quick Actions
        Route::patch('opportunities/{opportunity}/stage', [OpportunityApiController::class, 'updateStage'])->middleware('permission:crm.edit');
        Route::post('opportunities/{opportunity}/update-stage', [OpportunityApiController::class, 'updateStage'])->middleware('permission:crm.edit');
        Route::post('opportunities/{opportunity}/create-quotation', [OpportunityApiController::class, 'createQuotation'])->middleware('permission:crm.edit');
        Route::post('opportunities/{opportunity}/schedule-activity', [OpportunityApiController::class, 'scheduleActivity'])->middleware('permission:crm.edit');

        // Attachments
        Route::post('opportunities/{opportunity}/attachments', [OpportunityApiController::class, 'uploadAttachments'])->middleware('permission:crm.edit');
        Route::delete('opportunities/{opportunity}/attachments/{attachment}', [OpportunityApiController::class, 'deleteAttachment'])->middleware('permission:crm.delete');
        Route::get('opportunities/{opportunity}/attachments/{attachment}/download', [OpportunityApiController::class, 'downloadAttachment'])->middleware('permission:crm.read');

        // Opportunity Resource Routes
        Route::get('opportunities', [OpportunityApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('opportunities', [OpportunityApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::get('opportunities/{opportunity}', [OpportunityApiController::class, 'show'])->middleware('permission:crm.read');
        Route::put('opportunities/{opportunity}', [OpportunityApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('opportunities/{opportunity}', [OpportunityApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // ── Opportunity Settings (Stages / Lost Reasons / Win Reasons / Types) ─
        Route::get('opportunity-settings', [OpportunitySettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('opportunity-settings/seed-defaults', [OpportunitySettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Opportunity Stages
        Route::get('opportunity-settings/stages', [OpportunitySettingsApiController::class, 'indexStages'])->middleware('permission:crm.read');
        Route::post('opportunity-settings/stages', [OpportunitySettingsApiController::class, 'storeStage'])->middleware('permission:crm.edit');
        Route::get('opportunity-settings/stages/{id}', [OpportunitySettingsApiController::class, 'showStage'])->middleware('permission:crm.read');
        Route::put('opportunity-settings/stages/{id}', [OpportunitySettingsApiController::class, 'updateStage'])->middleware('permission:crm.edit');
        Route::delete('opportunity-settings/stages/{id}', [OpportunitySettingsApiController::class, 'destroyStage'])->middleware('permission:crm.delete');

        // Lost Reasons
        Route::get('opportunity-settings/lost-reasons', [OpportunitySettingsApiController::class, 'indexLostReasons'])->middleware('permission:crm.read');
        Route::post('opportunity-settings/lost-reasons', [OpportunitySettingsApiController::class, 'storeLostReason'])->middleware('permission:crm.edit');
        Route::get('opportunity-settings/lost-reasons/{id}', [OpportunitySettingsApiController::class, 'showLostReason'])->middleware('permission:crm.read');
        Route::put('opportunity-settings/lost-reasons/{id}', [OpportunitySettingsApiController::class, 'updateLostReason'])->middleware('permission:crm.edit');
        Route::delete('opportunity-settings/lost-reasons/{id}', [OpportunitySettingsApiController::class, 'destroyLostReason'])->middleware('permission:crm.delete');

        // Win Reasons
        Route::get('opportunity-settings/win-reasons', [OpportunitySettingsApiController::class, 'indexWinReasons'])->middleware('permission:crm.read');
        Route::post('opportunity-settings/win-reasons', [OpportunitySettingsApiController::class, 'storeWinReason'])->middleware('permission:crm.edit');
        Route::get('opportunity-settings/win-reasons/{id}', [OpportunitySettingsApiController::class, 'showWinReason'])->middleware('permission:crm.read');
        Route::put('opportunity-settings/win-reasons/{id}', [OpportunitySettingsApiController::class, 'updateWinReason'])->middleware('permission:crm.edit');
        Route::delete('opportunity-settings/win-reasons/{id}', [OpportunitySettingsApiController::class, 'destroyWinReason'])->middleware('permission:crm.delete');

        // Opportunity Types
        Route::get('opportunity-settings/types', [OpportunitySettingsApiController::class, 'indexTypes'])->middleware('permission:crm.read');
        Route::post('opportunity-settings/types', [OpportunitySettingsApiController::class, 'storeType'])->middleware('permission:crm.edit');
        Route::get('opportunity-settings/types/{id}', [OpportunitySettingsApiController::class, 'showType'])->middleware('permission:crm.read');
        Route::put('opportunity-settings/types/{id}', [OpportunitySettingsApiController::class, 'updateType'])->middleware('permission:crm.edit');
        Route::delete('opportunity-settings/types/{id}', [OpportunitySettingsApiController::class, 'destroyType'])->middleware('permission:crm.delete');

        // ── Proposal Management ──────────────────────────────────────────────
        Route::get('proposals/form-options', [ProposalApiController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::post('proposals/{proposal}/update-status', [ProposalApiController::class, 'updateStatus'])->middleware('permission:crm.edit');

        // Export Proposals (Excel, CSV, PDF)
        Route::get('proposals/export', [ProposalApiController::class, 'export'])->middleware('permission:crm.read');
        Route::get('proposals/export/excel', [ProposalApiController::class, 'exportExcel'])->middleware('permission:crm.read');
        Route::get('proposals/export/csv', [ProposalApiController::class, 'exportCsv'])->middleware('permission:crm.read');
        Route::get('proposals/export/pdf', [ProposalApiController::class, 'exportPdf'])->middleware('permission:crm.read');

        Route::get('proposals', [ProposalApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('proposals', [ProposalApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::get('proposals/{proposal}', [ProposalApiController::class, 'show'])->middleware('permission:crm.read');
        Route::put('proposals/{proposal}', [ProposalApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('proposals/{proposal}', [ProposalApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // ── Products & Services Catalog ──────────────────────────────────────
        Route::get('products/form-options', [ProductApiController::class, 'formOptions'])->middleware('permission:crm.read');
        Route::get('products/export', [ProductApiController::class, 'export'])->middleware('permission:crm.read');
        Route::get('products/export/excel', [ProductApiController::class, 'exportExcel'])->middleware('permission:crm.read');
        Route::get('products/export/csv', [ProductApiController::class, 'exportCsv'])->middleware('permission:crm.read');
        Route::get('products/export/pdf', [ProductApiController::class, 'exportPdf'])->middleware('permission:crm.read');

        // Categories management inside products (for "+" button in UI modal & list)
        Route::get('products/categories', [ProductApiController::class, 'listCategories'])->middleware('permission:crm.read');
        Route::post('products/categories', [ProductApiController::class, 'storeCategory'])->middleware('permission:crm.edit');
        Route::put('products/categories/{id}', [ProductApiController::class, 'updateCategory'])->middleware('permission:crm.edit');
        Route::delete('products/categories/{id}', [ProductApiController::class, 'destroyCategory'])->middleware('permission:crm.delete');

        Route::post('products/{product}/update-status', [ProductApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
        Route::patch('products/{product}/status', [ProductApiController::class, 'updateStatus'])->middleware('permission:crm.edit');
        Route::get('products', [ProductApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('products', [ProductApiController::class, 'store'])->middleware('permission:crm.edit');
        Route::get('products/{product}', [ProductApiController::class, 'show'])->middleware('permission:crm.read');
        Route::put('products/{product}', [ProductApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::patch('products/{product}', [ProductApiController::class, 'update'])->middleware('permission:crm.edit');
        Route::delete('products/{product}', [ProductApiController::class, 'destroy'])->middleware('permission:crm.delete');

        // ── Product Settings (Categories / Units / Currencies / Taxes) ─────────
        Route::get('product-settings', [ProductSettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('product-settings/seed-defaults', [ProductSettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Product Categories
        Route::get('product-settings/categories', [ProductSettingsApiController::class, 'indexCategories'])->middleware('permission:crm.read');
        Route::post('product-settings/categories', [ProductSettingsApiController::class, 'storeCategory'])->middleware('permission:crm.edit');
        Route::get('product-settings/categories/{id}', [ProductSettingsApiController::class, 'showCategory'])->middleware('permission:crm.read');
        Route::put('product-settings/categories/{id}', [ProductSettingsApiController::class, 'updateCategory'])->middleware('permission:crm.edit');
        Route::delete('product-settings/categories/{id}', [ProductSettingsApiController::class, 'destroyCategory'])->middleware('permission:crm.delete');

        // Units
        Route::get('product-settings/units', [ProductSettingsApiController::class, 'indexUnits'])->middleware('permission:crm.read');
        Route::post('product-settings/units', [ProductSettingsApiController::class, 'storeUnit'])->middleware('permission:crm.edit');
        Route::get('product-settings/units/{id}', [ProductSettingsApiController::class, 'showUnit'])->middleware('permission:crm.read');
        Route::put('product-settings/units/{id}', [ProductSettingsApiController::class, 'updateUnit'])->middleware('permission:crm.edit');
        Route::delete('product-settings/units/{id}', [ProductSettingsApiController::class, 'destroyUnit'])->middleware('permission:crm.delete');

        // Currencies
        Route::get('product-settings/currencies', [ProductSettingsApiController::class, 'indexCurrencies'])->middleware('permission:crm.read');
        Route::post('product-settings/currencies', [ProductSettingsApiController::class, 'storeCurrency'])->middleware('permission:crm.edit');
        Route::get('product-settings/currencies/{id}', [ProductSettingsApiController::class, 'showCurrency'])->middleware('permission:crm.read');
        Route::put('product-settings/currencies/{id}', [ProductSettingsApiController::class, 'updateCurrency'])->middleware('permission:crm.edit');
        Route::patch('product-settings/currencies/{id}/set-default', [ProductSettingsApiController::class, 'setDefaultCurrency'])->middleware('permission:crm.edit');
        Route::delete('product-settings/currencies/{id}', [ProductSettingsApiController::class, 'destroyCurrency'])->middleware('permission:crm.delete');

        // Taxes
        Route::get('product-settings/taxes', [ProductSettingsApiController::class, 'indexTaxes'])->middleware('permission:crm.read');
        Route::post('product-settings/taxes', [ProductSettingsApiController::class, 'storeTax'])->middleware('permission:crm.edit');
        Route::get('product-settings/taxes/{id}', [ProductSettingsApiController::class, 'showTax'])->middleware('permission:crm.read');
        Route::put('product-settings/taxes/{id}', [ProductSettingsApiController::class, 'updateTax'])->middleware('permission:crm.edit');
        Route::patch('product-settings/taxes/{id}/set-default', [ProductSettingsApiController::class, 'setDefaultTax'])->middleware('permission:crm.edit');
        Route::delete('product-settings/taxes/{id}', [ProductSettingsApiController::class, 'destroyTax'])->middleware('permission:crm.delete');

        // ── CRM Reports & Analytics Management ────────────────────────────────
        Route::get('reports/filter-options', [CrmReportApiController::class, 'filterOptions'])->middleware('permission:crm.read');

        // 1. Sales Report
        Route::get('reports/sales', [CrmReportApiController::class, 'salesReport'])->middleware('permission:crm.read');
        Route::get('reports/sales/export', [CrmReportApiController::class, 'exportSalesReport'])->middleware('permission:crm.read');

        // 2. Opportunity Report
        Route::get('reports/opportunity', [CrmReportApiController::class, 'opportunityReport'])->middleware('permission:crm.read');
        Route::get('reports/opportunity/export', [CrmReportApiController::class, 'exportOpportunityReport'])->middleware('permission:crm.read');

        // 3. Sales Performance Report
        Route::get('reports/sales-performance', [CrmReportApiController::class, 'salesPerformanceReport'])->middleware('permission:crm.read');
        Route::get('reports/sales-performance/export', [CrmReportApiController::class, 'exportSalesPerformanceReport'])->middleware('permission:crm.read');

        // 4. Activity Report
        Route::get('reports/activity', [CrmReportApiController::class, 'activityReport'])->middleware('permission:crm.read');
        Route::get('reports/activity/export', [CrmReportApiController::class, 'exportActivityReport'])->middleware('permission:crm.read');

        // 5. Quotation Report
        Route::get('reports/quotation', [CrmReportApiController::class, 'quotationReport'])->middleware('permission:crm.read');
        Route::get('reports/quotation/export', [CrmReportApiController::class, 'exportQuotationReport'])->middleware('permission:crm.read');

        // 6. Customer Report
        Route::get('reports/customer', [CrmReportApiController::class, 'customerReport'])->middleware('permission:crm.read');
        Route::get('reports/customer/export', [CrmReportApiController::class, 'exportCustomerReport'])->middleware('permission:crm.read');

        // 7. Lead Report
        Route::get('reports/lead', [CrmReportApiController::class, 'leadReport'])->middleware('permission:crm.read');
        Route::get('reports/lead/export', [CrmReportApiController::class, 'exportLeadReport'])->middleware('permission:crm.read');

        // ── CRM Permissions Settings ─────────────────────────────────────────
        Route::get('crm-permissions', [CrmPermissionSettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::put('crm-permissions', [CrmPermissionSettingsApiController::class, 'updateBulk'])->middleware('permission:crm.edit');
        Route::post('crm-permissions/seed-defaults', [CrmPermissionSettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');
        Route::get('crm-permissions/roles/{roleId}', [CrmPermissionSettingsApiController::class, 'showByRole'])->middleware('permission:crm.read');
        Route::put('crm-permissions/roles/{roleId}', [CrmPermissionSettingsApiController::class, 'updateByRole'])->middleware('permission:crm.edit');

        // ── Quotation Settings (Currencies / Payment Terms / Taxes) ───────────
        Route::get('quotation-settings', [QuotationSettingsApiController::class, 'index'])->middleware('permission:crm.read');
        Route::post('quotation-settings/seed-defaults', [QuotationSettingsApiController::class, 'seedDefaults'])->middleware('permission:crm.edit');

        // Currencies
        Route::get('quotation-settings/currencies', [QuotationSettingsApiController::class, 'indexCurrencies'])->middleware('permission:crm.read');
        Route::post('quotation-settings/currencies', [QuotationSettingsApiController::class, 'storeCurrency'])->middleware('permission:crm.edit');
        Route::get('quotation-settings/currencies/{id}', [QuotationSettingsApiController::class, 'showCurrency'])->middleware('permission:crm.read');
        Route::put('quotation-settings/currencies/{id}', [QuotationSettingsApiController::class, 'updateCurrency'])->middleware('permission:crm.edit');
        Route::delete('quotation-settings/currencies/{id}', [QuotationSettingsApiController::class, 'destroyCurrency'])->middleware('permission:crm.delete');

        // Payment Terms
        Route::get('quotation-settings/payment-terms', [QuotationSettingsApiController::class, 'indexPaymentTerms'])->middleware('permission:crm.read');
        Route::post('quotation-settings/payment-terms', [QuotationSettingsApiController::class, 'storePaymentTerm'])->middleware('permission:crm.edit');
        Route::get('quotation-settings/payment-terms/{id}', [QuotationSettingsApiController::class, 'showPaymentTerm'])->middleware('permission:crm.read');
        Route::put('quotation-settings/payment-terms/{id}', [QuotationSettingsApiController::class, 'updatePaymentTerm'])->middleware('permission:crm.edit');
        Route::delete('quotation-settings/payment-terms/{id}', [QuotationSettingsApiController::class, 'destroyPaymentTerm'])->middleware('permission:crm.delete');

        // Taxes
        Route::get('quotation-settings/taxes', [QuotationSettingsApiController::class, 'indexTaxes'])->middleware('permission:crm.read');
        Route::post('quotation-settings/taxes', [QuotationSettingsApiController::class, 'storeTax'])->middleware('permission:crm.edit');
        Route::get('quotation-settings/taxes/{id}', [QuotationSettingsApiController::class, 'showTax'])->middleware('permission:crm.read');
        Route::put('quotation-settings/taxes/{id}', [QuotationSettingsApiController::class, 'updateTax'])->middleware('permission:crm.edit');
        Route::patch('quotation-settings/taxes/{id}/set-default', [QuotationSettingsApiController::class, 'setDefaultTax'])->middleware('permission:crm.edit');
        Route::delete('quotation-settings/taxes/{id}', [QuotationSettingsApiController::class, 'destroyTax'])->middleware('permission:crm.delete');
    });

    // =========================================================================
    // ── Accounts Module (Separate Module: Revenue -> Customers) ───────────────
    // =========================================================================
    Route::prefix('accounts')->group(function () {
        // Revenue -> Customers (Primary endpoint requested: /api/admin/accounts/revenue/customers)
        Route::get('revenue/customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
        Route::apiResource('revenue/customers', AccountsCustomerApiController::class);
        Route::apiResource('revenue/customers.contact-persons', ContactPersonApiController::class);

        // Sales alias for frontend compatibility (/api/admin/accounts/sales/customers)
        Route::get('sales/customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
        Route::apiResource('sales/customers', AccountsCustomerApiController::class);
        Route::apiResource('sales/customers.contact-persons', ContactPersonApiController::class);

        // Root alias (/api/admin/accounts/customers)
        Route::get('customers/export', [AccountsCustomerApiController::class, 'export']);
        Route::get('customers/export/excel', [AccountsCustomerApiController::class, 'exportExcel']);
        Route::get('customers/export/pdf', [AccountsCustomerApiController::class, 'exportPdf']);
        Route::post('customers/import', [AccountsCustomerApiController::class, 'import']);
        Route::get('customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
        Route::apiResource('customers', AccountsCustomerApiController::class);
        Route::apiResource('customers.contact-persons', ContactPersonApiController::class);

        // Vendors
        Route::apiResource('vendors', VendorApiController::class);

        // Items
        Route::get('items/export', [ItemApiController::class, 'export']);
        Route::get('items/export/excel', [ItemApiController::class, 'exportExcel']);
        Route::get('items/export/pdf', [ItemApiController::class, 'exportPdf']);
        Route::post('items/import', [ItemApiController::class, 'import']);
        Route::get('items/form-options', [ItemApiController::class, 'formOptions']);
        Route::apiResource('items', ItemApiController::class);
    });
});

// Accounts Module also accessible at /api/accounts/... (direct prefix)
Route::group(['middleware' => 'auth:api', 'prefix' => 'accounts'], function () {
    // Revenue Customers
    Route::get('revenue/customers/export', [AccountsCustomerApiController::class, 'export']);
    Route::get('revenue/customers/export/excel', [AccountsCustomerApiController::class, 'exportExcel']);
    Route::get('revenue/customers/export/pdf', [AccountsCustomerApiController::class, 'exportPdf']);
    Route::post('revenue/customers/import', [AccountsCustomerApiController::class, 'import']);
    Route::get('revenue/customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
    Route::apiResource('revenue/customers', AccountsCustomerApiController::class);
    Route::apiResource('revenue/customers.contact-persons', ContactPersonApiController::class);

    // Sales Customers alias
    Route::get('sales/customers/export', [AccountsCustomerApiController::class, 'export']);
    Route::get('sales/customers/export/excel', [AccountsCustomerApiController::class, 'exportExcel']);
    Route::get('sales/customers/export/pdf', [AccountsCustomerApiController::class, 'exportPdf']);
    Route::post('sales/customers/import', [AccountsCustomerApiController::class, 'import']);
    Route::get('sales/customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
    Route::apiResource('sales/customers', AccountsCustomerApiController::class);
    Route::apiResource('sales/customers.contact-persons', ContactPersonApiController::class);

    // Direct /customers alias
    Route::get('customers/export', [AccountsCustomerApiController::class, 'export']);
    Route::get('customers/export/excel', [AccountsCustomerApiController::class, 'exportExcel']);
    Route::get('customers/export/pdf', [AccountsCustomerApiController::class, 'exportPdf']);
    Route::post('customers/import', [AccountsCustomerApiController::class, 'import']);
    Route::get('customers/form-options', [AccountsCustomerApiController::class, 'formOptions']);
    Route::apiResource('customers', AccountsCustomerApiController::class);
    Route::apiResource('customers.contact-persons', ContactPersonApiController::class);

    // Vendors
    Route::apiResource('vendors', VendorApiController::class);

    // Items (/api/accounts/items)
    Route::get('items/export', [ItemApiController::class, 'export']);
    Route::get('items/export/excel', [ItemApiController::class, 'exportExcel']);
    Route::get('items/export/pdf', [ItemApiController::class, 'exportPdf']);
    Route::post('items/import', [ItemApiController::class, 'import']);
    Route::get('items/form-options', [ItemApiController::class, 'formOptions']);
    Route::apiResource('items', ItemApiController::class);
});



// Employee Protected Routes (Now also using auth:api)
Route::group(['middleware' => 'auth:api', 'prefix' => 'employee'], function () {
    Route::get('dashboard', [EmployeePortalApiController::class, 'dashboard']);
    Route::get('punch-status', [EmployeePortalApiController::class, 'punchStatus']);
    Route::post('punch-in', [EmployeePortalApiController::class, 'punchIn']);
    Route::post('punch-out', [EmployeePortalApiController::class, 'punchOut']);
    Route::post('break/start', [EmployeePortalApiController::class, 'startBreak']);
    Route::post('break/end', [EmployeePortalApiController::class, 'endBreak']);
    Route::get('breaks', [EmployeePortalApiController::class, 'getBreaks']);
    Route::get('attendance/{attendanceId}/breaks', [EmployeePortalApiController::class, 'getAttendanceBreaks']);
    Route::get('breaks/{attendanceId}', [EmployeePortalApiController::class, 'getAttendanceBreaks']);

    // Leaves
    Route::get('leaves', [EmployeePortalApiController::class, 'leaves']);
    Route::get('leaves/{leave}', [EmployeePortalApiController::class, 'showLeave']);
    Route::get('leave-balance', [EmployeePortalApiController::class, 'leaveTypesAndBalance']);
    Route::post('leaves', [EmployeePortalApiController::class, 'storeLeave']);
    Route::put('leaves/{leave}', [EmployeePortalApiController::class, 'updateLeave']);
    Route::delete('leaves/{leave}', [EmployeePortalApiController::class, 'destroyLeave']);
    Route::get('leave-types', [LeaveTypeApiController::class, 'index']);
    Route::get('get-active-leave-types', [LeaveTypeApiController::class, 'getLeaveTypes']);
    Route::get('leave-allocations/{employee}', [LeaveAllocationApiController::class, 'show']);

    // Task Reports
    Route::get('task-reports', [EmployeePortalApiController::class, 'taskReports']);
    Route::get('task-reports/{taskReport}', [EmployeePortalApiController::class, 'showTaskReport']);
    Route::post('task-reports', [EmployeePortalApiController::class, 'storeTaskReport']);
    Route::put('task-reports/{taskReport}', [EmployeePortalApiController::class, 'updateTaskReport']);
    Route::delete('task-reports/{taskReport}', [EmployeePortalApiController::class, 'destroyTaskReport']);

    // WFH Requests
    Route::get('wfh-requests', [EmployeePortalApiController::class, 'wfhRequests']);
    Route::get('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'showWfhRequest']);
    Route::post('wfh-requests', [EmployeePortalApiController::class, 'storeWfhRequest']);
    Route::put('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'updateWfhRequest']);
    Route::delete('wfh-requests/{wfhRequest}', [EmployeePortalApiController::class, 'destroyWfhRequest']);

    // Attendance Requests
    Route::get('attendance-requests', [EmployeeAttendanceRequestApiController::class, 'index']);
    Route::post('attendance-requests', [EmployeeAttendanceRequestApiController::class, 'store']);
    Route::put('attendance-requests/{attendanceRequest}', [EmployeeAttendanceRequestApiController::class, 'update']);
    Route::delete('attendance-requests/{attendanceRequest}', [EmployeeAttendanceRequestApiController::class, 'destroy']);

    // Tasks (Employee side)
    Route::get('tasks', [EmployeeTaskApiController::class, 'index']);
    Route::get('tasks/{taskId}', [EmployeeTaskApiController::class, 'show']);
    Route::patch('tasks/{taskId}/status', [EmployeeTaskApiController::class, 'updateStatus']);

    //Assets
    Route::get('assets/{id}', [AssetApiController::class, 'employeeAssets']);


    Route::get('meetings/form-options', [MeetingApiController::class, 'formOptions']);
    Route::get('meetings/participants/search', [MeetingApiController::class, 'searchParticipants']);
    Route::patch('meetings/{meeting}/status', [MeetingApiController::class, 'updateStatus']);
    Route::post('meetings/{meeting}/status', [MeetingApiController::class, 'updateStatus']);
    Route::get('meetings', [MeetingApiController::class, 'index']);
    Route::get('my-meetings', [MeetingApiController::class, 'employeeMeetings']);
    Route::post('meetings', [MeetingApiController::class, 'store']);
    Route::get('meetings/{meeting}', [MeetingApiController::class, 'show']);
    Route::put('meetings/{meeting}', [MeetingApiController::class, 'update']);
    Route::patch('meetings/{meeting}', [MeetingApiController::class, 'update']);
    Route::delete('meetings/{meeting}', [MeetingApiController::class, 'destroy']);


    // Profile Settings
    Route::post('change-password', [ProfileApiController::class, 'changePassword']);
    Route::post('update-profile', [ProfileApiController::class, 'updateProfile']);
});


