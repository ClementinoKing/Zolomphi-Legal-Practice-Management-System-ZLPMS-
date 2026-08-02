<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSettingsController;
use App\Http\Controllers\CompanyFilesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InternalTaskController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\MatterController;
use App\Http\Controllers\MatterSettingsController;
use App\Http\Controllers\MatterWorkspaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TimeTrackerController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', 'can:access-administration'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search/suggestions', [GlobalSearchController::class, 'suggestions'])->name('search.suggestions');
    Route::get('search', [GlobalSearchController::class, 'index'])->name('search.index');
    Route::inertia('help', 'help/index')->name('help.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('time-tracker')
    ->name('time-tracker.')
    ->group(function (): void {
        Route::get('matters', [TimeTrackerController::class, 'matters'])->name('matters');
        Route::post('/', [TimeTrackerController::class, 'start'])->name('start');
        Route::patch('{timeTracker}/pause', [TimeTrackerController::class, 'pause'])->name('pause');
        Route::patch('{timeTracker}/resume', [TimeTrackerController::class, 'resume'])->name('resume');
        Route::patch('{timeTracker}/stop', [TimeTrackerController::class, 'stop'])->name('stop');
        Route::post('{timeTracker}/complete', [TimeTrackerController::class, 'complete'])->name('complete');
        Route::delete('{timeTracker}', [TimeTrackerController::class, 'discard'])->name('discard');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::patch('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('internal-tasks')
    ->name('internal-tasks.')
    ->group(function (): void {
        Route::get('/', [InternalTaskController::class, 'index'])->name('index');
        Route::post('/', [InternalTaskController::class, 'store'])->name('store');
        Route::patch('{internalTask}', [InternalTaskController::class, 'update'])->name('update');
        Route::patch('{internalTask}/complete', [InternalTaskController::class, 'complete'])->name('complete');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('administration')
    ->name('administration.')
    ->group(function (): void {
        Route::get('/', [AdministrationController::class, 'index'])->name('index');
        Route::get('firm', [AdministrationController::class, 'firm'])->name('firm');
        Route::get('users', [AdministrationController::class, 'users'])->name('users.index');
        Route::get('departments', [AdministrationController::class, 'departments'])->name('departments.index');
        Route::get('positions', [AdministrationController::class, 'positions'])->name('positions.index');
        Route::get('access', [AdministrationController::class, 'access'])->name('access');
        Route::get('roles', [AdministrationController::class, 'roles'])->name('roles.index');
        Route::get('permissions', [AdministrationController::class, 'permissions'])->name('permissions.index');
        Route::get('catalog', [AdministrationController::class, 'catalog'])->name('catalog');
        Route::get('matter-types', [AdministrationController::class, 'matterTypes'])->name('matter-types.index');
        Route::get('billing-items', [AdministrationController::class, 'billingItems'])->name('billing-items.index');
        Route::get('expense-categories', [AdministrationController::class, 'expenseCategories'])->name('expense-categories.index');
        Route::get('payment-methods', [AdministrationController::class, 'paymentMethods'])->name('payment-methods.index');
        Route::put('firm-settings', [AdministrationController::class, 'updateFirmSettings'])->name('firm-settings.update');
        Route::post('users', [AdministrationController::class, 'storeUser'])->name('users.store');
        Route::patch('users/{user}', [AdministrationController::class, 'updateUser'])->name('users.update');
        Route::post('departments', [AdministrationController::class, 'storeDepartment'])->name('departments.store');
        Route::patch('departments/{department}', [AdministrationController::class, 'updateDepartment'])->name('departments.update');
        Route::post('positions', [AdministrationController::class, 'storePosition'])->name('positions.store');
        Route::patch('positions/{position}', [AdministrationController::class, 'updatePosition'])->name('positions.update');
        Route::post('roles', [AdministrationController::class, 'storeRole'])->name('roles.store');
        Route::patch('roles/{role}', [AdministrationController::class, 'updateRole'])->name('roles.update');
        Route::post('permissions', [AdministrationController::class, 'storePermission'])->name('permissions.store');
        Route::patch('permissions/{permission}', [AdministrationController::class, 'updatePermission'])->name('permissions.update');
        Route::post('reference-data/{resource}', [AdministrationController::class, 'storeReferenceData'])
            ->whereIn('resource', ['matter-types', 'billing-items', 'expense-categories', 'payment-methods'])
            ->name('reference-data.store');
        Route::patch('reference-data/{resource}/{record}', [AdministrationController::class, 'updateReferenceData'])
            ->whereIn('resource', ['matter-types', 'billing-items', 'expense-categories', 'payment-methods'])
            ->name('reference-data.update');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->get('calendar', [CalendarController::class, 'index'])
    ->name('calendar.index');

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('files')
    ->name('files.')
    ->group(function (): void {
        Route::get('/', [CompanyFilesController::class, 'index'])->name('index');
        Route::post('upload', [CompanyFilesController::class, 'store'])->name('store');
        Route::get('documents/{document}/download', [CompanyFilesController::class, 'download'])->name('download');
        Route::get('documents/{document}/preview', [CompanyFilesController::class, 'preview'])->name('preview');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('invoices')
    ->name('invoices.')
    ->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('settings', [InvoiceSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [InvoiceSettingsController::class, 'update'])->name('settings.update');
        Route::get('create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::get('{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
        Route::patch('{invoice}', [InvoiceController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::post('/', [PaymentController::class, 'store'])->name('store');
    Route::post('plans', [PaymentController::class, 'storePlan'])->name('plans.store');
});

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('matters')
    ->name('matters.')
    ->group(function (): void {
        Route::get('/', [MatterController::class, 'index'])->name('index');
        Route::get('settings', [MatterSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [MatterSettingsController::class, 'update'])->name('settings.update');
        Route::get('open', [MatterController::class, 'open'])->name('open');
        Route::post('/', [MatterController::class, 'store'])->name('store');
        Route::get('{matter}', [MatterWorkspaceController::class, 'show'])->name('show');
        Route::post('{matter}/{work}', [MatterWorkspaceController::class, 'store'])
            ->whereIn('work', ['tasks', 'time-entries', 'documents', 'notes', 'emails', 'calendar-events', 'expenses', 'flat-fees'])
            ->name('workspace.store');
        Route::patch('{matter}/{work}/{record}', [MatterWorkspaceController::class, 'update'])
            ->whereIn('work', ['tasks', 'time-entries', 'documents', 'notes', 'calendar-events', 'expenses', 'flat-fees'])
            ->name('workspace.update');
        Route::patch('{matter}/tasks/{task}/complete', [MatterWorkspaceController::class, 'completeTask'])->name('tasks.complete');
        Route::get('{matter}/documents/{document}/download', [MatterWorkspaceController::class, 'download'])->name('documents.download');
        Route::get('{matter}/expenses/{expense}/receipt', [MatterWorkspaceController::class, 'downloadReceipt'])->name('expenses.receipt');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('clients')
    ->name('clients.')
    ->group(function (): void {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('settings', [ClientSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [ClientSettingsController::class, 'update'])->name('settings.update');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::patch('{client}', [ClientController::class, 'update'])->name('update');
    });

require __DIR__.'/settings.php';
