<?php
/**
 * ============================================================================
 * ProyectosController.php
 * ============================================================================
 * Ubicación: Modules/Comercial/Http/Controllers/ProyectosController.php
 *
 * Controlador completo para el submódulo Proyectos.
 * Provee todos los endpoints que consumen index.vue y form.vue.
 * ============================================================================
 */

namespace Modules\Comercial\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Comercial\Models\Proyecto;
use Modules\Comercial\Models\ProyectoProducto;
use Modules\Comercial\Models\ProyectoBitacora;
use Modules\Comercial\Models\ProyectoRazonSocial;
use Modules\Comercial\Models\ProyectoEvaluacionProveedor;
use Modules\Comercial\Models\Empresa;
use Modules\Comercial\Models\Contacto;
use Modules\Comercial\Models\Requerimiento;
use Modules\Comercial\Models\CotizacionRequerimiento;
use App\Models\Tenant\User;
use DB;

class ProyectosController extends Controller
{
    /**
     * Agosto 2026 (punto 19) · Plantilla de "Observaciones / Preocupaciones del
     * Cliente" en los productos del Proyecto.
     *
     * Texto del cliente: "En Productos, precargar una plantilla en Observaciones /
     * Preocupaciones del Cliente, respetando separaciones y negritas. El texto que
     * escriba el usuario no debe heredar esas características."
     *
     * Los ocho encabezados son los que el cliente especificó en la página 11 de
     * "MODULO REQUERIMIENTO - PROYECTOS - CAJAS-GUIAREMISION.pdf", palabra por
     * palabra y en ese orden. No son una propuesta nuestra.
     *
     * (La primera versión de este punto llevaba cuatro encabezados inventados por
     * nosotros, porque el requerimiento de agosto no decía qué debía contener la
     * plantilla y ese otro documento no estaba a la vista. El cliente lo corrigió
     * señalando la página exacta.)
     *
     * Una línea por encabezado, sin líneas en blanco entre ellos: así se ve en la
     * captura del propio documento, y a eso apunta el "respetando separaciones".
     *
     * Los `**` son los mismos marcadores del punto 11 (ver
     * Modules\Marketing\Support\TextoEnriquecido). Que se usen marcadores y no un
     * editor visual es justamente lo que cumple la segunda mitad del requisito
     * —"el texto que escriba el usuario no será texto negrita"—: al escribir
     * DESPUÉS de los dos puntos, fuera de los asteriscos, lo tecleado queda
     * necesariamente plano. No es algo que haya que resolver, es una consecuencia
     * de la forma en que se guarda el formato.
     */
    const PLANTILLA_OBSERVACIONES_PRODUCTO = "**Tipo de proveedor:**\n**Apreciaciones del proveedor:**\n**Criterios a considerar del producto:**\n**Preocupaciones del cliente:**\n**Present. Indiv:**\n**Lleva rotulado especial:**\n**Adicionales:**\n**MUESTRA PATRON:**\n";

    /**
     * Devuelve las observaciones del producto, o la plantilla si están vacías.
     *
     * La plantilla NO pisa nunca un texto existente: el campo ya hereda las
     * observaciones de la pre-cotización, y reemplazarlas borraría información
     * que el cliente dio al cotizar.
     *
     * @param  string|null $observaciones
     * @return string
     */
    private function observacionesConPlantilla($observaciones)
    {
        return trim((string) $observaciones) !== ''
            ? (string) $observaciones
            : self::PLANTILLA_OBSERVACIONES_PRODUCTO;
    }

    /**
     * Vista principal (Blade que monta el Vue)
     */
    public function index()
    {
        return view('comercial::proyectos.index');
    }

