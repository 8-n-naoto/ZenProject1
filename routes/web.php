<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TopController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/',[TopController::class,'index'])->name("top");
Route::get('/memo/{memo}/show',[TopController::class,'show'])->name("show");
Route::get('/memo/{memo}/edit',[TopController::class,'edit'])->name("edit");
Route::patch('/memo/{memo}/edit',[TopController::class,'update'])->name("update");
Route::delete('/memo/{memo}/destroy',[TopController::class,'destroy'])->name("destroy");
