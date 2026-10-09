<?php

namespace Modules\Logistica\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NotionLogisticaService;

class LogisticaDashboardController extends Controller
{
    public function data(NotionLogisticaService $service)
    {
        $data = $service->getDashboardData();
        return response()->json($data);
    }

    public function index()
    {
        return view('logistica::dashboard.index');
    }
}
