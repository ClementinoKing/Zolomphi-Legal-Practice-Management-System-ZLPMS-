<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSettingsController;
use App\Http\Controllers\CompanyFilesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
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

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('search/suggestions', [GlobalSearchController::class, 'suggestions'])->middleware('can:dashboard.view')->name('search.suggestions');
    Route::get('search', [GlobalSearchController::class, 'index'])->middleware('can:dashboard.view')->name('search.index');
    Route::inertia('help', 'help/index')->middleware('can:dashboard.view')->name('help.index');
    Route::get('reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit-logs.view')->name('audit-logs.index');
});

Route::middleware(['auth', 'verified', 'can:time-entries.create'])
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
    ->prefix('administration')
    ->name('administration.')
    ->group(function (): void {
        Route::get('/', [AdministrationController::class, 'index'])->middleware('can:administration.access')->name('index');
        Route::get('firm', [AdministrationController::class, 'firm'])->middleware('can:firm-settings.manage')->name('firm');
        Route::get('users', [AdministrationController::class, 'users'])->middleware('can:users.manage')->name('users.index');
        Route::get('departments', [AdministrationController::class, 'departments'])->middleware('can:departments.manage')->name('departments.index');
        Route::get('positions', [AdministrationController::class, 'positions'])->middleware('can:departments.manage')->name('positions.index');
        Route::get('access', [AdministrationController::class, 'access'])->middleware('can:roles.manage')->name('access');
        Route::get('roles', [AdministrationController::class, 'roles'])->middleware('can:roles.manage')->name('roles.index');
        Route::get('permissions', [AdministrationController::class, 'permissions'])->middleware('can:permissions.manage')->name('permissions.index');
        Route::get('catalog', [AdministrationController::class, 'catalog'])->middleware('can:matter-types.manage')->name('catalog');
        Route::get('matter-types', [AdministrationController::class, 'matterTypes'])->middleware('can:matter-types.manage')->name('matter-types.index');
        Route::get('billing-items', [AdministrationController::class, 'billingItems'])->middleware('can:billing-items.manage')->name('billing-items.index');
        Route::get('expense-categories', [AdministrationController::class, 'expenseCategories'])->middleware('can:expense-categories.manage')->name('expense-categories.index');
        Route::get('payment-methods', [AdministrationController::class, 'paymentMethods'])->middleware('can:payment-methods.manage')->name('payment-methods.index');
        Route::put('firm-settings', [AdministrationController::class, 'updateFirmSettings'])->middleware('can:firm-settings.manage')->name('firm-settings.update');
        Route::post('users', [AdministrationController::class, 'storeUser'])->middleware('can:users.manage')->name('users.store');
        Route::patch('users/{user}', [AdministrationController::class, 'updateUser'])->middleware('can:users.manage')->name('users.update');
        Route::delete('users/{user}', [AdministrationController::class, 'destroyUser'])->middleware('can:users.delete')->name('users.destroy');
        Route::post('departments', [AdministrationController::class, 'storeDepartment'])->middleware('can:departments.manage')->name('departments.store');
        Route::patch('departments/{department}', [AdministrationController::class, 'updateDepartment'])->middleware('can:departments.manage')->name('departments.update');
        Route::post('positions', [AdministrationController::class, 'storePosition'])->middleware('can:departments.manage')->name('positions.store');
        Route::patch('positions/{position}', [AdministrationController::class, 'updatePosition'])->middleware('can:departments.manage')->name('positions.update');
        Route::post('roles', [AdministrationController::class, 'storeRole'])->middleware('can:roles.manage')->name('roles.store');
        Route::patch('roles/{role}', [AdministrationController::class, 'updateRole'])->middleware('can:roles.manage')->name('roles.update');
        Route::post('permissions', [AdministrationController::class, 'storePermission'])->middleware('can:permissions.manage')->name('permissions.store');
        Route::patch('permissions/{permission}', [AdministrationController::class, 'updatePermission'])->middleware('can:permissions.manage')->name('permissions.update');
        Route::post('reference-data/{resource}', [AdministrationController::class, 'storeReferenceData'])
            ->whereIn('resource', ['matter-types', 'billing-items', 'expense-categories', 'payment-methods'])
            ->name('reference-data.store');
        Route::patch('reference-data/{resource}/{record}', [AdministrationController::class, 'updateReferenceData'])
            ->whereIn('resource', ['matter-types', 'billing-items', 'expense-categories', 'payment-methods'])
            ->name('reference-data.update');
    });

