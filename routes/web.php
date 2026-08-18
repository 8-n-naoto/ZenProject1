<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\TopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TopController::class, 'index'])->name('top');
Route::get('/memo/store', [TopController::class, 'store'])->name('store');
Route::post('/memo/store', [TopController::class, 'create'])->name('create');
Route::get('/memo/{memo}/show', [TopController::class, 'show'])->name('show');
Route::get('/memo/{memo}/edit', [TopController::class, 'edit'])->name('edit');
Route::patch('/memo/{memo}/edit', [TopController::class, 'update'])->name('update');
Route::delete('/memo/{memo}/destroy', [TopController::class, 'destroy'])->name('destroy');

Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
