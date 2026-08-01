<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\MatterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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
    ->prefix('matters')
    ->name('matters.')
    ->group(function (): void {
        Route::get('/', [MatterController::class, 'index'])->name('index');
        Route::get('open', [MatterController::class, 'open'])->name('open');
        Route::post('/', [MatterController::class, 'store'])->name('store');
    });

Route::middleware(['auth', 'verified', 'can:access-administration'])
    ->prefix('clients')
    ->name('clients.')
    ->group(function (): void {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::patch('{client}', [ClientController::class, 'update'])->name('update');
    });

require __DIR__.'/settings.php';
