<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Http\JsonResponse;

class OperationController extends Controller
{
    /**
     * GET /api/v1/operations — kataloq (UI-da palitra). module-a görə sıralı.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Operation::orderBy('module')->orderBy('code')->get(['code', 'description', 'module', 'is_stock'])
        );
    }
}
