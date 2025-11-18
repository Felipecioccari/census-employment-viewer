<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StateController extends Controller
{
    public function index(): JsonResponse
    {
        $states = config('states');
        return response()->json(['data' => $states]);
    }
}
