<?php

use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\StateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/states', [StateController::class, 'index']);
Route::get('/employments', [EmploymentController::class, 'index']);