Route::middleware(['auth', 'verified'])
    ->get('calendar', [CalendarController::class, 'index'])
    ->middleware('can:calendar.manage')
    ->name('calendar.index');

Route::middleware(['auth', 'verified'])
    ->prefix('files')
    ->name('files.')
    ->group(function (): void {
        Route::get('/', [CompanyFilesController::class, 'index'])->middleware('can:company-files.view')->name('index');
        Route::post('upload', [CompanyFilesController::class, 'store'])->middleware('can:company-files.upload')->name('store');
        Route::get('documents/{document}/download', [CompanyFilesController::class, 'download'])->middleware('can:view,document')->name('download');
        Route::get('documents/{document}/preview', [CompanyFilesController::class, 'preview'])->middleware('can:view,document')->name('preview');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('invoices')
    ->name('invoices.')
    ->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('can:viewAny,App\Models\Invoice')->name('index');
        Route::get('settings', [InvoiceSettingsController::class, 'edit'])->middleware('can:invoices.configure')->name('settings.edit');
        Route::put('settings', [InvoiceSettingsController::class, 'update'])->middleware('can:invoices.configure')->name('settings.update');
        Route::get('create', [InvoiceController::class, 'create'])->middleware('can:create,App\Models\Invoice')->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->middleware('can:create,App\Models\Invoice')->name('store');
        Route::get('{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('can:update,invoice')->name('edit');
        Route::patch('{invoice}', [InvoiceController::class, 'update'])->middleware('can:update,invoice')->name('update');
    });

Route::middleware(['auth', 'verified'])->prefix('payments')->name('payments.')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index'])->middleware('can:viewAny,App\Models\Payment')->name('index');
    Route::post('/', [PaymentController::class, 'store'])->middleware('can:record,App\Models\Payment')->name('store');
    Route::post('plans', [PaymentController::class, 'storePlan'])->middleware('can:managePlan,App\Models\Payment')->name('plans.store');
});

Route::middleware(['auth', 'verified'])
    ->prefix('matters')
    ->name('matters.')
    ->group(function (): void {
        Route::get('/', [MatterController::class, 'index'])->middleware('can:viewAny,App\Models\Matter')->name('index');
        Route::get('settings', [MatterSettingsController::class, 'edit'])->middleware('can:matters.configure')->name('settings.edit');
        Route::put('settings', [MatterSettingsController::class, 'update'])->middleware('can:matters.configure')->name('settings.update');
        Route::get('open', [MatterController::class, 'open'])->middleware('can:create,App\Models\Matter')->name('open');
        Route::post('/', [MatterController::class, 'store'])->middleware('can:create,App\Models\Matter')->name('store');
        Route::get('{matter}', [MatterWorkspaceController::class, 'show'])->middleware('can:view,matter')->name('show');
        Route::post('{matter}/{work}', [MatterWorkspaceController::class, 'store'])
            ->middleware('can:view,matter')
            ->whereIn('work', ['tasks', 'time-entries', 'documents', 'notes', 'emails', 'calendar-events', 'expenses', 'flat-fees'])
            ->name('workspace.store');
        Route::patch('{matter}/{work}/{record}', [MatterWorkspaceController::class, 'update'])
            ->middleware('can:view,matter')
            ->whereIn('work', ['tasks', 'time-entries', 'documents', 'notes', 'calendar-events', 'expenses', 'flat-fees'])
            ->name('workspace.update');
        Route::patch('{matter}/tasks/{task}/complete', [MatterWorkspaceController::class, 'completeTask'])->middleware(['can:view,matter', 'can:tasks.manage'])->name('tasks.complete');
        Route::get('{matter}/documents/{document}/download', [MatterWorkspaceController::class, 'download'])->middleware('can:view,matter')->name('documents.download');
        Route::get('{matter}/expenses/{expense}/receipt', [MatterWorkspaceController::class, 'downloadReceipt'])->middleware('can:view,matter')->name('expenses.receipt');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('clients')
    ->name('clients.')
    ->group(function (): void {
        Route::get('/', [ClientController::class, 'index'])->middleware('can:viewAny,App\Models\Client')->name('index');
        Route::get('settings', [ClientSettingsController::class, 'edit'])->middleware('can:clients.configure')->name('settings.edit');
        Route::put('settings', [ClientSettingsController::class, 'update'])->middleware('can:clients.configure')->name('settings.update');
        Route::post('/', [ClientController::class, 'store'])->middleware('can:create,App\Models\Client')->name('store');
        Route::patch('{client}', [ClientController::class, 'update'])->middleware('can:update,client')->name('update');
    });

require __DIR__.'/settings.php';