    /**
     * GET /comercial/proyectos/records
     * Lista de proyectos con filtros para la tabla del index.vue
     */
    public function records(Request $request)
    {
        $query = Proyecto::with(['empresa', 'requerimiento'])
            ->select('proyecto.*');

        // Filtro búsqueda general
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                  ->orWhere('nombre', 'like', "%{$search}%")
                  ->orWhereHas('empresa', function ($qe) use ($search) {
                      $qe->where('razon_social', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por etapa
        if ($request->get('etapa') && $request->get('etapa') !== 'Todos') {
            $query->where('etapa', $request->get('etapa'));
        }

        // Filtro por semáforo
        if ($request->get('semaforo') && $request->get('semaforo') !== 'Todos') {
            $query->where('semaforo', $request->get('semaforo'));
        }

        // Filtro por estado
        if ($request->get('estado') && $request->get('estado') !== 'Todos') {
            $query->where('estado', $request->get('estado'));
        }

        // Ordenamiento: última actualización primero (recién creados/editados arriba).
        $proyectos = $query->orderBy('updated_at', 'desc')
                           ->orderBy('id', 'desc')
                           ->get();

        return $proyectos->map(function ($p) {
            return [
                'id'                    => $p->id,
                'codigo'                => $p->codigo,
                'nombre'                => $p->nombre,
                'empresa_razon_social'  => $p->empresa ? $p->empresa->razon_social : '',
                'etapa'                 => $p->etapa,
                'semaforo'              => $p->semaforo,
                'monto_total'           => $p->monto_total,
                'costo_total_proyecto'  => $p->costo_total_proyecto,
                'porcentaje_avance'     => $p->porcentaje_avance,
                'ejecutivo_nombre'      => $p->ejecutivo_id ? optional(User::find($p->ejecutivo_id))->name : '',
                'fecha_fin'             => $p->fecha_fin ? $p->fecha_fin->format('Y-m-d') : null,
                'esta_vencido'          => $p->fecha_fin && $p->fecha_fin->isPast() && $p->estado === 'activo',
                'oc_numero'             => $p->oc_numero,
                'estado'                => $p->estado,
            ];
        });
    }

    /**
     * GET /comercial/proyectos/kpis
     * KPIs para los cards del index.vue
     */
    public function kpis()
    {
        $activos = Proyecto::where('estado', 'activo')->count();

        return response()->json([
            'activos'            => $activos,
            'en_produccion'      => Proyecto::where('estado', 'activo')->where('etapa', 'En producción')->count(),
            'semaforo_rojo'      => Proyecto::where('estado', 'activo')->where('semaforo', 'Rojo')->count(),
            'semaforo_amarillo'  => Proyecto::where('estado', 'activo')->where('semaforo', 'Amarillo')->count(),
            'pendientes_cobro'  => Proyecto::where('estado', 'activo')
                                    ->whereIn('etapa', ['Entregado', 'Pendiente de pago'])
                                    ->count(),
            // Suma el costo real del proyecto (M4); si aún no se calculó, cae al monto_total viejo.
            'monto_total'        => Proyecto::where('estado', 'activo')->sum(DB::raw('COALESCE(costo_total_proyecto, monto_total)')),
        ]);
    }

    /**
     * GET /comercial/proyectos/tables
     * Datos para los selects del formulario
     */
    public function tables()
    {
        return response()->json([
            'empresas' => Empresa::orderBy('razon_social')
                ->get(['id', 'razon_social']),

            'ejecutivos' => User::where('type', '!=', 'admin')
                ->orWhere('type', 'admin')
                ->orderBy('name')
                ->get(['id', 'name']),

            'etapas' => [
                'Nuevo',
                'En curso / Pre-producción',
                'En producción',
                'Final producción',
                'En reproceso',
                'Entregado',
                'Pendiente de pago',
                'Terminado',
            ],

            // Categoría del proyecto (alineada con Categoría de Requerimientos)
            'tipos_proyecto' => [
                'KITS CORPORATIVOS',
                'M. IMPORTADOS',
                'M. NACIONAL',
                'TEXTIL',
                'IMPRENTA',
                'WEB/MAIL',
                'PUB. EXTERIOR',
                'SERV DISEÑO',
                'UPCYCLING',
                'OTROS',
            ],

            // Tipo condición (alineado con Tipo condición de Requerimientos)
            'prioridades' => ['Estándar', 'Importante', 'Urgente', 'Muy urgente'],

            // Actualizado 2026-05-28: alineado con Requerimientos_27_05_26.pdf
            // página 6 del cliente. Misma lista se usa en Requerimientos (frontend).
            'formas_pago' => [
                '50% adelanto / 50% contra entrega',
                '50% adelanto / 50% crédito',
                '100% contra entrega',
                'Al Contado',
                'Crédito 5 días',
                'Crédito 7 días',
                'Crédito 15 días',
                'Crédito 20 días',
                'Crédito 30 días',
                'Crédito 45 días',
                'Crédito 60 días',
                'Crédito 90 días',
                'Crédito 120 días',
                'Factura negociable',
                'Factoring',
                'Cheque',
                'Otros',
            ],

            // Días de crédito permitidos para formas de pago mixtas (adelanto + crédito)
            'dias_credito_opciones' => [5, 7, 9, 15],

            // Opciones del select "Se tiene OC" por razón social (en Facturación)
            'tiene_oc_razon_social' => [
                'Pendiente',
                'No Aplica',
                'Se tiene',
            ],

            // Actualizado 2026-04-06 · agosto 2026 (punto 21): se suman los dos
            // últimos, que cubren escenarios que hoy se dan en la operación.
            'tipos_entrega' => [
                'ALMACEN',
                'EDIFICIO CORPORATIVO',
                'AGENCIA (PROV)',
                'ESPECIAL',
                'OFICINA PROPIA CLIENTE',
                'OFICINA COMPINA',
                'CASA CLIENTE',
                'RECOJO POR EL CLIENTE',
                'OTROS',
            ],

            // Agosto 2026 (punto 21): se suma "Otros".
            'tamanos_movilidad' => [
                'Moto',
                'Auto',
                'Camioneta',
                'Minivan',
                'Camión pequeño',
                'Camión grande',
                'Otros',
            ],
        ]);
    }

    /**
     * GET /comercial/proyectos/record/{id}
     * Obtener un proyecto con todas sus relaciones para el form.vue
     */
    public function record($id)
    {
        $proyecto = Proyecto::with(['empresa', 'requerimiento', 'productos', 'razonesSociales', 'evaluacionesProveedor'])
            ->findOrFail($id);

        $data = $proyecto->toArray();

        // Datos de la empresa
        $data['empresa_razon_social'] = $proyecto->empresa ? $proyecto->empresa->razon_social : null;

        // Datos del contacto
        $contacto = $proyecto->contacto_id ? Contacto::find($proyecto->contacto_id) : null;
        $data['contacto_nombre'] = $contacto
            ? trim(($contacto->nombres ?: '') . ' ' . ($contacto->apellidos ?: ''))
            : null;

        // Datos del requerimiento
        if ($proyecto->requerimiento) {
            $data['requerimiento_codigo'] = $proyecto->requerimiento->codigo ?: ('REQ-' . $proyecto->requerimiento->id);
            $data['requerimiento_monto_estimado'] = $proyecto->requerimiento->monto_estimado;
            $data['requerimiento_fecha_requerida'] = $proyecto->requerimiento->fecha_requerida
                ? $proyecto->requerimiento->fecha_requerida->format('Y-m-d') : null;

            // Monto real: tomar de la cotizacion APROBADA del requerimiento (la que el
            // cliente acepto). El "Monto Real Cotizacion" refleja la venta cerrada; si no
            // hay ninguna cotizacion aprobada queda vacio ("Sin cotizacion"). No se usa la
            // pre-cotizacion ni la ultima cotizacion sin aprobar como sustituto.
            $cotizacionAprobada = CotizacionRequerimiento::where('requerimiento_id', $proyecto->requerimiento_id)
                ->where('estado', 'Aprobada')
                ->orderBy('version', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $data['requerimiento_monto_real'] = $cotizacionAprobada ? $cotizacionAprobada->monto : null;

            // ── Agosto 2026 (punto 16) · Cotización de origen ──
            //
            // "Cambiar 'Requerimiento origen' por 'Cotización de origen', mostrar
            //  el código MAP de la cotización aprobada que dio origen al proyecto
            //  y agregar un botón para ver su PDF."
            //
            // El código MAP vive en `quotation_proposals.numero_cotizacion`, la
            // propuesta formal que genera el cotizador. Se llega a ella desde el
            // requerimiento a través de la pre-cotización.
            //
            // PRIORIDAD: la aprobada por el cliente primero, como pide el punto.
            //
            // No se exige que exista, porque el proyecto puede haber nacido por un
            // camino que no pasa por ahí: en la pestaña Pre-Cotización se puede
            // apretar "Aprobar" directamente sobre la pre-cotización y convertir,
            // sin que ninguna propuesta llegue a `aprobada_cliente`. Con el filtro
            // estricto, esos proyectos —que son muchos— mostrarían el campo vacío y
            // el punto quedaría sin efecto en la práctica.
            //
            // Por eso el orden es aprobada > enviada > emitida, y las RECHAZADAS
            // quedan excluidas: mostrar una cotización que el cliente rechazó como
            // origen del proyecto sí sería un error. Entre varias del mismo estado
            // gana la más reciente.
            $proposalOrigen = \Modules\Marketing\Models\QuotationProposal::whereHas(
                    'prequote',
                    function ($q) use ($proyecto) {
                        $q->where('requerimiento_id', $proyecto->requerimiento_id);
                    }
                )
                ->where('estado', '!=', \Modules\Marketing\Models\QuotationProposal::ESTADO_RECHAZADA_CLIENTE)
                ->orderByRaw("FIELD(estado, 'borrador', 'emitida', 'enviada_cliente', 'aprobada_cliente') DESC")
                ->orderBy('id', 'desc')
                ->first();

            $data['cotizacion_origen_codigo'] = $proposalOrigen
                ? $proposalOrigen->numero_cotizacion
                : null;

            // La URL se arma con PublicUrl y no con Storage::url(): ese helper la
            // deriva de APP_URL, y con el .env mal armado el navegador no puede
            // abrir el PDF. Es el mismo motivo por el que se corrigió el
            // "Previsualizar PDF" del cotizador.
            $data['cotizacion_origen_pdf_url'] = ($proposalOrigen && !empty($proposalOrigen->pdf_path))
                ? \Modules\Marketing\Support\PublicUrl::forDisk('public', $proposalOrigen->pdf_path)
                : null;
        } else {
            $data['requerimiento_codigo'] = null;
            $data['requerimiento_monto_estimado'] = null;
            $data['requerimiento_monto_real'] = null;
            $data['requerimiento_fecha_requerida'] = null;
            $data['cotizacion_origen_codigo'] = null;
            $data['cotizacion_origen_pdf_url'] = null;
        }

        // Contactos de facturación (JSON guardado en campo text)
        $data['contactos_facturacion'] = $proyecto->contactos_facturacion
            ? (is_string($proyecto->contactos_facturacion)
                ? json_decode($proyecto->contactos_facturacion, true)
                : $proyecto->contactos_facturacion)
            : [];

        // Razones sociales
        $data['razones_sociales'] = $proyecto->razonesSociales ? $proyecto->razonesSociales->toArray() : [];

        // COM-02 · Código de artículo con el que se cotizó cada producto, para
        // mostrarlo en la pestaña Productos del proyecto (solo lectura). Los
        // productos ya guardados no lo tienen en su propia tabla, así que se
        // resuelve contra la pre-cotización del requerimiento origen.
        $codigosArticulo = !empty($proyecto->requerimiento_id)
            ? $this->codigosArticuloDePrequote($proyecto->requerimiento_id)
            : ['porItemId' => [], 'porNombre' => []];

        // Agosto 2026 (punto 20) · Tiempo de Producción por proveedor. Hereda el
        // plazo del proveedor desde la pre-cotización como valor inicial.
        $tiemposProduccion = !empty($proyecto->requerimiento_id)
            ? $this->tiemposProduccionDePrequote($proyecto->requerimiento_id)
            : ['porItemId' => [], 'porNombre' => []];

        // Productos con sus proveedores (M2). Para productos antiguos sin filas de
        // proveedor, se sintetiza uno desde el proveedor/precio heredado para que la
        // subtabla no aparezca vacía (al guardar queda persistido como fila real).
        $proyecto->load(['productos.proveedores']);
        $productos = $proyecto->productos->map(function ($p) use ($codigosArticulo, $tiemposProduccion) {
            $arr = $p->toArray();
            $arr['codigo_articulo'] = $this->resolverCodigoArticulo($p, $codigosArticulo);
            // Agosto 2026 (punto 19): los productos ya guardados que quedaron con el
            // campo vacío también reciben la plantilla, así el equipo de producción
            // encuentra la misma estructura en todos los proyectos y no solo en los
            // creados después de este cambio.
            $arr['observaciones'] = $this->observacionesConPlantilla($p->observaciones);

            // Plazo heredado del requerimiento para ESTE producto.
            $tiempoHeredado = $this->resolverDeIndicesDePrequote($p, $tiemposProduccion);

            if (empty($arr['proveedores'])) {
                $arr['proveedores'] = [[
                    'id'                        => null,
                    'supplier_id'               => $p->supplier_id,
                    'supplier_name'             => $p->supplier_name,
                    'concepto'                  => $p->descripcion,
                    'tiempo_produccion'          => $tiempoHeredado,
                    'cantidad'                  => $p->cantidad,
                    'precio_unitario_informado' => $p->precio_unitario ?: 0,
                    'condicion_igv'             => 'exempt',
                    'activo'                    => true,
                    'orden'                     => 0,
                ]];
            } else {
                // Agosto 2026 (punto 20) — la herencia solo rellena lo que está
                // VACÍO. Es la diferencia entre "heredado" y "pisado": una vez que
                // el usuario escribió su plazo, recargar la pantalla no puede
                // devolverlo al de la pre-cotización. Por eso no se compara contra
                // el valor heredado ni se sobrescribe nunca un valor ya guardado.
                //
                // Efecto lateral aceptado: si el usuario BORRA el campo y guarda,
                // al recargar vuelve a mostrarse el heredado. Evitarlo exigiría una
                // segunda columna del tipo "ya fue editado", que no justifica el
                // costo para este caso.
                foreach ($arr['proveedores'] as $i => $prov) {
                    $actual = isset($prov['tiempo_produccion']) ? trim((string) $prov['tiempo_produccion']) : '';
                    if ($actual === '') {
                        $arr['proveedores'][$i]['tiempo_produccion'] = $tiempoHeredado;
                    }
                }
            }
            return $arr;
        })->all();

        // Fallback: si el proyecto NO tiene productos propios pero proviene de un
        // requerimiento con pre-cotización, mostrar los ítems de la pre-cotización
        // como referencia. El usuario puede editarlos y al guardar quedan persistidos
        // en proyecto_productos.
        if (empty($productos) && !empty($proyecto->requerimiento_id)) {
            $productos = $this->fetchItemsFromPrequote($proyecto->requerimiento_id);
        }

        $data['productos'] = $productos;

        // Evaluaciones de proveedor (post-venta)
        $data['evaluaciones_proveedor'] = $proyecto->evaluacionesProveedor
            ? $proyecto->evaluacionesProveedor->map(function ($e) {
                return [
                    'id'                  => $e->id,
                    'proveedor_id'        => $e->proveedor_id,
                    'proveedor_nombre'    => $e->proveedor_nombre,
                    'estrellas'           => $e->estrellas,
                    'calificacion_label'  => $e->calificacion_label,
                    'calificacion_texto'  => $e->calificacion_texto,
                    'comentario'          => $e->comentario,
                    'created_at'          => optional($e->created_at)->format('Y-m-d H:i:s'),
                    'updated_at'          => optional($e->updated_at)->format('Y-m-d H:i:s'),
                ];
            })->all()
            : [];

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Costos operativos de un ítem de pre-cotización que el proyecto hereda como
     * SOLO LECTURA (empaque efectivo, movilidad, SCTR derivado de valor_total; #4).
     * Ver el detalle en RequerimientosController::costosOperativosDeItem().
     * MANTENER EN SYNC con esa copia gemela.
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
     * Devuelve los ítems de la última pre-cotización del requerimiento
     * mapeados al formato de proyecto_productos (read-only en BD, pero el frontend
     * los puede editar y al guardar se persisten en la tabla del proyecto).
     *
     * @param  int  $requerimientoId
     * @return array
     */
    /**
     * Pre-cotización que representa al requerimiento a efectos del proyecto.
     *
     * ⚠️ Debe elegir EXACTAMENTE la misma que
     * RequerimientosController::getPrequoteParaProyecto() (ver detalle allí):
     * 'aprobada' > 'validada' > 'borrador'; DESC deja al final cualquier estado
     * no listado (FIELD()=0). Mantener ambas queries en sincronía.
     *
     * @param  int  $requerimientoId
     * @return \Modules\Marketing\Models\PreQuote|null
     */
    private function prequoteDelRequerimiento($requerimientoId)
    {
        return \Modules\Marketing\Models\PreQuote::where('requerimiento_id', $requerimientoId)
            ->orderByRaw("FIELD(estado, 'borrador','validada','aprobada') DESC")
            ->orderBy('version', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * COM-02 · Códigos de artículo de la pre-cotización, para mostrarlos en los
     * productos del proyecto.
     *
     * Los productos ya guardados en `proyecto_productos` no llevan el código en su
     * propia tabla, así que se resuelve contra la pre-cotización. Se devuelven dos
     * índices: por `item_id` del catálogo (exacto) y por nombre del producto
     * (respaldo para los ítems ad-hoc, que no tienen `item_id`).
     *
     * @param  int  $requerimientoId
     * @return array  ['porItemId' => [id => codigo], 'porNombre' => [nombre => codigo]]
     */
    private function codigosArticuloDePrequote($requerimientoId)
    {
        $porItemId = [];
        $porNombre = [];

        try {
            $prequote = $this->prequoteDelRequerimiento($requerimientoId);
            if (!$prequote) {
                return ['porItemId' => $porItemId, 'porNombre' => $porNombre];
            }

            $items = \Modules\Marketing\Models\PreQuoteItem::where('prequote_id', $prequote->id)->get();
            foreach ($items as $it) {
                // `codigo_articulo` es el EFECTIVO del ítem: manual → catálogo → ''.
                $codigo = (string) $it->codigo_articulo;
                if ($codigo === '') {
                    continue;
                }
                if (!empty($it->item_id) && !isset($porItemId[$it->item_id])) {
                    $porItemId[$it->item_id] = $codigo;
                }
                $clave = mb_strtolower(trim((string) $it->product_name));
                if ($clave !== '' && !isset($porNombre[$clave])) {
                    $porNombre[$clave] = $codigo;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Error obteniendo códigos de artículo de la pre-cotización', [
                'requerimiento_id' => $requerimientoId,
                'error'            => $e->getMessage(),
            ]);
        }

        return ['porItemId' => $porItemId, 'porNombre' => $porNombre];
    }

    /**
     * Agosto 2026 (punto 20) · Tiempos de PRODUCCIÓN de la pre-cotización, para
     * sembrar el plazo de los proveedores del proyecto.
     *
     * Devuelve los mismos dos índices que codigosArticuloDePrequote() —por
     * `item_id` del catálogo y por nombre del producto— porque el emparejamiento
     * es idéntico: los ítems ad-hoc no tienen `item_id` y hay que caer al nombre.
     *
     * Se lee `tiempo_estimado_dias`, que es lo que tarda el PROVEEDOR. En el
     * proyecto, el bloque "Proveedores y costos del producto" habla de lo que cada
     * proveedor tarda en entregarle el producto a COMPIPRO; el plazo que se le
     * promete al CLIENTE es otro y vive en la cotización
     * (`quotation_prequote_items.tiempo_entrega`, punto 9).
     *
     * @param  int  $requerimientoId
     * @return array  ['porItemId' => [id => tiempo], 'porNombre' => [nombre => tiempo]]
     */
    private function tiemposProduccionDePrequote($requerimientoId)
    {
        $porItemId = [];
        $porNombre = [];

        try {
            $prequote = $this->prequoteDelRequerimiento($requerimientoId);
            if (!$prequote) {
                return ['porItemId' => $porItemId, 'porNombre' => $porNombre];
            }

            $items = \Modules\Marketing\Models\PreQuoteItem::where('prequote_id', $prequote->id)->get();
            foreach ($items as $it) {
                $tiempo = trim((string) $it->tiempo_estimado_dias);
                if ($tiempo === '') {
                    continue;
                }
                if (!empty($it->item_id) && !isset($porItemId[$it->item_id])) {
                    $porItemId[$it->item_id] = $tiempo;
                }
                $clave = mb_strtolower(trim((string) $it->product_name));
                if ($clave !== '' && !isset($porNombre[$clave])) {
                    $porNombre[$clave] = $tiempo;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Error obteniendo tiempos de producción de la pre-cotización', [
                'requerimiento_id' => $requerimientoId,
                'error'            => $e->getMessage(),
            ]);
        }

        return ['porItemId' => $porItemId, 'porNombre' => $porNombre];
    }

    /**
     * COM-02 · Resuelve el código de artículo de un producto ya guardado del
     * proyecto: primero por el ítem del catálogo y, si es ad-hoc (sin `item_id`),
     * por el nombre con el que se armó la descripción.
     *
     * La descripción se guarda como "nombre — descripción", así que el nombre es
     * lo anterior al guion largo.
     *
     * @param  \Modules\Comercial\Models\ProyectoProducto  $producto
     * @param  array  $codigos  resultado de codigosArticuloDePrequote()
     * @return string  '' si no se pudo resolver
     */
    private function resolverCodigoArticulo($producto, $codigos)
    {
        return $this->resolverDeIndicesDePrequote($producto, $codigos);
    }

    /**
     * Empareja un producto ya guardado del proyecto contra los índices de la
     * pre-cotización: primero por el ítem del catálogo y, si es ad-hoc (sin
     * `item_id`), por el nombre con el que se armó la descripción.
     *
     * Extraído para que el código de artículo (COM-02) y el tiempo de producción
     * (agosto 2026, punto 20) usen exactamente el mismo emparejamiento. Si se
     * cambiara solo en uno, un producto podría resolver su código por catálogo y
     * su plazo por nombre, o al revés.
     *
     * @param  \Modules\Comercial\Models\ProyectoProducto  $producto
     * @param  array   $indices  ['porItemId' => [...], 'porNombre' => [...]]
     * @return string  '' si no se pudo resolver
     */
    private function resolverDeIndicesDePrequote($producto, $indices)
    {
        if (!empty($producto->item_id) && isset($indices['porItemId'][$producto->item_id])) {
            return $indices['porItemId'][$producto->item_id];
        }

        $nombre = (string) $producto->descripcion;
        $pos = mb_strpos($nombre, ' — ');
        if ($pos !== false) {
            $nombre = mb_substr($nombre, 0, $pos);
        }
        $clave = mb_strtolower(trim($nombre));

        return ($clave !== '' && isset($indices['porNombre'][$clave])) ? $indices['porNombre'][$clave] : '';
    }

    private function fetchItemsFromPrequote($requerimientoId)
    {
        try {
            $prequote = $this->prequoteDelRequerimiento($requerimientoId);

            if (!$prequote) {
                return [];
            }

            $items = \Modules\Marketing\Models\PreQuoteItem::where('prequote_id', $prequote->id)
                ->orderBy('item_order')
                ->orderBy('id')
                ->get();

            return $items->map(function ($it) use ($prequote) {
                $descripcion = $it->product_name;
                if (!empty($it->product_description)) {
                    $descripcion .= ' — ' . $it->product_description;
                }
                if (mb_strlen($descripcion) > 500) {
                    $descripcion = mb_substr($descripcion, 0, 497) . '...';
                }

                // Proveedor inicial sembrado desde la pre-cotización (el usuario lo
                // ajusta luego con las cotizaciones reales de los proveedores).
                $seedCant   = $it->quantity ?: 1;
                $seedPrecio = (float) $it->costo_base;
                $seedMontos = $this->calcularMontosProveedor($seedCant, $seedPrecio, 'exempt');

                // Costos operativos heredados de la pre-cotización (solo lectura, #4).
                $op = $this->costosOperativosDeItem($it, $prequote);

                return [
                    'id'                => null,                // null = aún no persistido en proyecto_productos
                    'item_id'           => $it->item_id,
                    // COM-02 · Código con el que se cotizó el producto (manual de la
                    // cotización si lo tiene; si no, el del catálogo). Solo lectura.
                    'codigo_articulo'   => $it->codigo_articulo,
                    'supplier_id'       => $it->supplier_id,
                    'supplier_name'     => $it->supplier_name,
                    'descripcion'       => $descripcion,
                    // Especificaciones técnicas copiadas de la pre-cotización (solo lectura en el front).
                    'material'          => $it->material,
                    'medida'            => $it->medida,
                    'tecnica_impresion' => $it->tecnica_impresion,
                    'cantidad_colores'  => $it->cantidad_colores,
                    'cantidad_lados'    => $it->cantidad_lados,
                    'empaque'           => $it->empaque,
                    'personalizacion'   => $it->personalizacion,
                    // Costos operativos heredados de la pre-cotización (solo lectura en el proyecto).
                    'costo_empaque'     => $op['empaque'],
                    'costo_movilidad'   => $op['movilidad'],
                    'costo_sctr'        => $op['sctr'],
                    // Hereda el path de la imagen de referencia de la pre-cotización.
                    'imagen'            => $it->product_reference_image,
                    'cantidad'          => $it->quantity,
                    'muestras'          => 0,
                    'precio_unitario'   => $it->valor_unitario,
                    'subtotal'          => $it->valor_total,
                    // Agosto 2026 (punto 19): si el ítem no traía observaciones, el
                    // producto arranca con la plantilla en vez de un campo en blanco.
                    'observaciones'     => $this->observacionesConPlantilla($it->observaciones),
                    // Proveedor inicial (M2) para que la subtabla no arranque vacía.
                    'proveedores'       => [[
                        'id'                        => null,
                        'supplier_id'               => $it->supplier_id,
                        'supplier_name'             => $it->supplier_name,
                        'concepto'                  => $it->product_name,
                        // Agosto 2026 (punto 20): plazo del proveedor, heredado de la
                        // pre-cotización. El usuario lo ajusta cuando el proveedor le
                        // confirma un plazo distinto al que se estimó al cotizar.
                        'tiempo_produccion'          => (string) $it->tiempo_estimado_dias,
                        'cantidad'                  => $seedCant,
                        'precio_unitario_informado' => $seedPrecio,
                        'condicion_igv'             => 'exempt',
                        'precio_unitario_neto'      => $seedMontos['precio_unitario_neto'],
                        'subtotal_neto'             => $seedMontos['subtotal_neto'],
                        'igv_monto'                 => $seedMontos['igv_monto'],
                        'total_pagar'               => $seedMontos['total_pagar'],
                        'activo'                    => true,
                        'orden'                     => 0,
                    ]],
                    '_from_prequote'    => true,                // flag informativo (front lo puede usar)
                ];
            })->all();
        } catch (\Throwable $e) {
            \Log::warning('Error obteniendo items de pre-cotización para proyecto', [
                'requerimiento_id' => $requerimientoId,
                'error'            => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * POST /comercial/proyectos/record
     * Crear nuevo proyecto
     */
    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|integer',
            'nombre'     => 'required|string|max:191',
        ]);

        // Verificar que la empresa existe en el tenant
        $empresa = Empresa::findOrFail($request->empresa_id);

        DB::beginTransaction();
        try {
            // Generar código
            $codigo = Proyecto::generarCodigo($request->empresa_id);

            $proyecto = new Proyecto();
            $proyecto->codigo = $codigo;
            $this->fillProyecto($proyecto, $request);
            $proyecto->save();

            // Guardar relaciones
            $this->saveRelaciones($proyecto, $request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proyecto creado correctamente',
                'data'    => ['id' => $proyecto->id, 'codigo' => $proyecto->codigo],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear proyecto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /comercial/proyectos/record/{id}
     * Actualizar proyecto existente
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'empresa_id' => 'required|integer',
            'nombre'     => 'required|string|max:191',
        ]);

        $proyecto = Proyecto::findOrFail($id);

        DB::beginTransaction();
        try {
            $this->fillProyecto($proyecto, $request);
            $proyecto->save();

            // Actualizar relaciones
            $this->saveRelaciones($proyecto, $request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proyecto actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /comercial/proyectos/record/{id}
     * Eliminar proyecto (soft delete)
     */
    public function destroy($id)
    {
        $proyecto = Proyecto::findOrFail($id);
        $proyecto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyecto eliminado',
        ]);
    }

    /**
     * GET /comercial/proyectos/contactos-empresa/{empresaId}
     * Obtener contactos de una empresa para el select
     */
    /**
     * GET /comercial/proyectos/search-proveedores?q=...
     * Buscar proveedores (persons.type='suppliers') por razón social, número o
     * nombre comercial. Devuelve hasta 20 resultados.
     */
    public function searchProveedores(Request $request)
    {
        // [NUEVO FLUJO 2026-10-20] q vacío = precarga de TODOS los proveedores
        // (la comparativa de pre-cotización carga el dropdown al abrir el acordeón).
        // Con texto se comporta igual que antes (mínimo 2 caracteres).
        $q = trim((string) $request->get('q', ''));
        if ($q !== '' && mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $query = \App\Models\Tenant\Person::where('type', 'suppliers');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('number', 'LIKE', "%{$q}%")
                  ->orWhere('trade_name', 'LIKE', "%{$q}%");
            });
        }
        $rows = $query
            ->orderBy('name')
            // [FIX 2026-10-21] La precarga (q vacío) sube a 500: el combo de la
            // comparativa filtra en el navegador y necesita la lista completa.
            ->limit($q === '' ? 500 : 20)
            ->get(['id', 'name', 'number', 'trade_name'])
            ->map(function ($p) {
                return [
                    'id'           => $p->id,
                    'razon_social' => $p->name,
                    'ruc'          => $p->number,
                    'nombre_comercial' => $p->trade_name,
                ];
            });

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function contactosEmpresa($empresaId)
    {
        $contactos = Contacto::with(['telefonos', 'emails'])
            ->where('empresa_id', $empresaId)
            ->where('estado', 1)
            ->orderBy('nombres')
            ->get()
            ->map(function ($c) {
                // Obtener el teléfono principal o el primero
                $telefonoPrincipal = null;
                if ($c->telefonos) {
                    $p = $c->telefonos->firstWhere('es_principal', 1);
                    $telefonoPrincipal = $p ? $p->telefono : ($c->telefonos->first() ? $c->telefonos->first()->telefono : null);
                }
                if (!$telefonoPrincipal && $c->celular) {
                    $telefonoPrincipal = $c->celular;
                }

                // Email principal
                $emailPrincipal = null;
                if ($c->emails) {
                    $e = $c->emails->firstWhere('es_principal', 1);
                    $emailPrincipal = $e ? $e->email : ($c->emails->first() ? $c->emails->first()->email : null);
                }
                if (!$emailPrincipal && $c->email) {
                    $emailPrincipal = $c->email;
                }

                return [
                    'id'        => $c->id,
                    'nombre'    => trim(($c->nombres ?: '') . ' ' . ($c->apellidos ?: '')),
                    'nombres'   => $c->nombres,
                    'apellidos' => $c->apellidos,
                    'cargo'     => $c->cargo,
                    'telefono'  => $telefonoPrincipal,
                    'correo'    => $emailPrincipal,
                ];
            });

        return response()->json($contactos);
    }

    /**
     * GET /comercial/proyectos/logs/{id}
     * Bitácora de auditoría del proyecto
     */
    public function logs($id)
    {
        $proyecto = Proyecto::findOrFail($id);
        $audits = $proyecto->audits()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($audit) {
                return [
                    'created_at' => $audit->created_at->format('d/m/Y H:i'),
                    'user'       => $audit->user ? $audit->user->name : 'Sistema',
                    'event'      => $audit->event,
                    'old_values' => $audit->old_values ?: [],
                    'new_values' => $audit->new_values ?: [],
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $audits,
        ]);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Llenar todos los campos del proyecto desde el request
     */
    private function fillProyecto(Proyecto $proyecto, Request $request)
    {
        $fields = [
            // Datos generales
            'empresa_id', 'contacto_id', 'requerimiento_id', 'ejecutivo_id',
            'nombre', 'coordinador', 'descripcion', 'tipo', 'prioridad', 'etapa', 'estado', 'semaforo',
            'monto_total', 'monto_costo', 'margen_pct',
            'fecha_inicio', 'fecha_fin',
            'oc_numero', 'oc_fecha',
            'porcentaje_avance', 'porcentaje_facturado', 'porcentaje_cobrado',
            // Criterios
            'tiempo_proyecto', 'politica_tiempos_cortos', 'es_recompra',
            'tipo_cliente_1', 'tipo_cliente_2', 'tiene_oc',
            // Facturación
            'servicio_facturado', 'cantidad_razones_sociales', 'cada_rs_con_oc',
            'aplica_detraccion', 'aplica_retencion', 'forma_pago', 'dias_credito', 'pago_directo',
            'contacto_facturacion', 'correo_factura', 'requiere_formato_especial',
            'entrega_factura_fisica', 'direccion_entrega_factura',
            'documento_adicional_factura', 'comentario_facturacion',
            // Muestras
            'muestra_virtual', 'muestra_fisica', 'prueba_color', 'arte_aprobado_at',
            // Logística
            'tipo_embalaje', 'embalaje_grupal', 'rotulo_especial',
            'sticker_compipro', 'cantidad_sticker',
            // Despacho
            'contacto_despacho', 'tipo_entrega', 'direccion_entrega',
            'horario_atencion_despacho', 'documentacion_adicional_despacho',
            'tamano_movilidad', 'requiere_programacion_despacho', 'confirmacion_ingreso',
            // Seguridad
            'sctr_personal_logistica', 'epps_logistica',
            'sctr_conductor', 'epps_conductor', 'documentacion_transportista',
            // Post Venta
            'carta_conformidad', 'solicitud_promocion', 'acepta_solicitud_promocion',
            'tiene_datos_cumpleanos', 'estatus_proveedores', 'resumen_proyecto',
            // Cobranza
            'fecha_envio_factura', 'fecha_ingreso_factura', 'fecha_tentativa_pago',
            'metodo_politica_pago', 'pago_conforme', 'observaciones_cobranza',
            'adelanto_monto', 'adelanto_fecha',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $proyecto->{$field} = $request->get($field);
            }
        }

        // Contactos de facturación se guarda como JSON
        if ($request->has('contactos_facturacion')) {
            $proyecto->contactos_facturacion = json_encode($request->get('contactos_facturacion'));
        }
    }

    /**
     * Guardar relaciones hijo (productos y razones sociales)
     */
    private function saveRelaciones(Proyecto $proyecto, Request $request)
    {
        // ---- Productos (con proveedores por producto, M2) ----
        // Upsert por id: preserva el id de cada producto y, con él, sus proveedores
        // y su historial. NO se hace delete+recreate (rompería las FK de proveedores).
        if ($request->has('productos')) {
            $productos = $request->get('productos', []);
            $idsProductos = [];

            foreach ($productos as $prod) {
                if (empty($prod['descripcion'])) {
                    continue;
                }

                $datos = [
                    'item_id'           => isset($prod['item_id']) ? $prod['item_id'] : null,
                    'supplier_id'       => isset($prod['supplier_id']) ? $prod['supplier_id'] : null,
                    'supplier_name'     => isset($prod['supplier_name']) ? $prod['supplier_name'] : null,
                    'descripcion'       => $prod['descripcion'],
                    // Especificaciones técnicas (snapshot de la pre-cotización).
                    'material'          => isset($prod['material']) ? $prod['material'] : null,
                    'medida'            => isset($prod['medida']) ? $prod['medida'] : null,
                    'tecnica_impresion' => isset($prod['tecnica_impresion']) ? $prod['tecnica_impresion'] : null,
                    'cantidad_colores'  => isset($prod['cantidad_colores']) ? $prod['cantidad_colores'] : null,
                    'cantidad_lados'    => isset($prod['cantidad_lados']) ? $prod['cantidad_lados'] : null,
                    'empaque'           => isset($prod['empaque']) ? $prod['empaque'] : null,
                    'personalizacion'   => isset($prod['personalizacion']) ? $prod['personalizacion'] : null,
                    // Costos operativos por producto (para el resumen del proyecto, M4).
                    'costo_empaque'     => isset($prod['costo_empaque']) ? $prod['costo_empaque'] : 0,
                    'costo_movilidad'   => isset($prod['costo_movilidad']) ? $prod['costo_movilidad'] : 0,
                    'costo_sctr'        => isset($prod['costo_sctr']) ? $prod['costo_sctr'] : 0,
                    'imagen'            => isset($prod['imagen']) ? $prod['imagen'] : null,
                    'cantidad'          => isset($prod['cantidad']) ? $prod['cantidad'] : 1,
                    'muestras'          => isset($prod['muestras']) ? $prod['muestras'] : 0,
                    'observaciones'     => isset($prod['observaciones']) ? $prod['observaciones'] : null,
                ];

                // Upsert por id
                $producto = null;
                if (!empty($prod['id'])) {
                    $producto = $proyecto->productos()->where('id', $prod['id'])->first();
                }
                if ($producto) {
                    $producto->fill($datos);
                } else {
                    $producto = new ProyectoProducto($datos);
                    $producto->proyecto_id = $proyecto->id;
                }

                $proveedoresInput = isset($prod['proveedores']) && is_array($prod['proveedores'])
                    ? $prod['proveedores'] : [];

                if (!empty($proveedoresInput)) {
                    // El subtotal lo gobiernan los proveedores (se fija abajo).
                    $producto->autoSubtotal = false;
                } else {
                    // Carga simple heredada (sin proveedores): precio/subtotal clásicos.
                    $producto->precio_unitario = isset($prod['precio_unitario']) ? $prod['precio_unitario'] : null;
                    $producto->subtotal        = isset($prod['subtotal']) ? $prod['subtotal'] : null;
                }

                $producto->save();
                $idsProductos[] = $producto->id;

                if (!empty($proveedoresInput)) {
                    $this->saveProveedoresProducto($producto, $proveedoresInput);
                }
            }

            // Borra los productos que ya no vienen (sus proveedores caen por FK cascade).
            $proyecto->productos()->whereNotIn('id', $idsProductos ?: [0])->delete();
        }

        // ---- Razones Sociales ----
        // Usamos updateOrCreate por ID para preservar los archivos de OC
        // (oc_archivo, oc_archivo_original) que se suben por endpoints separados.
        if ($request->has('razones_sociales')) {
            $razones = $request->get('razones_sociales', []);
            $idsRecibidos = [];

            foreach ($razones as $rs) {
                if (empty($rs['razon_social'])) {
                    continue;
                }

                $data = [
                    'razon_social'   => $rs['razon_social'],
                    'ruc'            => isset($rs['ruc']) ? $rs['ruc'] : null,
                    'monto_facturar' => isset($rs['monto_facturar']) ? $rs['monto_facturar'] : null,
                    'tiene_oc'       => isset($rs['tiene_oc']) && $rs['tiene_oc'] !== '' && $rs['tiene_oc'] !== null
                                            ? (string)$rs['tiene_oc']
                                            : 'Pendiente',
                    'oc_numero'      => isset($rs['oc_numero']) ? $rs['oc_numero'] : null,
                    'observaciones'  => isset($rs['observaciones']) ? $rs['observaciones'] : null,
                ];

                if (!empty($rs['id'])) {
                    // Actualizar existente (preserva oc_archivo)
                    $existing = $proyecto->razonesSociales()->where('id', $rs['id'])->first();
                    if ($existing) {
                        $existing->fill($data)->save();
                        $idsRecibidos[] = $existing->id;
                    } else {
                        $nueva = $proyecto->razonesSociales()->create($data);
                        $idsRecibidos[] = $nueva->id;
                    }
                } else {
                    $nueva = $proyecto->razonesSociales()->create($data);
                    $idsRecibidos[] = $nueva->id;
                }
            }

            // Eliminar las razones sociales que ya no están (y sus archivos)
            $aEliminar = $proyecto->razonesSociales()->whereNotIn('id', $idsRecibidos)->get();
            foreach ($aEliminar as $rs) {
                if ($rs->oc_archivo && Storage::disk('local')->exists($rs->oc_archivo)) {
                    Storage::disk('local')->delete($rs->oc_archivo);
                }
                $rs->delete();
            }
        }

        // ---- Evaluaciones de Proveedor (post-venta) ----
        // Patrón delete-and-recreate (las evaluaciones no tienen archivos asociados,
        // así que es seguro borrarlas y recrear).
        if ($request->has('evaluaciones_proveedor')) {
            $proyecto->evaluacionesProveedor()->delete();

            $userId = \Auth::id();
            $evals = $request->get('evaluaciones_proveedor', []);
            foreach ($evals as $ev) {
                if (empty($ev['proveedor_id'])) continue;

                $estrellas = isset($ev['estrellas']) ? (int) $ev['estrellas'] : null;
                if ($estrellas !== null && ($estrellas < 1 || $estrellas > 5)) {
                    $estrellas = null;
                }

                // Si no llega label explícito, derivarlo de las estrellas
                $label = $ev['calificacion_label'] ?? null;
                if (empty($label) && $estrellas !== null) {
                    if ($estrellas >= 4) $label = 'Bueno';
                    elseif ($estrellas >= 2) $label = 'Regular';
                    else $label = 'Malo';
                }

                $proyecto->evaluacionesProveedor()->create([
                    'proveedor_id'        => $ev['proveedor_id'],
                    'proveedor_nombre'    => isset($ev['proveedor_nombre']) ? $ev['proveedor_nombre'] : null,
                    'estrellas'           => $estrellas,
                    'calificacion_label'  => $label,
                    'comentario'          => isset($ev['comentario']) ? $ev['comentario'] : null,
                    'user_id'             => $userId,
                ]);
            }
        }

        // Recalcular el costo total del proyecto (M4) a partir de los productos y
        // sus proveedores activos.
        $this->recalcularCostoTotalProyecto($proyecto);
    }

    /**
     * Persiste los proveedores de un producto (M2) con historial y recalcula el
     * subtotal del producto (= suma del total a pagar de los proveedores activos).
     *
     * Historial: los proveedores marcados como inactivos (reemplazados) NO se
     * borran nunca; solo se eliminan filas ACTIVAS que el usuario quitó.
     */
    private function saveProveedoresProducto(ProyectoProducto $producto, array $proveedores)
    {
        $idsRecibidos = [];
        $orden        = 0;
        $sumaUnit     = 0;   // costo unitario acumulado (activos)
        $sumaTotal    = 0;   // costo total del producto (activos)

        foreach ($proveedores as $prov) {
            $tieneAlgo = !empty($prov['supplier_id']) || !empty($prov['supplier_name'])
                || !empty($prov['concepto']) || !empty($prov['precio_unitario_informado']);
            if (!$tieneAlgo && empty($prov['id'])) {
                continue;
            }

            $cantidad  = isset($prov['cantidad']) ? (float) $prov['cantidad'] : 1;
            $precioInf = isset($prov['precio_unitario_informado']) ? (float) $prov['precio_unitario_informado'] : 0;
            $condicion = isset($prov['condicion_igv']) ? $prov['condicion_igv'] : 'pending';
            if (!in_array($condicion, ['included', 'excluded', 'exempt', 'pending'], true)) {
                $condicion = 'pending';
            }
            $activo = !isset($prov['activo']) || in_array($prov['activo'], [true, 1, '1', 'true'], true);

            $montos = $this->calcularMontosProveedor($cantidad, $precioInf, $condicion);

            $datos = [
                'supplier_id'               => !empty($prov['supplier_id']) ? $prov['supplier_id'] : null,
                'supplier_name'             => isset($prov['supplier_name']) ? $prov['supplier_name'] : null,
                'concepto'                  => isset($prov['concepto']) ? $prov['concepto'] : null,
                // Agosto 2026 (punto 20): plazo del proveedor. Texto libre, igual
                // que el `tiempo_estimado_dias` de la pre-cotización del que hereda.
                'tiempo_produccion'          => isset($prov['tiempo_produccion']) && trim((string) $prov['tiempo_produccion']) !== ''
                                                ? trim((string) $prov['tiempo_produccion']) : null,
                'cantidad'                  => $cantidad,
                'precio_unitario_informado' => $precioInf,
                'condicion_igv'             => $condicion,
                'precio_unitario_neto'      => $montos['precio_unitario_neto'],
                'subtotal_neto'             => $montos['subtotal_neto'],
                'igv_monto'                 => $montos['igv_monto'],
                'total_pagar'               => $montos['total_pagar'],
                'activo'                    => $activo ? 1 : 0,
                'reemplaza_a_id'            => !empty($prov['reemplaza_a_id']) ? $prov['reemplaza_a_id'] : null,
                'motivo_reemplazo'          => isset($prov['motivo_reemplazo']) ? $prov['motivo_reemplazo'] : null,
                'orden'                     => $orden++,
            ];

            $fila = null;
            if (!empty($prov['id'])) {
                $fila = $producto->proveedores()->where('id', $prov['id'])->first();
            }
            if ($fila) {
                $fila->fill($datos)->save();
            } else {
                $fila = $producto->proveedores()->create($datos);
            }
            $idsRecibidos[] = $fila->id;

            if ($activo) {
                $sumaUnit  += $precioInf;
                $sumaTotal += $montos['total_pagar'];
            }
        }

        // Conservar el historial: solo se borran proveedores ACTIVOS que ya no vienen.
        $producto->proveedores()
            ->where('activo', 1)
            ->whereNotIn('id', $idsRecibidos ?: [0])
            ->delete();

        // Subtotal del producto = suma de los totales de los proveedores activos.
        $producto->autoSubtotal    = false;
        $producto->precio_unitario = round($sumaUnit, 2);
        $producto->subtotal        = round($sumaTotal, 2);
        $producto->save();
    }

    /**
     * Calcula los montos derivados de una línea de proveedor según la condición
     * de IGV (misma lógica que el front). Devuelve neto unitario, subtotal neto,
     * IGV y total a pagar.
     *
     * @return array
     */
    private function calcularMontosProveedor($cantidad, $precioInformado, $condicionIgv)
    {
        $IGV  = 0.18;
        $qty  = (float) $cantidad;
        $unit = (float) $precioInformado;

        $unitNet     = $unit;
        $subtotalNet = $unit * $qty;
        $totalPay    = $subtotalNet;

        if ($condicionIgv === 'included') {
            $unitNet     = $unit / (1 + $IGV);
            $subtotalNet = $unitNet * $qty;
            $totalPay    = $unit * $qty;
        } elseif ($condicionIgv === 'excluded') {
            $subtotalNet = $unit * $qty;
            $totalPay    = $subtotalNet * (1 + $IGV);
        } elseif ($condicionIgv === 'exempt') {
            $subtotalNet = $unit * $qty;
            $totalPay    = $subtotalNet;
        } else { // pending: aún no se confirma el IGV → no suma al total a pagar
            $subtotalNet = $unit * $qty;
            $totalPay    = 0;
        }

        return [
            'precio_unitario_neto' => round($unitNet, 2),
            'subtotal_neto'        => round($subtotalNet, 2),
            'igv_monto'            => round(max($totalPay - $subtotalNet, 0), 2),
            'total_pagar'          => round($totalPay, 2),
        ];
    }

    /**
     * Recalcula y persiste `proyecto.costo_total_proyecto` (M4): costo real del
     * proyecto = proveedores (neto + IGV) + costos operativos (empaque, movilidad,
     * SCTR) + su IGV. Misma fórmula que el resumen del mockup.
     */
    private function recalcularCostoTotalProyecto(Proyecto $proyecto)
    {
        $IGV = 0.18;
        $proyecto->load('productos.proveedoresActivos');

        $provNet   = 0;
        $provTotal = 0;
        $operativo = 0;

        foreach ($proyecto->productos as $p) {
            foreach ($p->proveedoresActivos as $pr) {
                $provNet   += (float) $pr->subtotal_neto;
                $provTotal += (float) $pr->total_pagar;
            }
            $operativo += (float) $p->costo_empaque + (float) $p->costo_movilidad + (float) $p->costo_sctr;
        }

        $subtotalProyecto = $provNet + $operativo;
        $igvProveedores   = max($provTotal - $provNet, 0);
        $igvProyecto      = $igvProveedores + ($operativo * $IGV);

        $proyecto->costo_total_proyecto = round($subtotalProyecto + $igvProyecto, 2);
        $proyecto->save();
    }

    // =========================================================================
    // ENDPOINTS: BITÁCORA "CAMBIOS E INDICACIONES" (M3)
    // =========================================================================

    /**
     * GET /comercial/proyectos/record/{id}/bitacora
     * Lista las entradas de la bitácora del proyecto en orden cronológico.
     */
    public function listBitacora($id)
    {
        $entradas = ProyectoBitacora::where('proyecto_id', $id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($b) {
                return $this->mapBitacora($b);
            });

        return response()->json(['success' => true, 'data' => $entradas]);
    }

    /**
     * POST /comercial/proyectos/record/{id}/bitacora
     * Registra una entrada nueva (guarda autor y fecha/hora automáticamente).
     */
    public function storeBitacora(Request $request, $id)
    {
        $request->validate(['contenido' => 'required|string']);
        Proyecto::findOrFail($id);

        $b = ProyectoBitacora::create([
            'proyecto_id' => $id,
            'contenido'   => trim($request->input('contenido')),
            'user_id'     => \Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registro agregado a la bitácora',
            'data'    => $this->mapBitacora($b),
        ]);
    }

    /**
     * PUT /comercial/proyectos/record/{id}/bitacora/{bitacoraId}
     * Edita una entrada; guarda el autor y la fecha/hora de la última edición.
     */
    public function updateBitacora(Request $request, $id, $bitacoraId)
    {
        $request->validate(['contenido' => 'required|string']);

        $b = ProyectoBitacora::where('proyecto_id', $id)->where('id', $bitacoraId)->firstOrFail();
        $b->contenido = trim($request->input('contenido'));
        $b->edited_by = \Auth::id();
        $b->edited_at = now();
        $b->save();

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado',
            'data'    => $this->mapBitacora($b),
        ]);
    }

    /**
     * Formatea una entrada de bitácora para el frontend.
     */
    private function mapBitacora(ProyectoBitacora $b)
    {
        return [
            'id'         => $b->id,
            'contenido'  => $b->contenido,
            'autor'      => $b->user_id ? optional(User::find($b->user_id))->name : null,
            'created_at' => optional($b->created_at)->format('Y-m-d H:i'),
            'editor'     => $b->edited_by ? optional(User::find($b->edited_by))->name : null,
            'edited_at'  => $b->edited_at ? $b->edited_at->format('Y-m-d H:i') : null,
        ];
    }

    // =========================================================================
    // ENDPOINTS: ARCHIVO PDF DE ORDEN DE COMPRA POR RAZÓN SOCIAL
    // =========================================================================

    /**
     * Sube el PDF de la Orden de Compra de una razón social específica.
     * POST /comercial/proyectos/{id}/razon-social/{rsId}/oc-upload
     */
    public function uploadOcArchivo($id, $rsId, Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:5120'], // 5 MB máx
        ]);

        $proyecto = Proyecto::findOrFail($id);
        $rs = $proyecto->razonesSociales()->where('id', $rsId)->firstOrFail();

        // Eliminar archivo anterior si existía
        if ($rs->oc_archivo && Storage::disk('local')->exists($rs->oc_archivo)) {
            Storage::disk('local')->delete($rs->oc_archivo);
        }

        $file = $request->file('archivo');
        $nombreOriginal = $file->getClientOriginalName();
        $nombreSeguro = 'oc_' . $rs->id . '_' . time() . '.pdf';
        $ruta = $file->storeAs("proyectos/{$proyecto->id}/oc", $nombreSeguro, 'local');

        $rs->oc_archivo = $ruta;
        $rs->oc_archivo_original = $nombreOriginal;
        $rs->save();

        return response()->json([
            'success' => true,
            'message' => 'Archivo de OC cargado correctamente',
            'data'    => [
                'id'                    => $rs->id,
                'oc_archivo'            => $rs->oc_archivo,
                'oc_archivo_original'   => $rs->oc_archivo_original,
            ],
        ]);
    }

    /**
     * Descarga el PDF de la Orden de Compra de una razón social.
     * GET /comercial/proyectos/{id}/razon-social/{rsId}/oc-download
     */
    public function downloadOcArchivo($id, $rsId)
    {
        $proyecto = Proyecto::findOrFail($id);
        $rs = $proyecto->razonesSociales()->where('id', $rsId)->firstOrFail();

        if (!$rs->oc_archivo || !Storage::disk('local')->exists($rs->oc_archivo)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de OC no existe',
            ], 404);
        }

        $nombre = $rs->oc_archivo_original ?: basename($rs->oc_archivo);
        return Storage::disk('local')->download($rs->oc_archivo, $nombre);
    }

    /**
     * Elimina el PDF de la Orden de Compra de una razón social (solo el archivo).
     * DELETE /comercial/proyectos/{id}/razon-social/{rsId}/oc-archivo
     */
    public function deleteOcArchivo($id, $rsId)
    {
        $proyecto = Proyecto::findOrFail($id);
        $rs = $proyecto->razonesSociales()->where('id', $rsId)->firstOrFail();

        if ($rs->oc_archivo && Storage::disk('local')->exists($rs->oc_archivo)) {
            Storage::disk('local')->delete($rs->oc_archivo);
        }

        $rs->oc_archivo = null;
        $rs->oc_archivo_original = null;
        $rs->save();

        return response()->json([
            'success' => true,
            'message' => 'Archivo de OC eliminado',
        ]);
    }
}
