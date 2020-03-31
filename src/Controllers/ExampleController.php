<?php

namespace Igaster\LaravelMetrics\Controllers;

use Illuminate\Http\Request;

class ExampleController extends Controller
{

    public function index(Request $request)
    {
        return response()->json(['message' => 'success']);
    }

}
