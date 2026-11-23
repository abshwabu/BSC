<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportDecisionController;
use App\Http\Controllers\ReportMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('reports.index');
    });

    Route::get('/dashboard', function () {
        return redirect()->route('reports.index');
    })->name('dashboard');

    // Reports Management
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])->name('reports.edit');
    Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
    Route::post('/reports/{report}/duplicate', [ReportController::class, 'duplicate'])->name('reports.duplicate');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    // Wizard autosave endpoints
    Route::patch('/reports/{report}/members/{reportMember}', [ReportMemberController::class, 'update'])->name('reports.members.update');
    Route::patch('/reports/{report}/decision', [ReportDecisionController::class, 'update'])->name('reports.decision.update');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
