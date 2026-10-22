<?php

namespace Modules\Comercial\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Comercial\Models\Contacto;

class LogsController extends Controller
{
    private const AUDITABLE_TYPES = [
        'Modules\Comercial\Models\Empresa',
        'Modules\Comercial\Models\Lead',
        'Modules\Comercial\Models\Contacto',
        'Modules\Comercial\Models\ContactoInteraccion',
        'Modules\Comercial\Models\Requerimiento',
        'Modules\Comercial\Models\RequerimientoInteraccion',
        'Modules\Comercial\Models\CotizacionRequerimiento',
        'Modules\Comercial\Models\Proyecto',
    ];

    /**
     * Vista principal de logs
     */
    public function index()
    {
        return view('comercial::logs.index');
    }

    /**
     * Get columns for logs table
     */
    public function columns()
    {
        return [
            'id' => 'ID',
            'tipo_label' => 'Tipo',
            'auditable_id' => 'Registro',
            'event' => 'Evento',
            'user' => 'Usuario',
            'changes_count' => 'Cambios',
            'created_at' => 'Fecha',
            'ip_address' => 'IP'
        ];
    }

    /**
     * Listar todos los audits del módulo Comercial
     * GET /comercial/api/logs
     */
    public function records(Request $request)
    {
        try {
            $query = DB::connection('tenant')->table('audits')
                ->whereIn('auditable_type', self::AUDITABLE_TYPES)
                ->leftJoin('users', 'audits.user_id', '=', 'users.id')
                ->select([
                    'audits.id',
                    'audits.auditable_id',
                    'audits.auditable_type',
                    'audits.event',
                    'audits.user_id',
                    'audits.old_values',
                    'audits.new_values',
                    'audits.url',
                    'audits.ip_address',
                    'audits.user_agent',
                    'audits.created_at',
                    'users.name as user_name',
                    'users.email as user_email'
                ]);

            // Filtros opcionales
            if ($request->filled('user_id')) {
                $query->where('audits.user_id', $request->user_id);
            }

            if ($request->filled('auditable_type')) {
                $query->where('audits.auditable_type', $request->auditable_type);
            }

            if ($request->filled('event')) {
                $query->where('audits.event', $request->event);
            }

            if ($request->filled('auditable_id')) {
                $query->where('audits.auditable_id', $request->auditable_id);
            }

            // Búsqueda por columna (compatible con DataTable)
            if ($request->filled('column') && $request->filled('value')) {
                $column = $request->get('column');
                $value = $request->get('value');
                
                if ($column === 'auditable_type') {
                    $query->where('audits.auditable_type', 'like', "%{$value}%");
                } elseif ($column === 'event') {
                    $query->where('audits.event', 'like', "%{$value}%");
                } elseif ($column === 'user') {
                    $query->where(function($q) use ($value) {
                        $q->where('users.name', 'like', "%{$value}%")
                          ->orWhere('users.email', 'like', "%{$value}%");
                    });
                }
            }

            // Paginación
            $perPage = $request->get('per_page', config('tenant.items_per_page', 50));
            $audits = $query->orderBy('audits.created_at', 'desc')
                ->paginate($perPage);
        } catch (Exception $e) {
            Log::error('Error en LogsController@records: ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => null,
                    'last_page' => 1,
                    'per_page' => $perPage ?? 50,
                    'to' => null,
                    'total' => 0
                ]
            ]);
        }

        // Procesar los cambios
        $audits->getCollection()->transform(function ($item) {
            $oldValues = json_decode($item->old_values, true) ?? [];
            $newValues = json_decode($item->new_values, true) ?? [];
            
            $changes = [];
            $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
            
            foreach ($allKeys as $key) {
                $old = $oldValues[$key] ?? null;
                $new = $newValues[$key] ?? null;
                
                if ($old !== $new) {
                    $changes[] = [
                        'field' => $key,
                        'old' => is_array($old) ? json_encode($old) : $old,
                        'new' => is_array($new) ? json_encode($new) : $new
                    ];
                }
            }
            
            $item->changes = $changes;
            $item->changes_count = count($changes);
            
            // Obtener nombre del tipo
            $item->tipo_label = $this->getTipoLabel($item->auditable_type);
            
            // Agregar objeto user para compatibilidad
            $item->user = $item->user_name ? [
                'name' => $item->user_name,
                'email' => $item->user_email
            ] : null;
            
            return $item;
        });

        // Retornar en el formato que espera el DataTable
        return response()->json([
            'data' => $audits->items(),
            'meta' => [
                'current_page' => $audits->currentPage(),
                'from' => $audits->firstItem(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'to' => $audits->lastItem(),
                'total' => $audits->total()
            ]
        ]);
    }

    /**
     * Ver un audit específico
     * GET /comercial/api/logs/{id}
     */
    public function show($id)
    {
        try {
            $audit = DB::connection('tenant')->table('audits')
                ->where('audits.id', $id)
                ->whereIn('auditable_type', self::AUDITABLE_TYPES)
                ->leftJoin('users', 'audits.user_id', '=', 'users.id')
                ->select([
                    'audits.*',
                    'users.name as user_name',
                    'users.email as user_email'
                ])
                ->first();

            if (!$audit) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Audit no encontrado'
                ], 404);
            }
        } catch (Exception $e) {
            Log::error('Error en LogsController@show: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'Error al obtener el audit'
            ], 500);
        }

        $oldValues = json_decode($audit->old_values, true) ?? [];
        $newValues = json_decode($audit->new_values, true) ?? [];
        
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        
        foreach ($allKeys as $key) {
            $old = $oldValues[$key] ?? null;
            $new = $newValues[$key] ?? null;
            
            if ($old !== $new) {
                $changes[] = [
                    'field' => $key,
                    'old' => is_array($old) ? json_encode($old) : $old,
                    'new' => is_array($new) ? json_encode($new) : $new
                ];
            }
        }

        // Obtener el modelo relacionado si existe
        $auditable = null;
        try {
            $modelClass = $audit->auditable_type;
            if (class_exists($modelClass)) {
                $auditable = $modelClass::find($audit->auditable_id);
            }
        } catch (Exception $e) {
            // El modelo puede no existir (fue eliminado)
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'audit' => [
                    'id' => $audit->id,
                    'auditable_id' => $audit->auditable_id,
                    'auditable_type' => $audit->auditable_type,
                    'event' => $audit->event,
                    'ip_address' => $audit->ip_address,
                    'created_at' => $audit->created_at,
                    'tipo_label' => $this->getTipoLabel($audit->auditable_type),
                ],
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changes' => $changes,
                'user' => $audit->user_name ? [
                    'name' => $audit->user_name,
                    'email' => $audit->user_email
                ] : null,
                'auditable' => $auditable,
            ]
        ]);
    }

    /**
     * Obtener lista de contactos para filtro
     * GET /comercial/api/logs/contactos
     */
    public function contactos()
    {
        try {
            $contactos = Contacto::query()
                ->whereNull('deleted_at')
                ->select('id', 'nombres', 'apellidos')
                ->orderBy('nombres')
                ->get();

            return response()->json([
                'ok' => true,
                'data' => $contactos
            ]);
        } catch (Exception $e) {
            Log::error('Error en LogsController@contactos: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'Error al obtener contactos'
            ], 500);
        }
    }

    /**
     * Ver estadísticas de auditoría
     * GET /comercial/api/logs/stats
     */
    public function stats()
    {
        try {
            $stats = [
                'total' => DB::connection('tenant')->table('audits')
                    ->whereIn('auditable_type', self::AUDITABLE_TYPES)
                    ->count(),
                
                'por_tipo' => DB::connection('tenant')->table('audits')
                    ->whereIn('auditable_type', self::AUDITABLE_TYPES)
                    ->selectRaw('auditable_type, count(*) as total')
                    ->groupBy('auditable_type')
                    ->get(),
                
                'por_evento' => DB::connection('tenant')->table('audits')
                    ->whereIn('auditable_type', self::AUDITABLE_TYPES)
                    ->selectRaw('event, count(*) as total')
                    ->groupBy('event')
                    ->get(),
            ];

            return response()->json([
                'ok' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Error en LogsController@stats: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Helper para obtener label del tipo
     */
    private function getTipoLabel($auditableType)
    {
        $tipos = [
            'Modules\Comercial\Models\Empresa'                  => 'Empresa',
            'Modules\Comercial\Models\Lead'                     => 'Lead',
            'Modules\Comercial\Models\Contacto'                 => 'Contacto',
            'Modules\Comercial\Models\ContactoInteraccion'      => 'Interacción Contacto',
            'Modules\Comercial\Models\Requerimiento'            => 'Requerimiento',
            'Modules\Comercial\Models\RequerimientoInteraccion' => 'Interacción Requerimiento',
            'Modules\Comercial\Models\CotizacionRequerimiento'  => 'Cotización',
            'Modules\Comercial\Models\Proyecto'                 => 'Proyecto',
        ];

        return $tipos[$auditableType] ?? $auditableType;
    }
}

