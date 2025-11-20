<?php

use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\StateController;
use Illuminate\Support\Facades\Route;

Route::get('/states', [StateController::class, 'index']);
Route::get('/employments', [EmploymentController::class, 'index']);
