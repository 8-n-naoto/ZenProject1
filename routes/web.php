<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TopController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\EmailVerificationController;

Route::get('/', [TopController::class, 'index'])->name('top');
Route::get('/memo/store', [TopController::class, 'store'])->name('store');
Route::post('/memo/store', [TopController::class, 'create'])->name('create');
Route::get('/memo/{memo}/show', [TopController::class, 'show'])->name('show');
Route::get('/memo/{memo}/edit', [TopController::class, 'edit'])->name('edit');
Route::patch('/memo/{memo}/edit', [TopController::class, 'update'])->name('update');
Route::delete('/memo/{memo}/destroy', [TopController::class, 'destroy'])->name('destroy');

Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');
