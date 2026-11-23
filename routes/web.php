<?php

use App\Http\Controllers\ActivityTemplateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportDecisionController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportJsonController;
use App\Http\Controllers\ReportMemberController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TrendController;
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

    // Export & Preview endpoints
    Route::get('/reports/{report}/preview', [ReportExportController::class, 'preview'])->name('reports.preview');
    Route::get('/reports/{report}/pdf', [ReportExportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{report}/docx', [ReportExportController::class, 'docx'])->name('reports.docx');
    Route::post('/reports/{report}/unfinalize', [ReportExportController::class, 'unfinalize'])->name('reports.unfinalize');

    // JSON Import / Export
    Route::get('/reports/{report}/export-json', [ReportJsonController::class, 'export'])->name('reports.export-json');
    Route::post('/reports/import-json', [ReportJsonController::class, 'import'])->name('reports.import-json');

    // Wizard autosave endpoints
    Route::patch('/reports/{report}/members/{reportMember}', [ReportMemberController::class, 'update'])->name('reports.members.update');
    Route::patch('/reports/{report}/decision', [ReportDecisionController::class, 'update'])->name('reports.decision.update');

    // Templates Library
    Route::get('/templates', [ActivityTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [ActivityTemplateController::class, 'store'])->name('templates.store');
    Route::match(['put', 'patch'], '/templates/{template}', [ActivityTemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{template}', [ActivityTemplateController::class, 'destroy'])->name('templates.destroy');
    Route::get('/activity-templates', [ActivityTemplateController::class, 'index'])->name('activity-templates.index');

    // Trends Dashboard
    Route::get('/trends', [TrendController::class, 'index'])->name('trends.index');

    // System Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
