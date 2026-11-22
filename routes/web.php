<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Settings\AcademicPeriodController;
use App\Http\Middleware\AuthorizeAppAccess;
use App\Http\Middleware\ShareAcademicPeriod;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('home'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Every page of the app: logged in, any role of this app, semester context shared with the views.
Route::middleware(['auth', AuthorizeAppAccess::class, ShareAcademicPeriod::class])->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Semester being viewed (topbar picker). Session only.
    Route::post('/academic-period', [AcademicPeriodController::class, 'update'])->name('academic-period.update');
    Route::post('/academic-period/reset', [AcademicPeriodController::class, 'reset'])->name('academic-period.reset');

    // Working features (scores, attendance, reports, ...) are added per stage and are protected with
    // ->middleware('can:use-rapor') plus Policies on the objects.
});
