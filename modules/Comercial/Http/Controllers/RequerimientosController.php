<?php
/**
 * ============================================================================
 * ARCHIVO: RequerimientosController.php (VERSIÓN 7)
 * ============================================================================
 * Ubicación: Modules/Comercial/Http/Controllers/RequerimientosController.php
 * 
 * CAMBIOS EN V7:
 * - list() devuelve TODOS los campos necesarios para las columnas del listado
 * - Incluye datos de empresa (ruc, tipo_empresa)
 * - Incluye datos de contacto (cargo, telefono, email)
 * - Incluye monto_real de la última cotización
 * - Incluye usuario_nombre de la última interacción
 * ============================================================================
 */

namespace Modules\Comercial\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Comercial\Models\Requerimiento;
use Modules\Comercial\Models\Empresa;
use Modules\Comercial\Models\Contacto;
use Modules\Comercial\Models\Lead;
use Modules\Comercial\Models\RequerimientoInteraccion;
use Modules\Comercial\Models\CotizacionRequerimiento;
use Modules\Comercial\Models\HistoriaActividadRequerimiento;
use Modules\Comercial\Models\Proyecto;
use Modules\Comercial\Models\ProyectoProducto;
use Modules\Comercial\Models\ProyectoEvaluacionProveedor;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\User;

class RequerimientosController extends Controller
{
    /**
     * Vista principal
     */
    public function index()
    {
        return view('comercial::requerimientos.index');
    }

