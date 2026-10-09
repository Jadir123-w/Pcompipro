<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\Payroll;
use Modules\Payroll\Models\PayrollEmployee;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Payroll\Exports\PayrollReportExport;

class ReportController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        // If request has filter parameters, treat as report generation
        if ($request->has('fecha_inicio')) {
             $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'tipo_reporte' => 'required|in:detallado,resumido,afp,essalud'
            ]);

            $reportData = $this->getReportData($request);
            
            // Re-fetch employees list if needed for the view, or rely on report data
            // But the view expects $employees in the else block
            $employees = $this->getEmployeesList();

            return view('payroll::reports.index', array_merge($reportData['data'], [
                'reportView' => $reportData['view'],
                'employees' => $employees
            ]));
        }

        $employees = $this->getEmployeesList();
        return view('payroll::reports.index', compact('employees'));
    }

    private function getEmployeesList() 
    {
        return PayrollEmployee::with('user')
            ->withCount('payrolls')
            ->with(['payrolls' => function($query) {
                $query->orderBy('periodo', 'desc');
            }])
            ->get()
            ->map(function($employee) {
                $employee->latest_payroll = $employee->payrolls->sortByDesc('periodo')->first();
                return $employee;
            });
    }

    // Removed payrollReport (merged into index)
    
    public function exportReport(Request $request)
    {
        $request->validate([
            'tipo_reporte' => 'required|in:detallado,resumido,afp,essalud',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $reportData = $this->getReportData($request);
        $filename = 'reporte_' . $request->tipo_reporte . '_' . Carbon::now()->format('YmdHis') . '.xlsx';
        
        return Excel::download(new PayrollReportExport($reportData['export_view'], $reportData['data']), $filename);
    }

    private function getReportData(Request $request)
    {
        $startDate = Carbon::parse($request->fecha_inicio);
        $endDate = Carbon::parse($request->fecha_fin);

        $query = Payroll::query()
            ->whereBetween('periodo', [$startDate->format('Y-m'), $endDate->format('Y-m')]);

        switch ($request->tipo_reporte) {
            case 'detallado':
                $payrolls = $query->with('detalles.empleado.user')->get();
                return [
                    'view' => 'payroll::reports.partials.detailed',
                    'export_view' => 'payroll::reports.exports.detailed',
                    'data' => compact('payrolls', 'startDate', 'endDate')
                ];

            case 'resumido':
                $summary = $query->select(
                    DB::raw('COUNT(*) as total_planillas'),
                    DB::raw('SUM(total_ingresos) as total_ingresos'),
                    DB::raw('SUM(total_descuentos) as total_descuentos'),
                    DB::raw('SUM(total_neto) as total_neto')
                )->first();
                return [
                    'view' => 'payroll::reports.partials.summary',
                    'export_view' => 'payroll::reports.exports.summary',
                    'data' => compact('summary', 'startDate', 'endDate')
                ];

            case 'afp':
                 $payrolls = $query->with('detalles.empleado.user')->get();
                return [
                    'view' => 'payroll::reports.partials.afp',
                    'export_view' => 'payroll::reports.exports.afp',
                    'data' => compact('payrolls', 'startDate', 'endDate')
                ];

            case 'essalud':
                 $payrolls = $query->with('detalles.empleado.user')->get();
                return [
                    'view' => 'payroll::reports.partials.essalud',
                    'export_view' => 'payroll::reports.exports.essalud',
                    'data' => compact('payrolls', 'startDate', 'endDate')
                ];
            default:
                throw new \Exception('Unexpected value');
        }
    }

    public function employeeReport($employeeId)
    {
        $employee = PayrollEmployee::with('user')->findOrFail($employeeId);

        // Get payrolls where this employee appears
        $payrolls = Payroll::whereHas('detalles', function($q) use ($employeeId) {
                $q->where('empleado_id', $employeeId);
            })
            ->with(['detalles' => function($q) use ($employeeId) {
                $q->where('empleado_id', $employeeId);
            }])
            ->orderBy('periodo', 'desc')
            ->get();

        return view('payroll::reports.employee', compact('employee', 'payrolls'));
    }
}
