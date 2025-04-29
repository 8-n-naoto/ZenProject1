<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TopController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/',[TopController::class,'index'])->name("top");
Route::get('/memo/{memo}/show',[TopController::class,'show'])->name("show");