    /**
     * API para listar requerimientos
     * V7: Devuelve TODOS los campos para las columnas del listado
     */
    public function list(Request $request)
    {
        try {
            $query = Requerimiento::query()
                ->with(['empresa', 'contacto', 'lead', 'cotizaciones']);

            // Filtro activos en CRM
            $activo = $request->input('activo_en_crm', true);
            if ($activo === true || $activo === 'true' || $activo === 1 || $activo === '1') {
                $query->where('activo_en_crm', true);
            }

            // Filtros
            if ($request->filled('etapa') && $request->etapa !== 'Todos') {
                $query->where('etapa', $request->etapa);
            }
            if ($request->filled('prioridad') && $request->prioridad !== 'Todos') {
                $query->where('nivel_prioridad', $request->prioridad);
            }
            if ($request->filled('origen') && $request->origen !== 'Todos') {
                $query->where('origen', $request->origen);
            }
            if ($request->filled('empresa_id')) {
                $query->where('empresa_id', $request->empresa_id);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('titulo', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%")
                      ->orWhere('id', $search);
                });
            }

            // Ordenamiento: última actualización primero (recién editados arriba).
            $query->orderBy('updated_at', 'desc')
                  ->orderBy('id', 'desc');

            $requerimientos = $query->get();

            // Proyecto generado por requerimiento (para los convertidos), en una sola
            // consulta para evitar N+1 al armar las tarjetas.
            $proyectosPorReq = Proyecto::whereIn('requerimiento_id', $requerimientos->pluck('id')->all())
                ->get(['id', 'codigo', 'requerimiento_id'])
                ->keyBy('requerimiento_id');

            $rows = $requerimientos->map(function ($r) use ($proyectosPorReq) {
                // Nombre completo del contacto
                $contactoNombre = null;
                if ($r->contacto) {
                    $contactoNombre = trim($r->contacto->nombres . ' ' . $r->contacto->apellidos);
                }

                // Usuario de apoyo
                $usuarioApoyoNombre = null;
                if ($r->usuario_apoyo_cotizacion) {
                    $usuarioApoyo = User::find($r->usuario_apoyo_cotizacion);
                    if ($usuarioApoyo) {
                        $usuarioApoyoNombre = $usuarioApoyo->name;
                    }
                }

                // Usuario última interacción
                $usuarioNombre = null;
                if ($r->usuario_ultima_interaccion) {
                    $usuario = User::find($r->usuario_ultima_interaccion);
                    if ($usuario) {
                        $usuarioNombre = $usuario->name;
                    }
                }

                // Monto real (de la última cotización aprobada o la más reciente)
                $montoReal = null;
                $ultimaCotizacion = $r->cotizaciones->first();
                if ($ultimaCotizacion) {
                    $montoReal = $ultimaCotizacion->monto;
                }

                return [
                    // Campos básicos
                    'id'                      => $r->id,
                    'codigo'                  => 'REQ-' . str_pad($r->id, 4, '0', STR_PAD_LEFT),
                    'titulo'                  => $r->titulo,
                    'descripcion'             => $r->descripcion,
                    
                    // Campos de empresa
                    'empresa_id'              => $r->empresa_id,
                    'empresa_razon_social'    => $r->empresa ? $r->empresa->razon_social : null,
                    'empresa_nombre_comercial'=> $r->empresa ? $r->empresa->nombre_comercial : null,
                    'empresa_ruc'             => $r->empresa ? $r->empresa->ruc : null,
                    'empresa_tipo'            => $r->empresa ? $r->empresa->tipo_empresa : null,
                    
                    // Campos de contacto
                    'contacto_id'             => $r->contacto_id,
                    'contacto_nombre'         => $contactoNombre,
                    'contacto_cargo'          => $r->contacto ? $r->contacto->cargo : null,
                    'contacto_telefono'       => $r->contacto ? $r->contacto->telefono : null,
                    'contacto_email'          => $r->contacto ? $r->contacto->email : null,
                    
                    // Lead (para compatibilidad)
                    'lead_id'                 => $r->lead_id,
                    'lead_contacto'           => $contactoNombre,
                    
                    // Campos del requerimiento
                    'origen'                  => $r->origen,
                    'etapa'                   => $r->etapa,
                    'nivel_prioridad'         => $r->nivel_prioridad,
                    'tipo_condicion_proyecto' => $r->tipo_condicion_proyecto,
                    'motivo_compra'           => $r->motivo_compra,
                    'forma_pago'              => $r->forma_pago,
                    'nivel_tentativo'         => $r->nivel_tentativo,
                    'categoria_productos'     => $r->categoria_productos,
                    
                    // Usuario apoyo
                    'usuario_apoyo_cotizacion'=> $r->usuario_apoyo_cotizacion,
                    'usuario_apoyo_nombre'    => $usuarioApoyoNombre,
                    
                    // Montos
                    'monto_estimado'          => $r->monto_estimado,
                    'monto_real'              => $montoReal,
                    
                    // Fechas
                    'fecha_requerida'         => $r->fecha_requerida ? $r->fecha_requerida->format('Y-m-d') : null,
                    'fecha_ingreso_etapa'     => $r->fecha_ingreso_etapa_actual ? $r->fecha_ingreso_etapa_actual->format('Y-m-d') : null,
                    'ultima_interaccion'      => $r->fecha_ultima_interaccion ? $r->fecha_ultima_interaccion->format('Y-m-d H:i') : null,
                    
                    // Estados
                    'cotizacion_aprobada'     => $r->cotizacion_aprobada,
                    'escalado_a_proyecto'     => $r->escalado_a_proyecto,
                    'proyecto_id'             => optional($proyectosPorReq->get($r->id))->id,
                    'proyecto_codigo'         => optional($proyectosPorReq->get($r->id))->codigo,
                    'activo_en_crm'           => $r->activo_en_crm,
                    'estado'                  => $r->estado,
                    
                    // Usuario última interacción
                    'usuario_nombre'          => $usuarioNombre,
                    
                    // Timestamps
                    'created_at'              => $r->created_at ? $r->created_at->format('Y-m-d H:i') : null,
                    'updated_at'              => $r->updated_at ? $r->updated_at->format('Y-m-d H:i') : null,
                ];
            });

            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Error en list(): ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * KPIs
     */
    public function kpis()
    {
        try {
            $activosEnCrm = Requerimiento::where('activo_en_crm', true)->count();
            $pendientesAtencion = Requerimiento::where('activo_en_crm', true)
                ->where('etapa', 'Pendiente atender')->count();
            $requierenSeguimientoHoy = Requerimiento::whereNotNull('fecha_seguimiento')
                ->whereDate('fecha_seguimiento', '<=', now())->count();
            $muyAlto = Requerimiento::where('activo_en_crm', true)
                ->where('nivel_prioridad', 'Muy alto')->count();
            $aprobadasSinProyecto = Requerimiento::where('cotizacion_aprobada', true)
                ->where('escalado_a_proyecto', false)->count();
            $totalCotizacionesAprobadas = Requerimiento::where('cotizacion_aprobada', true)->count();
            $aprobadasSinProyectoPorcentaje = $totalCotizacionesAprobadas > 0
                ? round(($aprobadasSinProyecto / $totalCotizacionesAprobadas) * 100, 1)
                : 0;
            $sinRespuesta3Dias = Requerimiento::where('activo_en_crm', true)
                ->where(function($q) {
                    $q->whereNull('fecha_ultima_interaccion')
                      ->orWhere('fecha_ultima_interaccion', '<=', now()->subDays(3));
                })->count();

            return response()->json([
                'ok'   => true,
                'data' => [
                    'activos_en_crm'                    => $activosEnCrm,
                    'pendientes_atencion'               => $pendientesAtencion,
                    'pendientes_hoy'                    => $requierenSeguimientoHoy,
                    'requieren_seguimiento_hoy'         => $requierenSeguimientoHoy,
                    'muy_alto'                          => $muyAlto,
                    'sin_respuesta_3dias'               => $sinRespuesta3Dias,
                    'aprobadas_sin_proyecto'            => $aprobadasSinProyecto,
                    'aprobadas_sin_proyecto_porcentaje' => $aprobadasSinProyectoPorcentaje,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en kpis(): ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Detalle de un requerimiento
     */
    public function show($id)
    {
        try {
            $req = Requerimiento::with(['empresa', 'contacto', 'lead'])->findOrFail($id);

            // Nombre completo del contacto
            $contactoNombre = null;
            if ($req->contacto) {
                $contactoNombre = trim($req->contacto->nombres . ' ' . $req->contacto->apellidos);
            }

            // Usuario de apoyo
            $usuarioApoyoNombre = null;
            if ($req->usuario_apoyo_cotizacion) {
                $usuarioApoyo = User::find($req->usuario_apoyo_cotizacion);
                if ($usuarioApoyo) {
                    $usuarioApoyoNombre = $usuarioApoyo->name;
                }
            }

            $estadoProvDetalle = $this->contarProductosSinProveedor($req->id);

            // Proyecto generado (si el requerimiento ya fue convertido) — para el
            // enlace "Ver proyecto" y el modo solo lectura.
            $proyectoGenerado = $req->escalado_a_proyecto
                ? Proyecto::where('requerimiento_id', $req->id)->orderBy('id', 'desc')->first()
                : null;

            $data = [
                'id'                         => $req->id,
                'codigo'                     => 'REQ-' . str_pad($req->id, 4, '0', STR_PAD_LEFT),
                'empresa_id'                 => $req->empresa_id,
                'empresa_razon_social'       => $req->empresa ? $req->empresa->razon_social : null,
                'empresa_nombre_comercial'   => $req->empresa ? $req->empresa->nombre_comercial : null,
                'contacto_id'                => $req->contacto_id,
                'contacto_nombre'            => $contactoNombre,
                'lead_id'                    => $req->lead_id,
                'lead_contacto'              => $contactoNombre,
                'titulo'                     => $req->titulo,
                'descripcion'                => $req->descripcion,
                'origen'                     => $req->origen,
                'etapa'                      => $req->etapa,
                'nivel_prioridad'            => $req->nivel_prioridad,
                'tipo_condicion_proyecto'    => $req->tipo_condicion_proyecto,
                'motivo_compra'              => $req->motivo_compra,
                'forma_pago'                 => $req->forma_pago,
                'nivel_tentativo'            => $req->nivel_tentativo,
                'categoria_productos'        => $req->categoria_productos,
                'usuario_apoyo_cotizacion'   => $req->usuario_apoyo_cotizacion,
                'usuario_apoyo_nombre'       => $usuarioApoyoNombre,
                'monto_estimado'             => $req->monto_estimado,
                'fecha_requerida'            => $req->fecha_requerida ? $req->fecha_requerida->format('Y-m-d') : null,
                'cotizacion_aprobada'        => $req->cotizacion_aprobada,
                'escalado_a_proyecto'        => $req->escalado_a_proyecto,
                'proyecto_id'                => $proyectoGenerado ? $proyectoGenerado->id : null,
                'proyecto_codigo'            => $proyectoGenerado ? $proyectoGenerado->codigo : null,
                'activo_en_crm'              => $req->activo_en_crm,
                'estado'                     => $req->estado,
                'puede_crear_proyecto'       => $req->cotizacion_aprobada && !$req->escalado_a_proyecto,
                'productos_sin_proveedor'    => $estadoProvDetalle['sin_proveedor'],
                'total_productos'            => $estadoProvDetalle['total'],
                'fecha_ultima_interaccion'   => $req->fecha_ultima_interaccion,
                'created_at'                 => $req->created_at ? $req->created_at->format('Y-m-d H:i') : null,
                'updated_at'                 => $req->updated_at ? $req->updated_at->format('Y-m-d H:i') : null,
            ];

            return response()->json([
                'ok'   => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en show(): ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un nuevo requerimiento
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'empresa_id'                => ['required', 'integer'],
            'titulo'                    => ['required', 'string', 'max:191'],
            'contacto_id'               => ['nullable', 'integer'],
            'descripcion'               => ['nullable', 'string', 'max:1000'],
            'origen'                    => ['nullable', 'string', 'max:50'],
            'nivel_prioridad'           => ['nullable', 'string', 'max:50'],
            'tipo_condicion_proyecto'   => ['nullable', 'string', 'max:100'],
            'nivel_tentativo'           => ['nullable', 'string', 'max:50'],
            'motivo_compra'             => ['nullable', 'string', 'max:255'],
            'forma_pago'                => ['nullable', 'string', 'max:100'],
            'categoria_productos'       => ['nullable', 'string', 'max:100'],
            'usuario_apoyo_cotizacion'  => ['nullable', 'integer'],
            'monto_estimado'            => ['nullable', 'numeric'],
            'fecha_requerida'           => ['nullable', 'date'],
            'etapa'                     => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'msg'    => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Guardrail: un requerimiento no puede "nacer" en una etapa avanzada de
        // cotización. Esas etapas se alcanzan haciendo el flujo (cotizar, aprobar)
        // o moviendo la tarjeta en el tablero. Evita el estado inconsistente de un
        // requerimiento marcado como aprobado sin una cotización real detrás.
        $etapaCreacion = $request->input('etapa', 'Pendiente atender');
        $etapasPermitidasAlCrear = ['nuevo', 'Pendiente atender', 'En cotización'];
        if (!in_array($etapaCreacion, $etapasPermitidasAlCrear, true)) {
            return response()->json([
                'ok'     => false,
                'msg'    => 'No se puede crear un requerimiento directamente en la etapa "' . $etapaCreacion . '". Las etapas de cotización se alcanzan a través del flujo (cotizar y aprobar) o moviendo la tarjeta en el tablero.',
                'errors' => ['etapa' => ['Etapa no permitida al crear el requerimiento.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $req = new Requerimiento();
            $req->empresa_id = $request->input('empresa_id');
            $req->contacto_id = $request->input('contacto_id');
            $req->titulo = $request->input('titulo');
            $req->descripcion = $request->input('descripcion');
            $req->origen = $request->input('origen', 'manual');
            $req->nivel_prioridad = $request->input('nivel_prioridad', 'Medio');
            $req->tipo_condicion_proyecto = $request->input('tipo_condicion_proyecto');
            $req->motivo_compra = $request->input('motivo_compra');
            $req->forma_pago = $request->input('forma_pago');
            $req->nivel_tentativo = $request->input('nivel_tentativo');
            $req->categoria_productos = $request->input('categoria_productos');
            $req->usuario_apoyo_cotizacion = $request->input('usuario_apoyo_cotizacion');
            $req->monto_estimado = $request->input('monto_estimado');
            $req->fecha_requerida = $request->input('fecha_requerida');
            $req->etapa = $request->input('etapa', 'Pendiente atender');
            // El formulario permite crear el requerimiento ya en "Cotización aprobada".
            // Sin esto el flag quedaría en false y el botón "Crear proyecto desde
            // requerimiento" (que se muestra según el flag, no según la etapa) no
            // aparecería nunca. Mantener en sintonía con la misma lógica de update().
            if ($req->etapa === 'Cotización aprobada') {
                $req->cotizacion_aprobada = true;
                $req->fecha_aprobacion = now();
            }
            $req->estado = 'abierto';
            $req->activo_en_crm = true;
            $req->fecha_ingreso_etapa_actual = now();
            $req->usuario_ultima_interaccion = Auth::id();
            $req->fecha_ultima_interaccion = now();
            $req->save();

            // Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $req->id,
                'tipo_actividad'   => 'Creación',
                'detalle'          => 'Se creó el requerimiento',
                'etapa_anterior'   => null,
                'etapa_nueva'      => $req->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            DB::commit();

            return response()->json([
                'ok'  => true,
                'msg' => 'Requerimiento creado exitosamente.',
                'data' => [
                    'id'     => $req->id,
                    'codigo' => 'REQ-' . str_pad($req->id, 4, '0', STR_PAD_LEFT),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear requerimiento: ' . $e->getMessage());
            
            return response()->json([
                'ok'    => false,
                'msg'   => 'Error al crear el requerimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un requerimiento
     */
    public function update(Request $request, $id)
    {
        try {
            $req = Requerimiento::findOrFail($id);

            // Un requerimiento ya convertido a proyecto es de SOLO LECTURA: no se edita.
            if ($req->escalado_a_proyecto) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Este requerimiento ya fue convertido a proyecto: es de solo lectura y no puede editarse.',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'contacto_id'               => ['nullable', 'integer'],
                'titulo'                    => ['sometimes', 'string', 'max:191'],
                'descripcion'               => ['nullable', 'string', 'max:1000'],
                'origen'                    => ['nullable', 'string', 'max:50'],
                'nivel_prioridad'           => ['nullable', 'string', 'max:50'],
                'tipo_condicion_proyecto'   => ['nullable', 'string', 'max:100'],
                'nivel_tentativo'           => ['nullable', 'string', 'max:50'],
                'motivo_compra'             => ['nullable', 'string', 'max:255'],
                'forma_pago'                => ['nullable', 'string', 'max:100'],
                'categoria_productos'       => ['nullable', 'string', 'max:100'],
                'usuario_apoyo_cotizacion'  => ['nullable', 'integer'],
                'monto_estimado'            => ['nullable', 'numeric'],
                'fecha_requerida'           => ['nullable', 'date'],
                'etapa'                     => ['nullable', 'string', 'max:50'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok'     => false,
                    'msg'    => 'Datos inválidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Guardar valores anteriores para el historial
            $etapaAnterior = $req->etapa;

            // Actualizar campos
            $fillableFields = [
                'contacto_id', 'titulo', 'descripcion', 'origen', 'nivel_prioridad',
                'tipo_condicion_proyecto', 'nivel_tentativo', 'motivo_compra',
                'forma_pago', 'categoria_productos', 'usuario_apoyo_cotizacion',
                'monto_estimado', 'fecha_requerida', 'etapa'
            ];

            foreach ($fillableFields as $field) {
                if ($request->has($field)) {
                    $req->$field = $request->input($field);
                }
            }

            // Sincronizar el flag de cotización aprobada con la etapa: se ACTIVA
            // estando en "Cotización aprobada" y se DESACTIVA fuera de esa etapa
            // (p. ej. al pasar a "rechazada" o "en cotización"), para que el botón
            // "Crear proyecto" refleje el estado real y no quede activo para siempre.
            //
            // Se recalcula en CADA guardado, no solo cuando la etapa cambia: así un
            // requerimiento que quedó desincronizado se repara solo al editarlo. Antes
            // esto estaba condicionado a isDirty('etapa') y, como el formulario reenvía
            // la etapa sin cambios, las filas rotas seguían rotas.
            if ($req->etapa === 'Cotización aprobada') {
                // La fecha se sella al aprobarse (o si nunca se registró); no se pisa
                // en cada guardado posterior.
                if (!$req->cotizacion_aprobada || empty($req->fecha_aprobacion)) {
                    $req->fecha_aprobacion = now();
                }
                $req->cotizacion_aprobada = true;
            } else {
                $req->cotizacion_aprobada = false;
            }

            // Actualizar última interacción
            $req->usuario_ultima_interaccion = Auth::id();
            $req->fecha_ultima_interaccion = now();

            $req->save();

            // Registrar en historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $req->id,
                'tipo_actividad'   => 'Actualización',
                'detalle'          => 'Se actualizó el requerimiento',
                'etapa_anterior'   => $etapaAnterior,
                'etapa_nueva'      => $req->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            return response()->json([
                'ok'   => true,
                'msg'  => 'Requerimiento actualizado correctamente.',
                'data' => $req,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar requerimiento ' . $id . ': ' . $e->getMessage());
            
            return response()->json([
                'ok'    => false,
                'msg'   => 'Error al actualizar el requerimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar etapa
     */
    public function cambiarEtapa(Request $request, $id)
    {
        try {
            $req = Requerimiento::findOrFail($id);

            // No se puede cambiar la etapa de un requerimiento ya convertido a proyecto.
            if ($req->escalado_a_proyecto) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Este requerimiento ya fue convertido a proyecto: no se puede cambiar su etapa.',
                ], 422);
            }

            $request->validate([
                'etapa' => 'required|string',
            ]);

            $etapaAnterior = $req->etapa;
            $req->etapa = $request->etapa;
            // Sincronizar el flag de cotización aprobada con la etapa (activar al
            // entrar a "Cotización aprobada", desactivar al salir de esa etapa).
            if ($request->etapa === 'Cotización aprobada') {
                $req->cotizacion_aprobada = true;
                $req->fecha_aprobacion = now();
            } else {
                $req->cotizacion_aprobada = false;
            }
            $req->fecha_ingreso_etapa_actual = now();
            $req->save();

            // Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $req->id,
                'tipo_actividad'   => 'Cambio de etapa',
                'detalle'          => "Cambio de etapa: {$etapaAnterior} → {$request->etapa}",
                'etapa_anterior'   => $etapaAnterior,
                'etapa_nueva'      => $request->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            return response()->json([
                'ok'   => true,
                'msg'  => 'Etapa actualizada correctamente.',
                'data' => $req->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cambiar etapa: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 400);
        }
    }

    /**
     * Listar interacciones
     */
    public function interacciones($id)
    {
        try {
            $requerimiento = Requerimiento::findOrFail($id);

            $items = RequerimientoInteraccion::where('requerimiento_id', $id)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($row) {
                    $usuario = null;
                    if ($row->user_id) {
                        $usuario = User::find($row->user_id);
                    }

                    return [
                        'id'                   => $row->id,
                        'canal'                => $row->canal,
                        'resumen'              => $row->resumen,
                        'requiere_seguimiento' => $row->requiere_seguimiento,
                        'proximo_seguimiento'  => $row->proximo_seguimiento ? date('Y-m-d', strtotime($row->proximo_seguimiento)) : null,
                        'archivo_adjunto'      => $row->archivo_adjunto,
                        'user_id'              => $row->user_id,
                        'usuario'              => $usuario ? $usuario->name : 'Sin usuario',
                        'usuario_nombre'       => $usuario ? $usuario->name : 'Sin usuario',
                        'created_at'           => $row->created_at ? $row->created_at->format('Y-m-d H:i') : null,
                        'updated_at'           => $row->updated_at ? $row->updated_at->format('Y-m-d H:i') : null,
                    ];
                });

            return response()->json([
                'ok'   => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar interacciones: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar nueva interacción
     */
    public function storeInteraccion($id, Request $request)
    {
        try {
            $requerimiento = Requerimiento::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'canal'                => ['required', 'string', 'max:100'],
                'resumen'              => ['required', 'string'],
                'requiere_seguimiento' => ['nullable'],
                'proximo_seguimiento'  => ['nullable', 'date'],
                'archivo_adjunto'      => ['nullable', 'file', 'max:10240'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok'     => false,
                    'msg'    => 'Datos inválidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Archivo
            $archivoPath = null;
            if ($request->hasFile('archivo_adjunto')) {
                $archivoPath = $request->file('archivo_adjunto')
                    ->store('requerimientos/interacciones', 'public');
            }

            // Requiere seguimiento
            $requiereSeguimiento = false;
            $valor = $request->input('requiere_seguimiento');
            if ($valor === true || $valor === 'true' || $valor === 1 || $valor === '1') {
                $requiereSeguimiento = true;
            }

            // Crear interacción
            $interaccion = RequerimientoInteraccion::create([
                'requerimiento_id'     => $id,
                'canal'                => $request->input('canal'),
                'resumen'              => $request->input('resumen'),
                'requiere_seguimiento' => $requiereSeguimiento,
                'proximo_seguimiento'  => $request->input('proximo_seguimiento'),
                'archivo_adjunto'      => $archivoPath,
                'user_id'              => Auth::id(),
            ]);

            // Actualizar requerimiento
            $requerimiento->usuario_ultima_interaccion = Auth::id();
            $requerimiento->fecha_ultima_interaccion = now();
            
            if ($requiereSeguimiento && $request->input('proximo_seguimiento')) {
                $requerimiento->fecha_seguimiento = $request->input('proximo_seguimiento');
            }
            
            $requerimiento->save();

            // Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $id,
                'tipo_actividad'   => 'Nueva interacción',
                'detalle'          => "Interacción registrada por canal: {$request->input('canal')}",
                'etapa_anterior'   => $requerimiento->etapa,
                'etapa_nueva'      => $requerimiento->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            DB::commit();

            return response()->json([
                'ok'  => true,
                'msg' => 'Interacción registrada correctamente.',
                'data' => $interaccion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear interacción: ' . $e->getMessage());

            return response()->json([
                'ok'    => false,
                'msg'   => 'Error al registrar la interacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una interacción existente de un requerimiento
     */
    public function updateInteraccion($id, $interaccionId, Request $request)
    {
        try {
            $interaccion = RequerimientoInteraccion::where('requerimiento_id', $id)
                ->findOrFail($interaccionId);

            $validator = Validator::make($request->all(), [
                'canal'                => ['required', 'string', 'max:100'],
                'resumen'              => ['required', 'string'],
                'requiere_seguimiento' => ['nullable'],
                'proximo_seguimiento'  => ['nullable', 'date'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok'     => false,
                    'msg'    => 'Datos inválidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $requiereSeguimiento = false;
            $valor = $request->input('requiere_seguimiento');
            if ($valor === true || $valor === 'true' || $valor === 1 || $valor === '1') {
                $requiereSeguimiento = true;
            }

            $interaccion->canal                = $request->input('canal');
            $interaccion->resumen              = $request->input('resumen');
            $interaccion->requiere_seguimiento = $requiereSeguimiento;
            $interaccion->proximo_seguimiento  = $request->input('proximo_seguimiento');
            $interaccion->save();

            return response()->json([
                'ok'  => true,
                'msg' => 'Interacción actualizada correctamente.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar interacción: ' . $e->getMessage());
            return response()->json([
                'ok'  => false,
                'msg' => 'Error al actualizar la interacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar cotizaciones
     */
    public function cotizaciones($id)
    {
        try {
            $items = CotizacionRequerimiento::where('requerimiento_id', $id)
                ->orderBy('version', 'desc')
                ->get()
                ->map(function ($row) {
                    $usuario = null;
                    if ($row->usuario_id) {
                        $usuario = User::find($row->usuario_id);
                    }

                    return [
                        'id'            => $row->id,
                        'version'       => $row->version,
                        'archivo_url'   => $row->archivo_url,
                        'monto'         => $row->monto,
                        'estado'        => $row->estado,
                        'motivo_caida'  => $row->motivo_caida,
                        'observaciones' => $row->observaciones,
                        'usuario_id'    => $row->usuario_id,
                        'usuario'       => $usuario ? $usuario->name : 'Sin usuario',
                        'created_at'    => $row->created_at ? $row->created_at->format('Y-m-d H:i') : null,
                    ];
                });

            return response()->json([
                'ok'   => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar cotizaciones: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Subir cotización
     */
    public function subirCotizacion($id, Request $request)
    {
        try {
            $requerimiento = Requerimiento::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'archivo'       => ['required', 'file', 'mimes:pdf,xlsx,xls,docx,doc', 'max:20480'],
                'monto'         => ['required', 'numeric', 'min:0'],
                'observaciones' => ['nullable', 'string'],
                'estado'        => ['required', 'string'],
                'motivo_caida'  => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok'     => false,
                    'msg'    => 'Datos inválidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->estado === 'Caida' && empty($request->motivo_caida)) {
                return response()->json([
                    'ok'     => false,
                    'msg'    => 'El motivo de caída es requerido.',
                ], 422);
            }

            DB::beginTransaction();

            $archivoPath = $request->file('archivo')
                ->store('requerimientos/cotizaciones', 'public');

            $ultimaVersion = CotizacionRequerimiento::where('requerimiento_id', $id)->max('version');
            $nuevaVersion = ($ultimaVersion !== null) ? $ultimaVersion + 1 : 1;

            $cotizacion = CotizacionRequerimiento::create([
                'requerimiento_id' => $id,
                'version'          => $nuevaVersion,
                'archivo_url'      => $archivoPath,
                'monto'            => $request->monto,
                'estado'           => $request->estado,
                'motivo_caida'     => $request->motivo_caida,
                'observaciones'    => $request->observaciones,
                'usuario_id'       => Auth::id(),
            ]);

            // Actualizar etapa según estado (NO sobrescribir monto_estimado)
            $etapaAnterior = $requerimiento->etapa;
            
            if ($request->estado === 'Aprobada') {
                $requerimiento->cotizacion_aprobada = true;
                $requerimiento->fecha_aprobacion = now();
                $requerimiento->etapa = 'Cotización aprobada';
            } elseif ($request->estado === 'Enviada') {
                $requerimiento->cotizacion_aprobada = false;
                $requerimiento->etapa = 'Cotización enviada';
            } elseif ($request->estado === 'Recotizada') {
                $requerimiento->cotizacion_aprobada = false;
                $requerimiento->etapa = 'En Re-cotización';
            } elseif ($request->estado === 'Caida') {
                $requerimiento->cotizacion_aprobada = false;
                $requerimiento->etapa = 'Cotización caída';
            }

            $requerimiento->save();

            // Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $id,
                'tipo_actividad'   => 'Nueva cotización',
                'detalle'          => "Cotización v{$nuevaVersion} - Monto: S/ " . number_format($request->monto, 2) . " - Estado: {$request->estado}",
                'etapa_anterior'   => $etapaAnterior,
                'etapa_nueva'      => $requerimiento->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            DB::commit();

            return response()->json([
                'ok'  => true,
                'msg' => 'Cotización subida correctamente.',
                'data' => $cotizacion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al subir cotización: ' . $e->getMessage());

            return response()->json([
                'ok'    => false,
                'msg'   => 'Error al subir la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial
     */
    public function historial($id)
    {
        try {
            $items = HistoriaActividadRequerimiento::where('requerimiento_id', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($row) {
                    return [
                        'id'             => $row->id,
                        'tipo_actividad' => $row->tipo_actividad,
                        'detalle'        => $row->detalle,
                        'etapa_anterior' => $row->etapa_anterior,
                        'etapa_nueva'    => $row->etapa_nueva,
                        'usuario'        => $row->usuario,
                        'created_at'     => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return response()->json([
                'ok'   => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar historial: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Logs para Bitácora de cambios
     */
    public function logs($id)
    {
        try {
            $items = HistoriaActividadRequerimiento::where('requerimiento_id', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($row) {
                    return [
                        'id'         => $row->id,
                        'event'      => $row->tipo_actividad,
                        'tipo'       => $row->detalle ? substr($row->detalle, 0, 50) : null,
                        'usuario'    => $row->usuario ?: 'Sistema',
                        'created_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return response()->json([
                'ok'   => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar logs: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Kanban
     */
    public function kanban()
    {
        return view('comercial::requerimientos.kanban');
    }

    /**
     * Crear proyecto desde requerimiento
     */
    public function crearProyecto(Request $request, $id)
    {
        try {
            $req = Requerimiento::with(['empresa', 'contacto'])->findOrFail($id);

            if (!$req->cotizacion_aprobada) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Solo se pueden crear proyectos de requerimientos con cotización aprobada.',
                ], 400);
            }

            if ($req->escalado_a_proyecto) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'Este requerimiento ya fue escalado a proyecto.',
                ], 400);
            }

            // Bloquear si hay productos sin proveedor asignado en la pre-cotización.
            // Aunque la cotización esté aprobada por el cliente, no se puede crear el
            // proyecto si algún producto no tiene proveedor: el proyecto requiere el
            // proveedor de cada ítem para la gestión de compras posterior.
            $estadoProv = $this->contarProductosSinProveedor($req->id);
            if ($estadoProv['sin_proveedor'] > 0) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se puede crear el proyecto: hay ' . $estadoProv['sin_proveedor'] . ' de ' . $estadoProv['total'] . ' producto(s) sin proveedor asignado. Ve a la pestaña "Proveedores" y asigna un proveedor a cada producto antes de continuar.',
                ], 422);
            }

            // 3er requisito: la pre-cotizacion debe estar CONVERTIDA A COTIZACION FORMAL.
            // El flujo Validar -> Aprobar -> Convertir genera la cotizacion formal y deja
            // la PreQuote con quotation_id. Sin eso, no se permite crear el proyecto.
            $tieneCotizacionFormal = PreQuote::where('requerimiento_id', $req->id)
                ->whereNotNull('quotation_id')
                ->exists();
            if (!$tieneCotizacionFormal) {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se puede crear el proyecto: la pre-cotizacion aun no se ha convertido a Cotizacion Formal. Ve a la pestaña "Pre-Cotizacion", valida, aprueba y convierte a Cotizacion Formal antes de continuar.',
                ], 422);
            }

            DB::beginTransaction();

            // 1) Crear el proyecto
            $codigo = Proyecto::generarCodigo($req->empresa_id);

            $proyecto = new Proyecto();
            $proyecto->codigo = $codigo;
            $proyecto->empresa_id = $req->empresa_id;
            $proyecto->contacto_id = $req->contacto_id;
            $proyecto->requerimiento_id = $req->id;
            $proyecto->nombre = $req->titulo;
            $proyecto->descripcion = $req->descripcion;
            $proyecto->tipo = $req->categoria_productos;           // Categoria (heredada del requerimiento)
            $proyecto->prioridad = $req->tipo_condicion_proyecto;  // Tipo condicion (heredado del requerimiento)
            $proyecto->monto_total = $req->monto_estimado;   // Monto Estimado (referencial, heredado del requerimiento)

            // Monto Real Cotizacion: monto de la cotizacion aprobada del requerimiento
            // (la venta que el cliente acepto). Crear el proyecto exige cotizacion aprobada,
            // asi que normalmente existe; si no, queda null.
            $cotizacionAprobada = CotizacionRequerimiento::where('requerimiento_id', $req->id)
                ->where('estado', 'Aprobada')
                ->orderBy('version', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $proyecto->monto_costo = $cotizacionAprobada ? $cotizacionAprobada->monto : null;

            // Forma de Pago: se hereda la que el usuario eligio en el requerimiento (#3).
            $proyecto->forma_pago = $req->forma_pago;

            $proyecto->etapa = 'Nuevo';
            $proyecto->estado = 'activo';
            $proyecto->semaforo = 'Verde';
            $proyecto->ejecutivo_id = Auth::id();
            $proyecto->fecha_inicio = now()->format('Y-m-d');
            $proyecto->fecha_fin = $req->fecha_requerida ? $req->fecha_requerida->format('Y-m-d') : null;
            $proyecto->save();

            // 1.b) Copiar productos desde la última pre-cotización del requerimiento
            //      (si existe) a proyecto_productos. Así el proyecto arranca con los
            //      mismos ítems que se cotizaron, listos para edición.
            $this->copiarItemsPreCotizacionAProyecto($req->id, $proyecto->id);

            // 1.c) Autopoblar la Evaluacion de Proveedores (Post Venta) con los
            //      proveedores unicos de los productos del proyecto, listos para
            //      calificar (sin estrellas ni comentario). El boton "Cargar
            //      proveedores del proyecto" y "+ Agregar evaluacion" quedan para extras.
            $proveedoresProyecto = ProyectoProducto::where('proyecto_id', $proyecto->id)
                ->whereNotNull('supplier_id')
                ->get(['supplier_id', 'supplier_name']);
            $proveedoresVistos = [];
            foreach ($proveedoresProyecto as $prod) {
                $sid = (int) $prod->supplier_id;
                if ($sid <= 0 || in_array($sid, $proveedoresVistos, true)) {
                    continue;
                }
                $proveedoresVistos[] = $sid;
                ProyectoEvaluacionProveedor::create([
                    'proyecto_id'        => $proyecto->id,
                    'proveedor_id'       => $sid,
                    'proveedor_nombre'   => $prod->supplier_name,
                    'estrellas'          => 0,
                    'calificacion_label' => null,
                    'comentario'         => null,
                    'user_id'            => Auth::id(),
                ]);
            }

            // 2) Marcar requerimiento como escalado y moverlo a la etapa
            //    "Convertido a proyecto": se MANTIENE visible en el CRM (Kanban/Lista)
            //    en modo solo lectura, en vez de ocultarlo del CRM.
            $etapaAnteriorEscalado = $req->etapa;
            $req->escalado_a_proyecto = true;
            $req->fecha_escalado_proyecto = now();
            $req->etapa = 'Convertido a proyecto';
            $req->activo_en_crm = true;
            $req->fecha_ingreso_etapa_actual = now();
            $req->save();

            // 3) Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $req->id,
                'tipo_actividad'   => 'Escalado a proyecto',
                'detalle'          => "Proyecto {$codigo} creado desde este requerimiento",
                'etapa_anterior'   => $etapaAnteriorEscalado,
                'etapa_nueva'      => 'Convertido a proyecto',
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);

            DB::commit();

            return response()->json([
                'ok'       => true,
                'msg'      => "Proyecto {$codigo} creado exitosamente.",
                'redirect' => '/comercial/proyectos/listado',
                'data'     => [
                    'proyecto_id'     => $proyecto->id,
                    'proyecto_codigo' => $codigo,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear proyecto: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar usuarios activos para select
     */
    public function usuariosActivos(Request $request)
    {
        try {
            $query = User::query()
                ->select('id', 'name', 'email');

            // Filtrar por búsqueda
            if ($request->filled('q')) {
                $search = $request->input('q');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $usuarios = $query->orderBy('name')
                ->limit(50)
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                });

            return response()->json([
                'ok' => true,
                'data' => $usuarios,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar usuarios activos: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Calcular prioridad automáticamente
     */
    public function calcularPrioridad($id)
    {
        try {
            $requerimiento = Requerimiento::with('empresa')->findOrFail($id);
            
            $puntaje = 0;
            $desglose = [
                'monto' => 0,
                'trabajadores' => 0,
                'antiguedad' => 0,
                'frecuencia' => 0,
            ];
            
            // Factor 1: Monto estimado (0-3 puntos)
            $monto = $requerimiento->monto_estimado ?: 0;
            if ($monto >= 50000) {
                $desglose['monto'] = 3;
            } elseif ($monto >= 20000) {
                $desglose['monto'] = 2;
            } elseif ($monto >= 5000) {
                $desglose['monto'] = 1;
            }
            $puntaje += $desglose['monto'];
            
            // Factor 2: Empresa
            if ($requerimiento->empresa) {
                $desglose['trabajadores'] = 2;
                $desglose['antiguedad'] = 1;
                $puntaje += 3;
            }
            
            // Factor 3: Frecuencia
            if ($requerimiento->empresa_id) {
                $otrosRequerimientos = Requerimiento::where('empresa_id', $requerimiento->empresa_id)
                    ->where('id', '!=', $requerimiento->id)
                    ->count();
                if ($otrosRequerimientos > 0) {
                    $desglose['frecuencia'] = min(2, $otrosRequerimientos);
                    $puntaje += $desglose['frecuencia'];
                }
            }
            
            // Determinar nivel
            $nivel = 'Bajo';
            if ($puntaje >= 8) {
                $nivel = 'Muy alto';
            } elseif ($puntaje >= 5) {
                $nivel = 'Alto';
            } elseif ($puntaje >= 3) {
                $nivel = 'Medio';
            }
            
            $requerimiento->nivel_prioridad = $nivel;
            $requerimiento->save();
            
            // Historial
            HistoriaActividadRequerimiento::create([
                'requerimiento_id' => $requerimiento->id,
                'tipo_actividad'   => 'Cálculo de prioridad',
                'detalle'          => "Prioridad calculada: {$nivel} ({$puntaje}/10 puntos)",
                'etapa_anterior'   => $requerimiento->etapa,
                'etapa_nueva'      => $requerimiento->etapa,
                'usuario'          => Auth::user() ? Auth::user()->name : 'Sistema',
            ]);
            
            return response()->json([
                'ok'   => true,
                'msg'  => 'Prioridad calculada exitosamente',
                'data' => [
                    'nivel'    => $nivel,
                    'puntaje'  => $puntaje,
                    'desglose' => $desglose,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al calcular prioridad: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Copia los ítems de la última pre-cotización del requerimiento al
     * proyecto recién creado (tabla proyecto_productos).
     *
     * Se prefiere la pre-cotización con estado 'aprobada'/'validada' más reciente.
     * Si no hay pre-cotización con ítems, no hace nada (no es un error).
     *
     * Mapping PreQuoteItem → ProyectoProducto:
     *   product_name (+ product_description)  → descripcion
     *   supplier_id / supplier_name             → supplier_id / supplier_name
     *   quantity                                → cantidad
     *   valor_unitario                          → precio_unitario
     *   valor_total                             → subtotal
     *   observaciones                           → observaciones
     *
     * @param  int  $requerimientoId
     * @param  int  $proyectoId
     * @return int  cantidad de ítems copiados
     */
    private function copiarItemsPreCotizacionAProyecto($requerimientoId, $proyectoId)
    {
        try {
            // Buscar la pre-cotización que alimenta el proyecto (la misma que se usa
            // en la validación de proveedores, para mantener total consistencia).
            $prequote = $this->getPrequoteParaProyecto($requerimientoId);

            if (!$prequote) {
                return 0;
            }

            $items = PreQuoteItem::where('prequote_id', $prequote->id)
                ->orderBy('item_order')
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                return 0;
            }

            $copiados = 0;
            foreach ($items as $it) {
                $descripcion = $it->product_name;
                if (!empty($it->product_description)) {
                    $descripcion .= ' — ' . $it->product_description;
                }
                // Limitar a 500 chars (max de la columna)
                if (mb_strlen($descripcion) > 500) {
                    $descripcion = mb_substr($descripcion, 0, 497) . '...';
                }

                // Costos operativos (empaque efectivo, movilidad, SCTR derivado) que el
                // proyecto hereda como SOLO LECTURA de la pre-cotizacion (#4).
                $op = $this->costosOperativosDeItem($it, $prequote);

                $producto = ProyectoProducto::create([
                    'proyecto_id'      => $proyectoId,
                    'item_id'          => $it->item_id,
                    'supplier_id'      => $it->supplier_id,
                    'supplier_name'    => $it->supplier_name,
                    'descripcion'      => $descripcion,
                    // Especificaciones tecnicas heredadas de la pre-cotizacion (snapshot).
                    'material'          => $it->material,
                    'medida'            => $it->medida,
                    'tecnica_impresion' => $it->tecnica_impresion,
                    'cantidad_colores'  => $it->cantidad_colores,
                    'cantidad_lados'    => $it->cantidad_lados,
                    'empaque'           => $it->empaque,
                    'personalizacion'   => $it->personalizacion,
                    // Costos operativos heredados de la pre-cotizacion (solo lectura en el proyecto).
                    'costo_empaque'     => $op['empaque'],
                    'costo_movilidad'   => $op['movilidad'],
                    'costo_sctr'        => $op['sctr'],
                    'cantidad'         => $it->quantity ?: 1,
                    'muestras'         => $it->numero_muestras ?: 0,
                    'precio_unitario'  => $it->valor_unitario,
                    'subtotal'         => $it->valor_total,
                    'observaciones'    => $it->observaciones,
                ]);

                // Proveedor inicial (M2): sembrado con el costo base de la pre-cotizacion
                // (condicion 'exempt' = sin ajuste de IGV). El usuario lo ajusta luego con
                // las cotizaciones reales de los proveedores durante la ejecucion.
                $seedCant     = $it->quantity ?: 1;
                $seedPrecio   = (float) $it->costo_base;
                $seedSubtotal = round($seedPrecio * $seedCant, 2);
                $producto->proveedores()->create([
                    'supplier_id'               => $it->supplier_id,
                    'supplier_name'             => $it->supplier_name,
                    'concepto'                  => $it->product_name,
                    'cantidad'                  => $seedCant,
                    'precio_unitario_informado' => $seedPrecio,
                    'condicion_igv'             => 'exempt',
                    'precio_unitario_neto'      => $seedPrecio,
                    'subtotal_neto'             => $seedSubtotal,
                    'igv_monto'                 => 0,
                    'total_pagar'               => $seedSubtotal,
                    'activo'                    => 1,
                    'orden'                     => 0,
                ]);
                $copiados++;
            }

            return $copiados;
        } catch (\Throwable $e) {
            Log::warning('No se pudieron copiar items de pre-cotización al proyecto', [
                'requerimiento_id' => $requerimientoId,
                'proyecto_id'      => $proyectoId,
                'error'            => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Costos operativos de un ítem de pre-cotización que el proyecto hereda como
     * SOLO LECTURA (empaque/movilidad/SCTR, todos como TOTALES del ítem, #4):
     *   - empaque: efectivo (× cantidad si es "por unidad").
     *   - movilidad: fija.
     *   - SCTR: el monto no se persiste en la pre-cotización, pero `valor_total`
     *     ya lo incluye (valor_total = base + SCTR), así que se deriva como
     *     valor_total × pct/(100+pct). El % efectivo se resuelve por CASCADA
     *     ítem → cotización → global (igual que QuotationCalculationEngine).
     *     Derivarlo de valor_total lo hace robusto a cambios de la fórmula de costeo.
     *
     * MANTENER EN SYNC con la copia gemela en ProyectosController::costosOperativosDeItem().
     *
     * @return array
     */
    private function costosOperativosDeItem($it, $prequote)
    {
        $q = max(1, (int) $it->quantity);
        $empaque = $it->empaque_por_unidad ? round((float) $it->costo_empaque * $q, 2) : (float) $it->costo_empaque;
        $movilidad = (float) $it->costo_movilidad;

        if ($it->sctr_porcentaje !== null && $it->sctr_porcentaje !== '') {
            $pct = (float) $it->sctr_porcentaje;
        } elseif ($prequote && $prequote->sctr_porcentaje !== null && $prequote->sctr_porcentaje !== '') {
            $pct = (float) $prequote->sctr_porcentaje;
        } else {
            $pct = (float) \Modules\Marketing\Models\QuotationConfig::getValue('sctr_porcentaje', 1.5);
        }

        $valorTotal = (float) $it->valor_total;
        $sctr = ($pct > 0 && $valorTotal > 0) ? round($valorTotal * $pct / (100 + $pct), 2) : 0;

        return ['empaque' => $empaque, 'movilidad' => $movilidad, 'sctr' => $sctr];
    }

    /**
     * Devuelve la pre-cotización que alimenta el proyecto: la más reciente del
     * requerimiento, priorizando estado 'aprobada' > 'validada' > 'borrador',
     * luego mayor versión e id. Centralizado aquí para que la copia de ítems y
     * la validación de proveedores usen exactamente la misma.
     *
     * @param  int  $requerimientoId
     * @return \Modules\Marketing\Models\PreQuote|null
     */
    private function getPrequoteParaProyecto($requerimientoId)
    {
        // FIELD() lista los estados de menor a mayor prioridad y ordenamos DESC:
        // 'aprobada' gana a 'validada', y ambas a 'borrador'. Un estado no listado
        // devuelve 0 en FIELD(), por lo que con DESC queda al final (nunca se elige
        // por error sobre uno aprobado). Luego desempata por mayor versión e id.
        return PreQuote::where('requerimiento_id', $requerimientoId)
            ->orderByRaw("FIELD(estado, 'borrador','validada','aprobada') DESC")
            ->orderBy('version', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Cuenta los productos de la pre-cotización que alimenta el proyecto y cuántos
     * de ellos NO tienen proveedor asignado. Se usa para (a) bloquear la creación
     * del proyecto cuando falta algún proveedor y (b) informar al frontend para
     * deshabilitar el botón con el motivo.
     *
     * @param  int  $requerimientoId
     * @return array  ['total' => int, 'sin_proveedor' => int]
     */
    private function contarProductosSinProveedor($requerimientoId)
    {
        $prequote = $this->getPrequoteParaProyecto($requerimientoId);

        if (!$prequote) {
            return ['total' => 0, 'sin_proveedor' => 0];
        }

        $total = PreQuoteItem::where('prequote_id', $prequote->id)->count();

        $sinProveedor = PreQuoteItem::where('prequote_id', $prequote->id)
            ->where(function ($q) {
                $q->whereNull('supplier_id')->orWhere('supplier_id', 0);
            })
            ->count();

        return ['total' => $total, 'sin_proveedor' => $sinProveedor];
    }
}
