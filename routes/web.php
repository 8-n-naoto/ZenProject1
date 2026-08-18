<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Group\InvitationController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TopController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('top');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');

    Route::get('/groups/{group}/search-users', [GroupController::class, 'searchUsers'])->name('groups.search-users');
    Route::post('/groups/{group}/invite/{user}', [GroupController::class, 'invite'])->name('groups.invite');

    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
});

Route::get('/memo/store', [TopController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('store');

Route::post('/memo/store', [TopController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('create');

Route::get('/memo/{memo}/show', [TopController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('show');

Route::get('/memo/{memo}/edit', [TopController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('edit');

Route::patch('/memo/{memo}/edit', [TopController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('update');

Route::delete('/memo/{memo}/destroy', [TopController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('destroy');

Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');