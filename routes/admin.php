<?php

use App\Http\Controllers\Admin\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('users', [UserRoleController::class, 'index'])
        ->middleware(['permission:admin.access', 'permission:users.view'])
        ->name('users.index');

    Route::get('users/{user}/roles', [UserRoleController::class, 'edit'])
        ->middleware(['permission:admin.access', 'permission:users.roles.manage'])
        ->name('users.roles.edit');

    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware(['permission:admin.access', 'permission:users.roles.manage'])
        ->name('users.roles.update');
});
