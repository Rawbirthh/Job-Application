<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\EmploymentTypeController;
use App\Http\Controllers\Admin\JobCategoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('dashboard', AdminDashboardController::class)
        ->middleware(['permission:admin.access'])
        ->name('dashboard');

    Route::get('users', [UserRoleController::class, 'index'])
        ->middleware(['permission:admin.access', 'permission:users.view'])
        ->name('users.index');

    Route::get('users/{user}/roles', [UserRoleController::class, 'edit'])
        ->middleware(['permission:admin.access', 'permission:users.roles.manage'])
        ->name('users.roles.edit');

    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware(['permission:admin.access', 'permission:users.roles.manage'])
        ->name('users.roles.update');

    // Roles management
    Route::prefix('roles')->name('roles.')->middleware(['permission:admin.access'])->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware(['permission:roles.view'])
            ->name('index');

        Route::middleware(['permission:roles.manage'])->group(function (): void {
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
        });
    });

    // Permissions management
    Route::prefix('permissions')->name('permissions.')->middleware(['permission:admin.access'])->group(function (): void {
        Route::get('/', [PermissionController::class, 'index'])
            ->middleware(['permission:permissions.view'])
            ->name('index');

        Route::middleware(['permission:permissions.manage'])->group(function (): void {
            Route::post('/', [PermissionController::class, 'store'])->name('store');
            Route::get('{permission}/edit', [PermissionController::class, 'edit'])->name('edit');
            Route::put('{permission}', [PermissionController::class, 'update'])->name('update');
            Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
        });
    });

    // Job Categories management
    Route::prefix('categories')->name('categories.')->middleware(['permission:admin.access'])->group(function (): void {
        Route::get('/', [JobCategoryController::class, 'index'])
            ->middleware(['permission:categories.view'])
            ->name('index');

        Route::middleware(['permission:categories.manage'])->group(function (): void {
            Route::post('/', [JobCategoryController::class, 'store'])->name('store');
            Route::put('{category}', [JobCategoryController::class, 'update'])->name('update');
            Route::delete('{category}', [JobCategoryController::class, 'destroy'])->name('destroy');
        });
    });

    // Employment Types management
    Route::prefix('employment-types')->name('employment-types.')->middleware(['permission:admin.access'])->group(function (): void {
        Route::get('/', [EmploymentTypeController::class, 'index'])
            ->middleware(['permission:employment_types.view'])
            ->name('index');

        Route::middleware(['permission:employment_types.manage'])->group(function (): void {
            Route::post('/', [EmploymentTypeController::class, 'store'])->name('store');
            Route::put('{employment_type}', [EmploymentTypeController::class, 'update'])->name('update');
            Route::delete('{employment_type}', [EmploymentTypeController::class, 'destroy'])->name('destroy');
        });
    });
});
