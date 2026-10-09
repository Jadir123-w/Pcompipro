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
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ContactosController extends Controller
{
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
        return [
            'razon_social' => 'Empresa',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'celular' => 'Celular',
            'telefono' => 'Telefono',
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
            ->with([
                'empresa:id,razon_social',
                'emails',      // Solo activos (estado = 1)
                'telefonos'    // Solo activos (estado = 1)
            ])
            // Ordenar por última actualización (recién creados/editados arriba).
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc');

        if ($request->get('column') && $request->get('value')) {
            $column = $request->get('column');
            $value = $request->get('value');

            if ($column === 'razon_social') {
                // Buscar empresa por razón social Y por nombre comercial
                // (requerimiento del cliente: los ejecutivos identifican
                // empresas por nombre comercial más rápido).
                $records->whereHas('empresa', function ($q) use ($value) {
                    $q->where('razon_social', 'LIKE', "%{$value}%")
                      ->orWhere('nombre_comercial', 'LIKE', "%{$value}%");
                });
            } elseif (in_array($column, ['nombres', 'apellidos', 'cargo'])) {
                $records->where($column, 'LIKE', "%{$value}%");
            } elseif ($column === 'celular') {
                // Buscar en campo directo Y en contacto_telefonos (tipos movil/casa)
                $records->where(function ($q) use ($value) {
                    $q->where('celular', 'LIKE', "%{$value}%")
                      ->orWhereHas('telefonos', function ($q2) use ($value) {
                          $q2->where('telefono', 'LIKE', "%{$value}%")
                             ->whereIn('tipo', ['movil', 'casa']);
                      });
                });
            } elseif ($column === 'telefono') {
                // Buscar en campo directo Y en contacto_telefonos
                $records->where(function ($q) use ($value) {
                    $q->where('telefono', 'LIKE', "%{$value}%")
                      ->orWhereHas('telefonos', function ($q2) use ($value) {
                          $q2->where('telefono', 'LIKE', "%{$value}%");
                      });
                });
            } elseif ($column === 'email') {
                // Buscar en campo directo Y en contacto_emails
                $records->where(function ($q) use ($value) {
                    $q->where('email', 'LIKE', "%{$value}%")
                      ->orWhereHas('emails', function ($q2) use ($value) {
                          $q2->where('email', 'LIKE', "%{$value}%");
                      });
                });
            }
        }

        return new ContactosCollection($records->paginate(config('tenant.items_per_page')));
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
            $num = trim($t['telefono'] ?? '');
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

        // 2) Unicidad: el telefono no puede estar registrado en otro contacto.
        //    Se revisa tanto en contacto_telefonos como en el campo directo
        //    contacto.telefono (contactos antiguos sin fila en contacto_telefonos).
        $numeros = [];
        foreach ($telefonosInput as $t) {
            $num = trim($t['telefono'] ?? '');
            if ($num !== '') $numeros[] = $num;
        }
        if (!empty($numeros)) {
            $telDup = ContactoTelefono::whereIn('telefono', $numeros)
                ->where('estado', 1)
                ->where('contacto_id', '!=', $idContactoActual ?: 0)
                ->first();
            $telExistente = $telDup ? $telDup->telefono : null;

            if (!$telExistente) {
                // El numero directo puede estar en contacto.celular o contacto.telefono.
                $contactoTelDup = Contacto::where(function ($q) use ($numeros) {
                        $q->whereIn('telefono', $numeros)
                          ->orWhereIn('celular', $numeros);
                    })
                    ->where('estado', 1)
                    ->where('id', '!=', $idContactoActual ?: 0)
                    ->first();
                if ($contactoTelDup) {
                    $telExistente = in_array($contactoTelDup->celular, $numeros, true)
                        ? $contactoTelDup->celular
                        : $contactoTelDup->telefono;
                }
            }

            if ($telExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro contacto con el telefono ' . $telExistente . '.',
                ], 422);
            }
        }

        // 3) Unicidad: el correo no puede estar registrado en otro contacto.
        //    Se revisa tanto en contacto_emails como en el campo directo
        //    contacto.email (contactos antiguos sin fila en contacto_emails).
        $correos = [];
        foreach ($emailsInput as $e) {
            $em = trim($e['email'] ?? '');
            if ($em !== '') $correos[] = $em;
        }
        if (!empty($correos)) {
            $emailDup = ContactoEmail::whereIn('email', $correos)
                ->where('estado', 1)
                ->where('contacto_id', '!=', $idContactoActual ?: 0)
                ->first();
            $correoExistente = $emailDup ? $emailDup->email : null;

            if (!$correoExistente) {
                $contactoEmailDup = Contacto::whereIn('email', $correos)
                    ->where('estado', 1)
                    ->where('id', '!=', $idContactoActual ?: 0)
                    ->first();
                $correoExistente = $contactoEmailDup ? $contactoEmailDup->email : null;
            }

            if ($correoExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro contacto con el correo ' . $correoExistente . '.',
                ], 422);
            }
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
        if (empty($telefonos)) {
            // Si no hay teléfonos, marcar todos como inactivos (borrado lógico)
            ContactoTelefono::where('contacto_id', $contactoId)
                ->where('estado', 1)
                ->update(['estado' => 0, 'updated_at' => now()]);

            // El contacto queda sin celular en el listado.
            \Modules\Comercial\Models\Contacto::where('id', $contactoId)
                ->update(['celular' => null]);
            return;
        }

        // IDs a conservar (activos)
        $idsAConservar = collect($telefonos)->pluck('id')->filter()->toArray();

        // Marcar como inactivos los que ya no están en la lista
        ContactoTelefono::where('contacto_id', $contactoId)
            ->where('estado', 1)
            ->whereNotIn('id', $idsAConservar)
            ->update(['estado' => 0, 'updated_at' => now()]);

        // Liberar transitoriamente los números de los teléfonos que se
        // conservan (los que traen id), poniéndoles un valor temporal único.
        // Esto permite INTERCAMBIAR (swap) los números entre dos teléfonos
        // del mismo contacto en un solo guardado sin chocar con el índice
        // único (contacto_id, telefono, estado). Sin este paso, reasignar el
        // número de un teléfono al que todavía tiene otro produce el error
        // 1062 ("teléfono repetido"). Todo ocurre dentro de la transacción
        // abierta en store(), así que estos valores temporales nunca quedan
        // persistidos: el loop siguiente los reescribe con el valor final.
        foreach ($telefonos as $row) {
            if (!empty($row['id']) && trim($row['telefono'] ?? '') !== '') {
                ContactoTelefono::where('id', $row['id'])
                    ->where('contacto_id', $contactoId)
                    ->update(['telefono' => '__tmp_' . $row['id']]);
            }
        }

        // Slots válidos (defensa frente a payload corrupto: cae a 'adicional').
        $slotsValidos = [
            ContactoTelefono::SLOT_CELULAR_PRINCIPAL,
            ContactoTelefono::SLOT_FIJO,
            ContactoTelefono::SLOT_ADICIONAL,
        ];

        // Actualizar o crear cada teléfono
        foreach ($telefonos as $row) {
            $telefono = trim($row['telefono'] ?? '');

            if (empty($telefono)) {
                continue;
            }

            $slot = $row['slot'] ?? null;
            if (!in_array($slot, $slotsValidos, true)) {
                $slot = ContactoTelefono::SLOT_ADICIONAL;
            }

            $esPrincipal = ($slot === ContactoTelefono::SLOT_CELULAR_PRINCIPAL);
            $tipo = $row['tipo'] ?? 'movil';
            $estado = (int)($row['estado'] ?? 1);

            // Actualizar o crear
            if (!empty($row['id'])) {
                // Actualizar existente
                ContactoTelefono::where('id', $row['id'])
                    ->where('contacto_id', $contactoId)
                    ->update([
                        'telefono' => $telefono,
                        'tipo' => $tipo,
                        'es_principal' => $esPrincipal ? 1 : 0,
                        'slot' => $slot,
                        'estado' => $estado,
                        'updated_at' => now(),
                    ]);
            } else {
                // Crear nuevo
                ContactoTelefono::create([
                    'contacto_id' => $contactoId,
                    'telefono' => $telefono,
                    'tipo' => $tipo,
                    'es_principal' => $esPrincipal ? 1 : 0,
                    'slot' => $slot,
                    'estado' => $estado,
                ]);
            }
        }

        // Sincronizar contacto.celular con el slot='celular_principal' activo.
        // Si no hay celular principal, queda en null (el listado muestra vacío).
        // NO se hace auto-promote de otro teléfono — los slots son explícitos.
        $principal = ContactoTelefono::where('contacto_id', $contactoId)
            ->where('estado', 1)
            ->where('slot', ContactoTelefono::SLOT_CELULAR_PRINCIPAL)
            ->first();

        \Modules\Comercial\Models\Contacto::where('id', $contactoId)
            ->update(['celular' => $principal ? $principal->telefono : null]);
    }

    /**
     * Guardar emails del contacto con borrado lógico
     */
    protected function saveEmails($contactoId, array $emails)
    {
        if (empty($emails)) {
            // Si no hay emails, marcar todos como inactivos (borrado lógico)
            ContactoEmail::where('contacto_id', $contactoId)
                ->where('estado', 1)
                ->update(['estado' => 0, 'updated_at' => now()]);
            return;
        }

        // IDs a conservar (activos)
        $idsAConservar = collect($emails)->pluck('id')->filter()->toArray();

        // Marcar como inactivos los que ya no están en la lista
        ContactoEmail::where('contacto_id', $contactoId)
            ->where('estado', 1)
            ->whereNotIn('id', $idsAConservar)
            ->update(['estado' => 0, 'updated_at' => now()]);

        // Actualizar o crear cada email
        foreach ($emails as $row) {
            $email = trim($row['email'] ?? '');
            
            if (empty($email)) {
                continue;
            }

            $esPrincipal = (bool)($row['es_principal'] ?? false);
            $estado = (int)($row['estado'] ?? 1);

            // Si es principal, quitar la marca de principal de los demás
            if ($esPrincipal) {
                ContactoEmail::where('contacto_id', $contactoId)
                    ->where('estado', 1)
                    ->update(['es_principal' => 0]);
            }

            // Actualizar o crear
            if (!empty($row['id'])) {
                // Actualizar existente
                ContactoEmail::where('id', $row['id'])
                    ->where('contacto_id', $contactoId)
                    ->update([
                        'email' => $email,
                        'es_principal' => $esPrincipal ? 1 : 0,
                        'estado' => $estado,
                        'updated_at' => now(),
                    ]);
            } else {
                // Crear nuevo
                ContactoEmail::create([
                    'contacto_id' => $contactoId,
                    'email' => $email,
                    'es_principal' => $esPrincipal ? 1 : 0,
                    'estado' => $estado,
                ]);
            }
        }

        // Si ninguno es principal, marcar el primero activo como principal
        $principalExists = ContactoEmail::where('contacto_id', $contactoId)
            ->where('estado', 1)
            ->where('es_principal', 1)
            ->exists();

        if (!$principalExists) {
            $first = ContactoEmail::where('contacto_id', $contactoId)
                ->where('estado', 1)
                ->orderBy('id')
                ->first();
            
            if ($first) {
                $first->es_principal = 1;
                $first->save();
            }
        }

        // Sincronizar el email principal al campo directo contacto.email
        $principal = ContactoEmail::where('contacto_id', $contactoId)
            ->where('estado', 1)
            ->where('es_principal', 1)
            ->first();

        \Modules\Comercial\Models\Contacto::where('id', $contactoId)
            ->update(['email' => $principal ? $principal->email : null]);
    }

    public function record($id)
    {
        return new ContactoResource(Contacto::query()->findOrFail($id));
    }

    public function destroy($id)
    {
        try {
            Contacto::query()->where('id', $id)->update([
                'deleted_at' => now(),
                'estado' => 0,
            ]);

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

    protected function inactivar($id)
    {
        try {
            $contacto = Contacto::query()->findOrFail($id);
            
            // ✅ CORRECCIÓN 2: No establecer manualmente updated_at
            $contacto->estado = 0;
            // ❌ ELIMINADO: $contacto->updated_at = now();
            $contacto->save();

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
        $selected = collect($request->selected);
        $itemDisabled = 0;
        $count = $selected->count();

        if ($count == 0) {
            return [
                'success'  => false,
                'message' => 'Tiene que seleccionar los items'
            ];
        }

        $selected->each(function ($id) use (&$itemDisabled) {
            $response = $this->inactivar($id);
            if ($response['success']) $itemDisabled += 1;
        });

        return [
            'success' => true,
            'message' => "Se inactivaron {$itemDisabled} contactos de {$count} contactos seleccionados"
        ];
    }

    protected function activar($id)
    {
        try {
            $contacto = Contacto::query()->findOrFail($id);
            
            // ✅ CORRECCIÓN 3: No establecer manualmente updated_at
            $contacto->estado = 1;
            // ❌ ELIMINADO: $contacto->updated_at = now();
            $contacto->save();

            return [
                'success' => true,
                'message' => 'Contacto activado con éxito'
            ];

        } catch (Exception $e) {
            Log::error("Error al activar el contacto: {$e->getMessage()}");

            return  ['success' => false, 'message' => 'Error inesperado, no se pudo activar el contacto'];
        }
    }

    public function enableMassive(Request $request)
    {
        $selected = collect($request->selected);
        $itemEnable = 0;
        $count = $selected->count();

        if ($count == 0) {
            return [
                'success'  => false,
                'message' => 'Tiene que seleccionar los items'
            ];
        }

        $selected->each(function ($id) use (&$itemEnable) {
            $response = $this->activar($id);
            if ($response['success']) $itemEnable += 1;
        });

        return [
            'success' => true,
            'message' => "Se activaron {$itemEnable} contactos de {$count} contactos seleccionados"
        ];
    }

    public function destroyMassive(Request $request)
    {
        $selected = collect($request->selected);
        $itemDeleted = 0;
        $count = $selected->count();

        if ($count == 0) {
            return [
                'success'  => false,
                'message' => 'Tiene que seleccionar los items'
            ];
        }

        $selected->each(function ($id) use (&$itemDeleted) {
            $response = $this->destroy($id);
            if ($response['success']) $itemDeleted += 1;
        });

        return [
            'success' => true,
            'message' => "Se eliminaron {$itemDeleted} contactos de {$count} contactos seleccionados"
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
            ->with(['empresa:id,razon_social', 'emails', 'telefonos'])
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
        $contacto = Contacto::with(['empresa:id,razon_social', 'emails', 'telefonos'])->find($id);
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
