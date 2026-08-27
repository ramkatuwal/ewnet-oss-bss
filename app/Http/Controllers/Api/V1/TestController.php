<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        try {
            return response()->json([
                'message' => 'Test controller works',
                'data' => $request->all()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error in test controller',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
