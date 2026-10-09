<?php
namespace Modules\Logistica\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\Logistica\Helpers\LogisticaNotionDashboard;

class LogisticaController extends Controller
{
    public function index()
    {
        return view('logistica::dashboard');
    }
    
    public function dashboardData(Request $request)
    {
        $service = new LogisticaNotionDashboard();

        return response()->json(
            $service->getDashboardData()
        );
    }
}

