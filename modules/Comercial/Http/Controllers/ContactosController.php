<?php

namespace Modules\Comercial\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Comercial\Http\Resources\ContactoResource;
use Modules\Comercial\Http\Resources\ContactosCollection;
use Modules\Comercial\Models\Contacto;
use Modules\Comercial\Models\ContactoEmail;
use Modules\Comercial\Models\ContactoTelefono;
use Modules\Comercial\Models\Empresa;
use Modules\Comercial\Models\ContactoInteraccion;
use Modules\Comercial\Http\Controllers\Traits\BusquedaPorPalabra;
use Modules\Comercial\Support\Correo;
use Modules\Comercial\Support\Telefono;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ContactosController extends Controller
{
    use BusquedaPorPalabra;

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('comercial::contactos.index');
    }

    public function columns()
    {
        // El primero es el filtro por defecto.
        return [
            'todos' => 'Todos',
            'razon_social' => 'Empresa',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'cargo' => 'Cargo',
            'celular' => 'Celular',
            'telefono' => 'Teléfono',
            'email' => 'Email',
        ];
    }

    public function tables()
    /**
     * Tablas
    */
    {
        // nombre_comercial se incluye para permitir filtrado local por ese
        // campo en el frontend (Element UI select con filterable).
        $list_empresas = Empresa::query()->where('estado', '=', 1)
            ->select(['id', 'razon_social', 'nombre_comercial'])
            ->get();

        $list_estados_interno = [
            (object)["id" => 1, "name" => 'Activo'],
            (object)["id" => 0, "name" => 'Inactivo'],
        ];

        $list_tipos_telefono = [
            (object)["id" => 'movil', "name" => 'Móvil'],
            (object)["id" => 'fijo', "name" => 'Fijo'],
            (object)["id" => 'trabajo', "name" => 'Trabajo'],
            (object)["id" => 'casa', "name" => 'Casa'],
            (object)["id" => 'otro', "name" => 'Otro'],
        ];

        // *** AGREGADO: Lista de orígenes ***
        $list_origenes = [
            (object)["id" => 'FIDELIZADO', "name" => 'FIDELIZADO'],
            (object)["id" => 'REFERIDO', "name" => 'REFERIDO'],
            (object)["id" => 'OP. COMERCIAL', "name" => 'OP. COMERCIAL'],
            (object)["id" => 'MKTG - RRSS', "name" => 'MKTG - RRSS'],
            (object)["id" => 'MKTG - MAIL', "name" => 'MKTG - MAIL'],
            (object)["id" => 'MKTG - C.PAGO', "name" => 'MKTG - C.PAGO'],
            (object)["id" => 'MKTG - WSP PAGADO', "name" => 'MKTG - WSP PAGADO'],
            (object)["id" => 'MKTG - WSP MANUAL', "name" => 'MKTG - WSP MANUAL'],
            (object)["id" => 'OTROS', "name" => 'OTROS'],
        ];

        // *** MODIFICADO: Agregar list_origenes al return ***
        return compact('list_empresas', 'list_estados_interno', 'list_tipos_telefono', 'list_origenes');
    }

    /**
     * ============================================================================
     * MÉTODO CORREGIDO: records()
     * ============================================================================
     * CAMBIOS:
     * - Usar LIKE en lugar de FULLTEXT MATCH para todos los campos
     * - Agregar 'telefono' y 'email' a la lista de columnas filtrables
     * - Funciona sin necesidad de índices FULLTEXT
     * ============================================================================
     */
    public function records(Request $request)
    {
        $records = Contacto::query()->whereNull('deleted_at')
            // Usuario de la última modificación: último cambio registrado en la auditoría.
            ->select('contacto.*')
            ->selectSub(function ($q) {
                $q->from('audits')
                  ->join('users', 'users.id', '=', 'audits.user_id')
                  ->select('users.name')
                  ->whereColumn('audits.auditable_id', 'contacto.id')
                  ->where('audits.auditable_type', Contacto::class)
                  ->orderByDesc('audits.id')
                  ->limit(1);
            }, 'ultimo_usuario')
            ->with([
                'empresa:id,razon_social,nombre_comercial',
                'responsableComercial:id,name',
                'emails',      // Solo activos (estado = 1)
                'telefonos'    // Solo activos (estado = 1)
            ])
            // Ordenar por última actualización (recién creados/editados arriba).
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc');

        $column = $request->get('column');
        $value = trim((string) $request->get('value'));
        if ($value !== '' && array_key_exists($column, $this->columns())) {
            $this->buscarContactos($records, $column, $value);
        }

        return new ContactosCollection($records->paginate(config('tenant.items_per_page')));
    }

    /**
     * Búsqueda por palabra (cada palabra en alguno de los campos de la opción elegida).
     * Un teléfono escrito con espacios, +51 o (01) se busca como un solo número normalizado.
     */
    private function buscarContactos($query, $column, $value)
    {
        $todos = $column === 'todos';
        $patrones = Telefono::pareceTelefono($value) && in_array($column, ['todos', 'celular', 'telefono'], true)
            ? ['%' . addcslashes(Telefono::normalizarNumero(Telefono::separarAnexo($value)['numero']), '%_\\') . '%']
            : $this->patronesPorPalabra($value);

        foreach ($patrones as $like) {
            $query->where(function ($q) use ($column, $todos, $like) {
                if ($todos || $column === 'razon_social') {
                    // Razón social y nombre comercial: los ejecutivos identifican así a las empresas.
                    $q->orWhereHas('empresa', function ($e) use ($like) {
                        $e->whereRaw("REPLACE(razon_social, '.', '') LIKE ?", [$like])
                          ->orWhereRaw("REPLACE(nombre_comercial, '.', '') LIKE ?", [$like]);
                    });
                }
                foreach (['nombres', 'apellidos', 'cargo'] as $campo) {
                    if ($todos || $column === $campo) {
                        $q->orWhereRaw("REPLACE(contacto.{$campo}, '.', '') LIKE ?", [$like]);
                    }
                }
                if ($todos || $column === 'celular') {
                    $q->orWhere('contacto.celular', 'LIKE', $like)
                      ->orWhereHas('telefonos', function ($t) use ($like) {
                          $t->where('telefono', 'LIKE', $like)->whereIn('tipo', ['movil', 'casa']);
                      });
                }
                if ($todos || $column === 'telefono') {
                    $q->orWhere('contacto.telefono', 'LIKE', $like)
                      ->orWhereHas('telefonos', function ($t) use ($like) {
                          $t->where('telefono', 'LIKE', $like);
                      });
                }
                if ($todos || $column === 'email') {
                    $q->orWhere('contacto.email', 'LIKE', $like)
                      ->orWhereHas('emails', function ($m) use ($like) {
                          $m->where('email', 'LIKE', $like);
                      });
                }
            });
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'nombres'    => ['required', 'string', 'max:191'],
            'apellidos'  => ['nullable', 'string', 'max:191'],
            'dni'        => ['nullable', 'string', 'max:20'],
            'origen'  => ['required', 'string'],
            'cargo'      => ['nullable', 'string', 'max:191'],
            'celular'   => ['nullable', 'string', 'max:50'],
            'tipo_contacto'  => ['nullable', 'string'],
            'fecha_cumpleanos'  => ['nullable', 'date'],
            'direccion'  => ['nullable', 'string'],
            'referencia' => ['nullable', 'string'],
            'adicionales' => ['nullable', 'string'],
            'email'  => ['nullable', 'email'],
            'telefono'  => ['nullable', 'string'],
            'estado'  => ['nullable', 'integer'],
            
            // Nuevos campos separados
            'telefonos' => ['nullable', 'array'],
            'telefonos.*.id' => ['nullable', 'integer'],
            'telefonos.*.telefono' => ['required_with:telefonos', 'string', 'max:50'],
            'telefonos.*.anexo' => ['nullable', 'string', 'max:10'],
            'telefonos.*.tipo' => ['nullable', 'string', 'in:movil,fijo,trabajo,personal,casa,otro'],
            'telefonos.*.es_principal' => ['nullable', 'boolean'],
            'telefonos.*.estado' => ['nullable', 'integer', 'in:0,1'],
            
            'emails' => ['nullable', 'array'],
            'emails.*.id' => ['nullable', 'integer'],
            'emails.*.email' => ['required_with:emails', 'email', 'max:191'],
            'emails.*.es_principal' => ['nullable', 'boolean'],
            'emails.*.estado' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $existEmpresa = Empresa::query()->find($data['empresa_id']);
        if (!$existEmpresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        // ===== Validaciones de negocio (ANTES de tocar la BD) =====
        $idContactoActual = $request->input('id');
        $telefonosInput   = $request->input('telefonos', []);
        $emailsInput      = $request->input('emails', []);

        // 1) El celular principal y el telefono fijo no pueden ser el mismo numero.
        $celPrincipal = null;
        $telFijo      = null;
        foreach ($telefonosInput as $t) {
            $num = Telefono::normalizarNumero(Telefono::separarAnexo($t['telefono'] ?? '')['numero']);
            if ($num === '') continue;
            if (($t['slot'] ?? '') === 'celular_principal') $celPrincipal = $num;
            if (($t['slot'] ?? '') === 'fijo')              $telFijo      = $num;
        }
        if ($celPrincipal !== null && $celPrincipal === $telFijo) {
            return response()->json([
                'success' => false,
                'message' => 'El celular principal y el telefono fijo no pueden ser el mismo numero.',
            ], 422);
        }

        // 2) Unicidad del celular: no puede estar activo en otro contacto activo.
        //    El fijo no se valida (la central de la empresa se registra en Empresas).
        $celulares = [];
        foreach ($telefonosInput as $t) {
            $num = Telefono::normalizar($t['telefono'] ?? '', $t['anexo'] ?? null);
            if (Telefono::esCelular($num)) $celulares[] = $num;
        }
        $celularExistente = $this->celularEnOtroContacto($celulares, $idContactoActual);
        if ($celularExistente) {
            return $this->respuestaDuplicado('telefono', $celularExistente);
        }

        // 3) Unicidad del correo (sin espacios y sin distinguir mayúsculas).
        $correos = [];
        foreach ($emailsInput as $e) {
            $em = Correo::normalizar($e['email'] ?? '');
            if ($em !== '') $correos[] = $em;
        }
        $correoExistente = $this->correoEnOtroContacto($correos, $idContactoActual);
        if ($correoExistente) {
            return $this->respuestaDuplicado('email', $correoExistente);
        }

        // La transaccion DEBE ir en la conexion del TENANT: los modelos usan
        // UsesTenantConnection, y una transaccion en la conexion por defecto NO
        // cubre sus operaciones, por lo que el rollBack no revertiria el contacto
        // si fallan los telefonos/emails (causa del "da error pero igual crea").
        $tenantConn = (new Contacto)->getConnectionName();
        DB::connection($tenantConn)->beginTransaction();
        
        try {
            $idContacto = $request->input('id');
            $telefonos = $request->input('telefonos', []);
            $emails = $request->input('emails', []);

            // CREAR NUEVO CONTACTO
            if (empty($idContacto)) {
                $contacto = Contacto::query()->create($data);

                // Guardar teléfonos
                $this->saveTelefonos($contacto->id, $telefonos);

                // Guardar emails
                $this->saveEmails($contacto->id, $emails);

                DB::connection($tenantConn)->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Contacto creado correctamente',
                    'data' => ['id' => $contacto->id]
                ]);
            }

            // ACTUALIZAR CONTACTO EXISTENTE
            $contacto = Contacto::query()->findOrFail($idContacto);
            // La empresa de un contacto no se cambia al editar (solo sus datos).
            unset($data['empresa_id']);
            
            // ✅ CORRECCIÓN 1: No establecer manualmente updated_at
            // Laravel lo hará automáticamente, y así no interfiere con la auditoría
            $contacto->fill($data);
            // ❌ ELIMINADO: $contacto->updated_at = now();
            $contacto->save();

            // Actualizar teléfonos
            $this->saveTelefonos($contacto->id, $telefonos);

            // Actualizar emails
            $this->saveEmails($contacto->id, $emails);

            DB::connection($tenantConn)->commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto actualizado correctamente',
                'data' => ['id' => $contacto->id]
            ]);

        } catch (ValidationException $e) {
            DB::connection($tenantConn)->rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::connection($tenantConn)->rollBack();
            Log::error('Error al guardar contacto: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el contacto. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar teléfonos del contacto con borrado lógico.
     *
     * Modelo de datos:
     *   - Cada teléfono lleva un `slot` explícito que identifica su rol dentro
     *     del contacto: 'celular_principal' | 'fijo' | 'adicional'.
     *   - El campo `es_principal` se mantiene por backward-compat (lo lee el
     *     listado de contactos) pero es DERIVADO del slot:
     *     es_principal = 1  ⇔  slot = 'celular_principal'.
     *
     * Reglas:
     *   - NO se hace auto-promote: si el usuario borra el celular principal,
     *     el contacto queda sin principal hasta que cree uno nuevo. Esto es
     *     un cambio intencional vs. la versión anterior, que promovía
     *     automáticamente el primer no-principal y causaba que "todo se
     *     corriera" en el reload del form.
     *   - El frontend es responsable de mandar UN solo teléfono con
     *     slot='celular_principal' y máximo UN solo teléfono con slot='fijo'.
     *     El backend confía en eso y NO degrada otros teléfonos por las dudas.
     */
    protected function saveTelefonos($contactoId, array $telefonos)
    {
        $slotsValidos = [
            ContactoTelefono::SLOT_CELULAR_PRINCIPAL,
            ContactoTelefono::SLOT_FIJO,
            ContactoTelefono::SLOT_ADICIONAL,
        ];
        // Un contacto inactivo guarda sus teléfonos suspendidos (se recuperan al activarlo).
        $estado = (int) Contacto::query()->where('id', $contactoId)->value('estado') === 1 ? 1 : 0;
        $existentes = ContactoTelefono::where('contacto_id', $contactoId)->orderBy('id')->get();
        $usados = [];

        foreach ($telefonos as $row) {
            $valor = Telefono::normalizar($row['telefono'] ?? '', $row['anexo'] ?? null);
            if ($valor === '' || $this->yaUsado($usados, 'telefono', $valor)) {
                continue;
            }

            $slot = in_array($row['slot'] ?? null, $slotsValidos, true) ? $row['slot'] : ContactoTelefono::SLOT_ADICIONAL;
            $registro = $this->registroParaGuardar($existentes, $usados, 'telefono', $valor, $row['id'] ?? null)
                ?: new ContactoTelefono(['contacto_id' => $contactoId]);

            $registro->fill([
                'telefono'     => $valor,
                'tipo'         => $row['tipo'] ?? 'movil',
                'es_principal' => $slot === ContactoTelefono::SLOT_CELULAR_PRINCIPAL ? 1 : 0,
                'slot'         => $slot,
                'estado'       => $estado,
                'deleted_at'   => null,
            ])->save();
            $usados[$registro->id] = $registro;
        }

        $this->darDeBajaNoUsados($existentes, $usados);

        // contacto.celular = celular principal (sin auto-promote de otro teléfono).
        $principal = collect($usados)->first(function ($t) {
            return $t->slot === ContactoTelefono::SLOT_CELULAR_PRINCIPAL;
        });
        Contacto::where('id', $contactoId)->update(['celular' => $principal ? $principal->telefono : null]);
    }

    /**
     * Guardar emails del contacto (normalizados; reactiva el que la persona ya tuvo).
     */
    protected function saveEmails($contactoId, array $emails)
    {
        $estado = (int) Contacto::query()->where('id', $contactoId)->value('estado') === 1 ? 1 : 0;
        $existentes = ContactoEmail::where('contacto_id', $contactoId)->orderBy('id')->get();
        $usados = [];
        $principalId = null;

        foreach ($emails as $row) {
            $valor = Correo::normalizar($row['email'] ?? '');
            if ($valor === '' || $this->yaUsado($usados, 'email', $valor)) {
                continue;
            }

            $registro = $this->registroParaGuardar($existentes, $usados, 'email', $valor, $row['id'] ?? null)
                ?: new ContactoEmail(['contacto_id' => $contactoId]);
            $registro->fill([
                'email'      => $valor,
                'estado'     => $estado,
                'deleted_at' => null,
            ])->save();
            $usados[$registro->id] = $registro;

            if ($principalId === null && !empty($row['es_principal'])) {
                $principalId = $registro->id;
            }
        }

        $this->darDeBajaNoUsados($existentes, $usados);

        // Un solo principal: el marcado o, si no hay, el primero.
        if ($principalId === null && !empty($usados)) {
            $principalId = array_keys($usados)[0];
        }
        foreach ($usados as $id => $registro) {
            $esPrincipal = $id === $principalId ? 1 : 0;
            if ((int) $registro->es_principal !== $esPrincipal) {
                $registro->es_principal = $esPrincipal;
                $registro->save();
            }
        }

        Contacto::where('id', $contactoId)->update([
            'email' => $principalId ? $usados[$principalId]->email : null,
        ]);
    }

    /**
     * ¿El valor ya se guardó en esta misma petición? (evita repetirlo).
     */
    private function yaUsado(array $usados, $campo, $valor)
    {
        foreach ($usados as $registro) {
            if ($registro->{$campo} === $valor) {
                return true;
            }
        }
        return false;
    }

    /**
     * Registro a reutilizar: primero el que ya tiene ese valor (activo, suspendido o de baja:
     * se reactiva); si no, el que se está editando (cambio de valor).
     */
    private function registroParaGuardar($existentes, array $usados, $campo, $valor, $idEditado)
    {
        $mismoValor = $existentes->first(function ($r) use ($usados, $campo, $valor) {
            return !isset($usados[$r->id]) && $r->{$campo} === $valor;
        });
        if ($mismoValor || !$idEditado) {
            return $mismoValor;
        }

        return $existentes->first(function ($r) use ($usados, $idEditado) {
            return !isset($usados[$r->id]) && (int) $r->id === (int) $idEditado;
        });
    }

    /**
     * Lo que el usuario quitó queda de baja con fecha (las bajas anteriores conservan la suya).
     */
    private function darDeBajaNoUsados($existentes, array $usados)
    {
        foreach ($existentes as $registro) {
            if (!isset($usados[$registro->id]) && $registro->deleted_at === null) {
                $registro->cambiarEstado('baja');
            }
        }
    }

    /**
     * Celular activo en otro contacto activo (no eliminado ni inactivo).
     */
    private function celularEnOtroContacto(array $celulares, $idContactoActual)
    {
        if (empty($celulares)) {
            return null;
        }

        $dup = ContactoTelefono::whereIn('telefono', $celulares)
            ->where('estado', 1)
            ->whereNull('deleted_at')
            ->where('contacto_id', '!=', $idContactoActual ?: 0)
            ->whereHas('contacto', function ($q) {
                $q->where('estado', 1);
            })
            ->first();
        if ($dup) {
            return ['valor' => $dup->telefono, 'contacto' => $dup->contacto];
        }

        // Contactos antiguos con el número solo en el campo directo.
        $directo = Contacto::whereIn('celular', $celulares)
            ->where('estado', 1)
            ->where('id', '!=', $idContactoActual ?: 0)
            ->first();

        return $directo ? ['valor' => $directo->celular, 'contacto' => $directo] : null;
    }

    /**
     * 422 de dato repetido: mensaje que dice quién lo tiene y qué hacer, y los datos para que la
     * pantalla marque la casilla y ofrezca "Ver contacto".
     */
    private function respuestaDuplicado($campo, array $existente)
    {
        $contacto = $existente['contacto'];
        $nombre = trim($contacto->nombres . ' ' . $contacto->apellidos);
        $empresa = $contacto->empresa ? $contacto->empresa->nombreVisible() : '';
        $valor = $campo === 'telefono' ? Telefono::formatear($existente['valor']) : $existente['valor'];
        $dato = $campo === 'telefono' ? 'El celular' : 'El correo';

        return response()->json([
            'success'   => false,
            'message'   => "{$dato} {$valor} ya está registrado en el contacto {$nombre}"
                . ($empresa !== '' ? " ({$empresa})" : '')
                . '. Si esa persona cambió de empresa, inactiva ese contacto y vuelve a guardar.',
            'duplicado' => [
                'campo'           => $campo,
                'valor'           => $existente['valor'],
                'valor_formato'   => $valor,
                'contacto_id'     => $contacto->id,
                'contacto_nombre' => $nombre,
                'empresa_nombre'  => $empresa,
            ],
        ], 422);
    }

    /**
     * Correo activo en otro contacto activo (no eliminado ni inactivo).
     */
    private function correoEnOtroContacto(array $correos, $idContactoActual)
    {
        if (empty($correos)) {
            return null;
        }

        $dup = ContactoEmail::whereIn('email', $correos)
            ->where('estado', 1)
            ->whereNull('deleted_at')
            ->where('contacto_id', '!=', $idContactoActual ?: 0)
            ->whereHas('contacto', function ($q) {
                $q->where('estado', 1);
            })
            ->first();
        if ($dup) {
            return ['valor' => $dup->email, 'contacto' => $dup->contacto];
        }

        $directo = Contacto::whereIn('email', $correos)
            ->where('estado', 1)
            ->where('id', '!=', $idContactoActual ?: 0)
            ->first();

        return $directo ? ['valor' => $directo->email, 'contacto' => $directo] : null;
    }

    public function record($id)
    {
        return new ContactoResource(Contacto::query()->findOrFail($id));
    }

    /**
     * Eliminar: solo para registros duplicados o mal hechos. Un contacto con actividad
     * no se elimina (se inactiva). Da de baja, con fecha, sus teléfonos y correos.
     */
    public function destroy($id)
    {
        try {
            $contacto = Contacto::query()->findOrFail($id);

            if (!empty($this->actividadDelContacto($contacto->id))) {
                $nombre = trim($contacto->nombres . ' ' . $contacto->apellidos);
                return [
                    'success'       => false,
                    'con_actividad' => true,
                    'nombre'        => $nombre,
                    'message'       => "{$nombre} tiene historial registrado y no se puede eliminar."
                        . ' Si la persona dejó la empresa o ya no es el contacto, usa Inactivar.',
                ];
            }

            DB::connection($contacto->getConnectionName())->transaction(function () use ($contacto) {
                $ahora = now();
                foreach ([ContactoTelefono::class, ContactoEmail::class] as $modelo) {
                    $modelo::where('contacto_id', $contacto->id)
                        ->whereNull('deleted_at')
                        ->update(['estado' => 0, 'deleted_at' => $ahora, 'updated_at' => $ahora]);
                }
                $contacto->estado = 0;
                $contacto->save();
                $contacto->delete();
            });

            return [
                'success' => true,
                'message' => 'Contacto eliminado con éxito'
            ];
        } catch (Exception $e) {
            Log::error("Error al eliminar el contacto: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'Error inesperado, no se pudo eliminar el contacto.'
            ];
        }
    }

    /**
     * Tablas donde el contacto deja historial. Con alguna fila, no se elimina.
     */
    private function actividadDelContacto($contactoId)
    {
        $tablas = [
            'requerimiento'        => 'requerimientos',
            'quotation_prequotes'  => 'pre-cotizaciones',
            'proyecto'             => 'proyectos',
            'com07_oportunidades'  => 'oportunidades',
            'contacto_interaccion' => 'interacciones',
        ];
        $conexion = (new Contacto)->getConnectionName();
        $actividad = [];

        foreach ($tablas as $tabla => $etiqueta) {
            if (Schema::connection($conexion)->hasTable($tabla)
                && DB::connection($conexion)->table($tabla)->where('contacto_id', $contactoId)->exists()) {
                $actividad[] = $etiqueta;
            }
        }

        return $actividad;
    }

    /**
     * Antes de eliminar: la pantalla pregunta si el contacto tiene historial (solo lectura)
     * para mostrar de entrada la ventana de "no se puede eliminar" con la opción de inactivar.
     */
    public function historial($id)
    {
        $contacto = Contacto::query()->findOrFail($id);

        return [
            'success'         => true,
            'tiene_historial' => !empty($this->actividadDelContacto($contacto->id)),
            'activo'          => (int) $contacto->estado === 1,
            'nombre'          => trim($contacto->nombres . ' ' . $contacto->apellidos),
        ];
    }

    /**
     * Inactivar: el contacto dejó la empresa. Conserva su historial; sus teléfonos y
     * correos quedan suspendidos (libres para otro contacto) y se recuperan al activarlo.
     */
    public function inactivar($id)
    {
        try {
            $contacto = Contacto::query()->findOrFail($id);

            DB::connection($contacto->getConnectionName())->transaction(function () use ($contacto) {
                $contacto->estado = 0;
                $contacto->save();
                foreach ([ContactoTelefono::class, ContactoEmail::class] as $modelo) {
                    $modelo::where('contacto_id', $contacto->id)
                        ->where('estado', 1)
                        ->whereNull('deleted_at')
                        ->update(['estado' => 0, 'updated_at' => now()]);
                }
            });

            return [
                'success' => true,
                'message' => 'Contacto inactivado con éxito'
            ];

        } catch (Exception $e) {
            Log::error("Error al inhabilitar el contacto: {$e->getMessage()}");

            return  [
                'success' => false,
                'message' => 'Error inesperado, no se pudo inhabilitar el contacto.'
            ];
        }
    }

    public function disableMassive(Request $request)
    {
        return $this->accionMasiva($request, 'inactivar', 'Se inactivaron');
    }

    /**
     * Activar: recupera los teléfonos y correos suspendidos. El celular o correo que ya
     * usa otro contacto activo no se recupera (queda de baja) y se avisa.
     */
    public function activar($id)
    {
        try {
            $contacto = Contacto::query()->findOrFail($id);
            $omitidos = [];

            DB::connection($contacto->getConnectionName())->transaction(function () use ($contacto, &$omitidos) {
                $contacto->estado = 1;
                $contacto->save();

                $suspendidos = ContactoTelefono::where('contacto_id', $contacto->id)
                    ->where('estado', 0)->whereNull('deleted_at')->get();
                foreach ($suspendidos as $telefono) {
                    if (Telefono::esCelular($telefono->telefono)
                        && $this->celularEnOtroContacto([$telefono->telefono], $contacto->id)) {
                        $telefono->cambiarEstado('baja');
                        $omitidos[] = Telefono::formatear($telefono->telefono);
                        continue;
                    }
                    $telefono->cambiarEstado('activo');
                }

                $suspendidos = ContactoEmail::where('contacto_id', $contacto->id)
                    ->where('estado', 0)->whereNull('deleted_at')->get();
                foreach ($suspendidos as $email) {
                    if ($this->correoEnOtroContacto([$email->email], $contacto->id)) {
                        $email->cambiarEstado('baja');
                        $omitidos[] = $email->email;
                        continue;
                    }
                    $email->cambiarEstado('activo');
                }

                $this->sincronizarPrincipales($contacto->id);
            });

            $mensaje = 'Contacto activado con éxito';
            if (!empty($omitidos)) {
                $mensaje .= '. No se recuperaron (ya los usa otro contacto): ' . implode(', ', $omitidos);
            }

            return ['success' => true, 'message' => $mensaje];

        } catch (Exception $e) {
            Log::error("Error al activar el contacto: {$e->getMessage()}");

            return  ['success' => false, 'message' => 'Error inesperado, no se pudo activar el contacto'];
        }
    }

    /**
     * Campos directos contacto.celular / contacto.email = principales activos.
     */
    private function sincronizarPrincipales($contactoId)
    {
        $celular = ContactoTelefono::where('contacto_id', $contactoId)->where('estado', 1)
            ->where('slot', ContactoTelefono::SLOT_CELULAR_PRINCIPAL)->value('telefono');
        $email = ContactoEmail::where('contacto_id', $contactoId)->where('estado', 1)
            ->orderByDesc('es_principal')->orderBy('id')->value('email');

        Contacto::where('id', $contactoId)->update(['celular' => $celular, 'email' => $email]);
    }

    public function enableMassive(Request $request)
    {
        return $this->accionMasiva($request, 'activar', 'Se activaron');
    }

    public function destroyMassive(Request $request)
    {
        return $this->accionMasiva($request, 'destroy', 'Se eliminaron');
    }

    /**
     * Aplica eliminar / inactivar / activar a los seleccionados y resume el resultado
     * (incluye los avisos: contactos con actividad o datos no recuperados).
     */
    private function accionMasiva(Request $request, $accion, $verbo)
    {
        $selected = collect($request->selected);
        $count = $selected->count();

        if ($count == 0) {
            return [
                'success'  => false,
                'message' => 'Tiene que seleccionar los items'
            ];
        }

        $hechos = 0;
        $avisos = [];
        $conHistorial = [];
        $selected->each(function ($item) use ($accion, &$hechos, &$avisos, &$conHistorial) {
            // La pantalla envía la fila completa del listado; también se acepta solo el id.
            $id = is_array($item) ? ($item['id'] ?? null) : $item;
            $response = $this->{$accion}($id);
            if ($response['success']) {
                $hechos += 1;
            }
            if (!empty($response['con_actividad'])) {
                $conHistorial[] = $response['nombre'];
                return;
            }
            if (!$response['success'] || strpos($response['message'], 'No se recuperaron') !== false) {
                $avisos[] = $response['message'];
            }
        });

        $mensaje = "{$verbo} {$hechos} contactos de {$count} contactos seleccionados";
        if (!empty($conHistorial)) {
            $mensaje .= '. ' . count($conHistorial) . ' no se eliminaron porque tienen historial registrado ('
                . implode(', ', $conHistorial) . '); puedes inactivarlos';
        }
        if (!empty($avisos)) {
            $mensaje .= '. ' . implode(' ', $avisos);
        }

        return [
            'success'       => true,
            'message'       => $mensaje,
            'con_historial' => count($conHistorial),
        ];
    }

    public function empresa($empresaId)
    {
        try {
            $contactos = Contacto::query()->where('empresa_id', $empresaId)
                ->where('estado', 1)
                ->with(['emails', 'telefonos'])  // Solo activos
                ->orderBy('nombres')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $contactos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar contactos'
            ], 500);
        }
    }

    public function list(Request $request)
    {
        $query = Contacto::query()
            ->with(['empresa:id,razon_social,nombre_comercial', 'emails', 'telefonos'])
            ->select([
                'id',
                'empresa_id',
                'nombres',
                'apellidos',
                'cargo',
                'telefono',
                'email',
                'estado',
                'created_at',
            ]);

        if ($empresaId = $request->input('empresa_id')) {
            $query->where('empresa_id', (int) $empresaId);
        }

        if ($contactoId = $request->input('contacto_id')) {
            $query->where('id', (int) $contactoId);
        }

        if ($search = $request->input('search.value', $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'like', "%{$search}%")
                    ->orWhere('apellidos', 'like', "%{$search}%")
                    ->orWhere('cargo', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $serverSide = filter_var($request->input('serverSide'), FILTER_VALIDATE_BOOLEAN);
        if ($serverSide) {
            $columns  = ['id', 'empresa_id', 'nombres', 'apellidos', 'cargo', 'telefono', 'email', 'estado', 'created_at'];
            $orderIdx = (int) ($request->input('order.0.column', 0));
            $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $orderCol = $columns[$orderIdx] ?? 'id';
            $query->orderBy($orderCol, $orderDir);

            $draw   = (int) $request->input('draw', 1);
            $start  = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);

            $recordsTotal    = Contacto::count();
            $recordsFiltered = (clone $query)->count();
            $data = $query->skip($start)->take($length)->get();

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $data,
            ]);
        }

        $query->orderBy('id', 'desc');
        return response()->json($query->get());
    }

    public function show($id)
    {
        $contacto = Contacto::with(['empresa:id,razon_social,nombre_comercial', 'emails', 'telefonos'])->find($id);
        if (!$contacto) {
            return response()->json(['ok' => false, 'error' => 'Contacto no encontrado'], 404);
        }
        return response()->json(['ok' => true, 'data' => $contacto]);
    }

    public function update(Request $request, $id)
    {
        $contacto = Contacto::find($id);
        if (!$contacto) {
            return response()->json(['ok' => false, 'error' => 'Contacto no encontrado'], 404);
        }

        $data = $request->validate([
            'empresa_id' => ['sometimes', 'required', 'integer', 'exists:empresa,id'],
            'nombres'    => ['sometimes', 'required', 'string', 'max:191'],
            'apellidos'  => ['nullable', 'string', 'max:191'],
            'cargo'      => ['nullable', 'string', 'max:191'],
            'telefono'   => ['nullable', 'string', 'max:50'],
            'email'      => ['nullable', 'email', 'max:191'],
            'estado'     => ['nullable', 'integer'],
            'direccion'  => ['nullable', 'string'],
            'referencia' => ['nullable', 'string'],
            'adicionales' => ['nullable', 'string'],
        ]);

        $contacto->fill($data)->save();
        return response()->json(['ok' => true, 'data' => $contacto]);
    }

    /**
     * Listar interacciones de un contacto
     */
    public function interacciones($id)
    {
        try {
            $contacto = Contacto::findOrFail($id);

            $items = ContactoInteraccion::where('contacto_id', $id)
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
            Log::error('Error al listar interacciones de contacto: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar nueva interacción para un contacto
     */
    public function storeInteraccion($id, Request $request)
    {
        try {
            $contacto = Contacto::findOrFail($id);

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
                    ->store('contactos/interacciones', 'public');
            }

            // Requiere seguimiento
            $requiereSeguimiento = false;
            $valor = $request->input('requiere_seguimiento');
            if ($valor === true || $valor === 'true' || $valor === 1 || $valor === '1') {
                $requiereSeguimiento = true;
            }

            // Crear interacción
            $interaccion = ContactoInteraccion::create([
                'contacto_id'          => $id,
                'canal'                => $request->input('canal'),
                'resumen'              => $request->input('resumen'),
                'requiere_seguimiento' => $requiereSeguimiento,
                'proximo_seguimiento'  => $request->input('proximo_seguimiento'),
                'archivo_adjunto'      => $archivoPath,
                'user_id'              => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'ok'   => true,
                'msg'  => 'Interacción registrada correctamente.',
                'data' => $interaccion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear interacción de contacto: ' . $e->getMessage());

            return response()->json([
                'ok'  => false,
                'msg' => 'Error al registrar la interacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una interacción existente de un contacto
     */
    public function updateInteraccion($id, $interaccionId, Request $request)
    {
        try {
            $interaccion = ContactoInteraccion::where('contacto_id', $id)
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
            Log::error('Error al actualizar interacción de contacto: ' . $e->getMessage());
            return response()->json([
                'ok'  => false,
                'msg' => 'Error al actualizar la interacción: ' . $e->getMessage(),
            ], 500);
        }
    }

}
