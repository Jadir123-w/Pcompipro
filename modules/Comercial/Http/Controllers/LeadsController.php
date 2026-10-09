<?php

namespace Modules\Comercial\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Listeners\Comercial\SyncEmpresaToPersons;
use Modules\Comercial\Http\Resources\LeadResource;
use Modules\Comercial\Http\Resources\LeadsCollection;
use Modules\Comercial\Models\Contacto;
use Modules\Comercial\Models\ContactoEmail;
use Modules\Comercial\Models\ContactoTelefono;
use Modules\Comercial\Models\Empresa;
use Modules\Comercial\Models\Lead;
use Modules\Comercial\Models\Requerimiento;

class LeadsController extends Controller
{
    /**
     * RUCs genéricos que no deben usarse para detectar empresas
     */
    private const RUCS_GENERICOS = [
        '00000000000',
        '00000000001',
        '11111111111',
        '99999999999',
    ];

    /**
     * Display a listing of the resource.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\View\View
     */
    public function index()
    {
        return view('comercial::leads.index');
    }

    public function columns()
    {
      return [
        'razon_social' => 'Empresa',
        'origen' => 'Origen',
        'etapa' => 'Etapa',
        'contacto' => 'Contacto',
        'email' => 'Email',
        'prioridad' => 'Prioridad',
      ];
    }

    public function tables()
    {
      $list_empresas = Lead::query()->where('estado', '=', 1)
        ->select(['id', 'razon_social'])
        ->get();

      $list_origenes = [
        (object)["id" => 'web', "name" => 'Web'],
        (object)["id" => 'n8n', "name" => 'N8N'],
        (object)["id" => 'correo', "name" => 'Correo Electronico'],
        (object)["id" => 'manual', "name" => 'Manual'],
        (object)["id" => 'chatbot_mkt01', "name" => 'Chatbot IA'],
      ];

      $list_etapas = [
        (object)["id" => 'nuevo', "name" => 'Nuevo'],
        (object)["id" => 'calificado', "name" => 'Calificado'],
        (object)["id" => 'en_proceso', "name" => 'En Proceso'],
      ];

      $list_prioridades = [
        (object)["id" => 'alta', "name" => 'Alta'],
        (object)["id" => 'media', "name" => 'Media'],
        (object)["id" => 'baja', "name" => 'Baja'],
      ];

      $list_estados_revision = [
        (object)["id" => 'pendiente', "name" => 'Pendiente'],
        (object)["id" => 'en_revision', "name" => 'En Revision'],
        (object)["id" => 'convertido', "name" => 'Convertido'],
      ];

      return compact('list_empresas', 'list_origenes', 'list_etapas', 'list_prioridades', 'list_estados_revision');
    }

    public function records(Request $request)
    {
      $records = Lead::query()
        ->with('empresa:id,razon_social')
        ->where(function($q) {
          $q->whereNull('estado_revision')
            ->orWhere('estado_revision', '!=', 'convertido');
        })
        // Ordenar por última actualización (recién creados/editados arriba).
        ->orderBy('updated_at', 'desc')
        ->orderBy('id', 'desc');

      if ($request->get('column') && $request->get('value')) {
        if ($request->get('column') === 'razon_social') {
          $records->whereHas('empresa', function ($q) use ($request) {
            $q->where('razon_social', 'LIKE', "%{$request->get('value')}%");
          });
        }
        if ($request->get('column') === 'origen') {
          $records->where('origen', 'LIKE', "%{$request->get('value')}%");
        }
        if ($request->get('column') === 'etapa') {
          $records->where('etapa', 'LIKE', "%{$request->get('value')}%");
        }
        if ($request->get('column') === 'contacto') {
          $records->where('contacto', 'LIKE', "%{$request->get('value')}%");
        }
        if ($request->get('column') === 'email') {
          $records->where('email', 'LIKE', "%{$request->get('value')}%");
        }
        if ($request->get('column') === 'prioridad') {
          $records->where('prioridad', 'LIKE', "%{$request->get('value')}%");
        }
      }

      return new LeadsCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function store(Request $request)
    {
      if (!$request->filled('origen')) {
        $request->merge(['origen' => 'web']);
      }
      if (!$request->filled('etapa')) {
        $request->merge(['etapa' => 'nuevo']);
      }

      $data = $request->validate([
        'empresa_id' => ['required'],
        'origen'        => ['required','string','max:100'],
        'etapa'         => ['required','string','max:100'],
        'descripcion'   => ['nullable','string','max:500'],
        'contacto'      => ['nullable','string','max:191'],
        'telefono'      => ['nullable','string','max:50'],
        'email'         => ['nullable','email','max:191'],
        'prioridad'     => ['nullable','string','max:50'],
        'monto_estimado'=> ['nullable','numeric'],
        'fecha_estimada'=> ['nullable','date'],
        'estado'        => ['nullable','string','max:50'],
      ]);

      $empresa = Empresa::query()->where('id', $data['empresa_id'])->first();

      $data['etapa']  = isset($data['etapa']) ? $data['etapa'] : 'nuevo';
      $data['ruc']  = $empresa ? $empresa->ruc : $data['empresa_id'];
      if (!$empresa) {
        $data['empresa_id'] = null;
      }

      $data['empresa_detectada_id'] = $empresa ? $empresa->id : null;

      $id_lead = $request->input('id');

      if (empty($id_lead)) {

        $data['estado_revision']     = 'pendiente';
        $data['conflicto_detectado'] = 0;
        $data['procesado']           = 0;
        $data['estado']              = isset($data['estado']) ? $data['estado'] : 'activo';
        $data['created_at'] = now();
        $lead = Lead::create($data);

        return response()->json([
          'success' => true,
          'message' => 'Lead creado correctamente',
          'data' => ['id' => $lead->id]
        ]);
      } else {

        $lead = Lead::find($id_lead);
        $data['updated_at'] = now();
        $lead->update($data);

        return response()->json([
          'success' => true,
          'message' => 'Lead actualizado correctamente',
          'data' => ['id' => $id_lead]
        ]);
      }
    }

  public function record($id)
  {
    return new LeadResource(Lead::query()->with('empresa')->findOrFail($id));
  }

  /**
   * Verifica si un RUC es genérico (no válido para detectar empresa)
   */
  private function esRucGenerico($ruc)
  {
    return empty($ruc) || in_array($ruc, self::RUCS_GENERICOS);
  }

  /**
   * Normaliza un teléfono para comparación (solo dígitos)
   */
  private function normalizarTelefono($telefono)
  {
    if (empty($telefono)) {
      return null;
    }
    // Quitar todo excepto dígitos
    return preg_replace('/[^0-9]/', '', $telefono);
  }

  /**
   * Busca contactos que coincidan por email O teléfono
   * ✅ CORREGIDO: Lógica OR correcta y búsqueda en tablas relacionadas
   */
  private function buscarContactosCoincidentes($lead)
  {
    $contactos = collect();
    $emailLead = trim($lead->email ?? '');
    $telefonoLead = $this->normalizarTelefono($lead->telefono);

    // Si no hay email ni teléfono, no hay nada que buscar
    if (empty($emailLead) && empty($telefonoLead)) {
      return $contactos;
    }

    // 1) Buscar en tabla contacto (campos email y telefono directos)
    $queryContactos = Contacto::query()->whereNull('deleted_at');

    $queryContactos->where(function($q) use ($emailLead, $telefonoLead) {
      // ✅ CORRECCIÓN: Usar OR correctamente
      if (!empty($emailLead)) {
        $q->orWhere('email', $emailLead);
      }
      if (!empty($telefonoLead)) {
        // Buscar teléfono normalizado (solo dígitos)
        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?", ['%' . $telefonoLead . '%']);
      }
    });

    $contactosDirectos = $queryContactos->get();
    $contactos = $contactos->merge($contactosDirectos);

    // 2) Buscar en tabla contacto_emails
    if (!empty($emailLead)) {
      $contactoEmails = ContactoEmail::query()
        ->where('email', $emailLead)
        ->where('estado', 1)
        ->get();

      foreach ($contactoEmails as $ce) {
        $contacto = Contacto::query()
          ->where('id', $ce->contacto_id)
          ->whereNull('deleted_at')
          ->first();
        
        if ($contacto && !$contactos->contains('id', $contacto->id)) {
          $contactos->push($contacto);
        }
      }
    }

    // 3) Buscar en tabla contacto_telefonos (si existe)
    if (!empty($telefonoLead)) {
      try {
        $contactoTelefonos = ContactoTelefono::query()
          ->where('estado', 1)
          ->whereRaw("REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?", ['%' . $telefonoLead . '%'])
          ->get();

        foreach ($contactoTelefonos as $ct) {
          $contacto = Contacto::query()
            ->where('id', $ct->contacto_id)
            ->whereNull('deleted_at')
            ->first();
          
          if ($contacto && !$contactos->contains('id', $contacto->id)) {
            $contactos->push($contacto);
          }
        }
      } catch (\Exception $e) {
        // Si la tabla no existe, ignorar
      }
    }

    return $contactos;
  }

  /**
   * Verifica si un contacto realmente coincide con los datos del lead
   * ✅ NUEVO: Validación estricta de coincidencia
   */
  private function contactoCoincideConLead($contacto, $lead)
  {
    $emailLead = trim($lead->email ?? '');
    $telefonoLead = $this->normalizarTelefono($lead->telefono);

    // Si el lead no tiene ni email ni teléfono, no puede haber coincidencia
    if (empty($emailLead) && empty($telefonoLead)) {
      return false;
    }

    // Verificar coincidencia por email
    if (!empty($emailLead)) {
      // Email directo en contacto
      if (strtolower(trim($contacto->email ?? '')) === strtolower($emailLead)) {
        return true;
      }

      // Email en tabla contacto_emails
      $emailEncontrado = ContactoEmail::query()
        ->where('contacto_id', $contacto->id)
        ->where('email', $emailLead)
        ->where('estado', 1)
        ->exists();

      if ($emailEncontrado) {
        return true;
      }
    }

    // Verificar coincidencia por teléfono
    if (!empty($telefonoLead)) {
      $telefonoContacto = $this->normalizarTelefono($contacto->telefono);
      $celularContacto = $this->normalizarTelefono($contacto->celular ?? '');

      // Teléfono o celular directo en contacto
      if (
        (!empty($telefonoContacto) && strpos($telefonoContacto, $telefonoLead) !== false) ||
        (!empty($celularContacto) && strpos($celularContacto, $telefonoLead) !== false)
      ) {
        return true;
      }

      // Teléfono en tabla contacto_telefonos
      try {
        $telefonoEncontrado = ContactoTelefono::query()
          ->where('contacto_id', $contacto->id)
          ->where('estado', 1)
          ->whereRaw("REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?", ['%' . $telefonoLead . '%'])
          ->exists();

        if ($telefonoEncontrado) {
          return true;
        }
      } catch (\Exception $e) {
        // Si la tabla no existe, ignorar
      }
    }

    return false;
  }

  public function review($id)
  {
    // Modelo Eloquent (ajusta el namespace si difiere)
    $lead = Lead::query()->find($id);
    if (!$lead) {
      return response()->json([
        'success'  => false,
        'message' => 'Lead no encontrado'
      ], 404);
    }

    // Siempre buscar empresa por RUC actual del lead (no usar empresa_detectada_id cacheado)
    $empresa = null;
    if (!$this->esRucGenerico($lead->ruc)) {
      $empresa = Empresa::query()
        ->where('ruc', $lead->ruc)
        ->whereNull('deleted_at')
        ->first();
    }

    // 2) Contactos por email / telefono
    $contactos = collect();
    $contacto = null;

    // Siempre buscar en tiempo real por email/teléfono actuales del lead,
    // ignorando contacto_detectado_id cacheado que puede ser de una revisión antigua.
    $contactosPotenciales = $this->buscarContactosCoincidentes($lead);

    foreach ($contactosPotenciales as $c) {
      if ($this->contactoCoincideConLead($c, $lead)) {
        $contactos->push($c);
      }
    }

    $contacto = $contactos->count() > 0 ? $contactos->first() : null;

    // 3) Conflicto: empresa detectada distinta a empresa_id? (si hay empresa_id asignado)
    $conflicto = false;
    if ($lead->empresa_id && $empresa) {
      if ((int)$lead->empresa_id !== (int)$empresa->id) {
        $conflicto = true;
      }
    }

    // 4) Semáforo: verde (todo ok), naranja (empresa ok, falta contacto), amarillo (conflicto), rojo (nada)
    $estado_semaforo = 'rojo';
    if ($empresa && $contacto && !$conflicto) {
      $estado_semaforo = 'verde';
    } elseif ($empresa && !$contacto) {
      $estado_semaforo = 'naranja';
    } elseif ($conflicto) {
      $estado_semaforo = 'amarillo';
    }

    // Estado compatible con lo que ya tenías
    $estado_revision = 'pendiente';
    if ($empresa && $contacto && !$conflicto) {
      $estado_revision = 'coincidencia_total';
    } elseif ($conflicto) {
      $estado_revision = 'conflicto';
    }

    // 5) Guardar detecciones en lead (no marca procesado)
    $lead->empresa_detectada_id  = $empresa->id   ?? null;
    $lead->contacto_detectado_id = $contacto->id  ?? null;
    $lead->conflicto_detectado   = $conflicto;
    $lead->estado_revision       = $estado_revision;
    $lead->save();

    // 6) Sugerencias (compatible PHP 7)
    switch ($estado_semaforo) {
      case 'verde':
        $sugerencias = ['convertir'];
        break;
      case 'naranja':
        $sugerencias = ['crear_contacto_y_convertir', 'solo_convertir'];
        break;
      case 'amarillo':
        $sugerencias = ['revisar_conflicto', 'crear_contacto_y_convertir'];
        break;
      default:
        $sugerencias = ['crear_empresa', 'crear_contacto', 'revisar_y_convertir'];
        break;
    }

    // 7) Respuesta: incluye claves nuevas y las que ya usabas para no romper integraciones previas
    return response()->json([
      'data' => [
        'ok' => true,
        'lead' => $lead->fresh(),
        'empresa_detectada' => $empresa,
        'contacto_detectado' => $contacto,
        'contactos_coincidentes' => $contactos->values()->all(),   // ✅ Convertir a array
        'conflicto' => $conflicto,
        'conflicto_detectado' => $conflicto,
        'estado_revision' => Lead::ESTADOS_REVISION_LEAD[$estado_revision] ?? ucfirst($estado_revision),
        'estado_semaforo' => $estado_semaforo,
        'sugerencias' => $sugerencias,
        // ✅ NUEVO: Info de debug para entender la coincidencia
        'debug_busqueda' => [
          'email_lead' => $lead->email,
          'telefono_lead' => $lead->telefono,
          'contactos_encontrados' => $contactos->count(),
        ],
      ],
    ]);
  }

  public function storeEmpresa(Request $request)
  {
    $data = $request->validate([
      'id_lead' => 'required|integer',
      'ruc'                     => 'required|digits:11',
      'razon_social'            => 'required|string|max:191',
      'nombre_comercial'        => 'nullable|string|max:191',
      'direccion'               => 'nullable|string|max:255',
    ]);

    $empresa = new Empresa();
    $empresa->fill($data);
    $empresa->created_at = now();
    $empresa->updated_at = now();

    if ($empresa->save() ) {
      $lead = Lead::query()->find($data['id_lead']);
      $lead->empresa_id = $empresa->id;
      $lead->empresa_detectada_id = $empresa->id;
      $lead->ruc = $empresa->ruc;
      $lead->razon_social = $empresa->razon_social;
      $lead->updated_at = now();
      $lead->save();

      // Espejo Empresa->Cliente: las empresas nacidas de un lead deben crear su
      // cliente en `persons` (type=customers), igual que al guardar desde
      // Comercial->Empresas. Sin esto quedan sin cliente y la "Conversion a
      // Cotizacion Formal" falla (no encuentra el customer por RUC). Se captura
      // el error para no interrumpir la creacion de la empresa (queda en el log).
      try {
        app(SyncEmpresaToPersons::class)->syncOne($empresa);
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Lead storeEmpresa: sync empresa->persons fallo', [
          'empresa_id' => $empresa->id,
          'ruc'        => $empresa->ruc,
          'error'      => $e->getMessage(),
        ]);
      }
    }


    return response()->json([
      'success' => true,
      'message' => 'Empresa creada correctamente',
      'data' => ['id' => $empresa->id]
    ]);

  }

  public function storeContacto(Request $request)
  {
    $data = $request->validate([
      'id_lead' => 'required|integer',
      'nombres'    => ['required','string','max:191'],
      'apellidos'  => ['nullable','string','max:191'],
      'cargo'      => ['nullable','string','max:191'],
      'telefono'   => ['nullable','string','max:50'],
      'email'      => ['nullable','email','max:191'],
    ]);

    $data['empresa_id'] = $request->input('empresa_id');
    $data['created_at'] = now();
    $data['updated_at'] = now();

    $lead = Lead::query()->find($data['id_lead']);

    $data['origen'] = ucfirst($lead->origen);
    $contacto = Contacto::query()->create($data);

    $lead->contacto_detectado_id = $contacto->id;
    $lead->contacto = $data['nombres'] . ' ' . $data['apellidos'];
    $lead->telefono = $data['telefono'];
    $lead->email = $data['email'];
    $lead->updated_at = now();
    $lead->save();

    return response()->json([
      'success' => true,
      'message' => 'Contacto creado correctamente',
      'data' => ['id' => $contacto->id]
    ]);

  }


  public function convertToRequirement($id, Request $request)
  {
      // 1) Traer el lead
      $lead = Lead::query()->where('id', $id)->whereNull('deleted_at')->first();
      if (!$lead) {
        return response()->json([
          'success'  => false,
          'message' => 'Lead no encontrado'
        ], 404);
      }

      // 2) Empresa: usar empresa_id del request (ya validado en el frontend)
      //    Si no viene, intentar por RUC como fallback
      $empresa = null;
      $empresaIdRequest = $request->input('empresa_id');

      if ($empresaIdRequest) {
        $empresa = Empresa::query()->where('id', $empresaIdRequest)->whereNull('deleted_at')->first();
      }

      if (!$empresa && !$this->esRucGenerico($lead->ruc)) {
        $empresa = Empresa::query()->where('ruc', $lead->ruc)->whereNull('deleted_at')->first();
      }

      if (!$empresa) {
        return response()->json([
          'success'  => false,
          'message' => 'No se encontró la empresa. Por favor, primero revise el lead.'
        ], 422);
      }

      // 3) Contacto: usar contacto_id del request (ya validado en el frontend)
      //    Si no viene, intentar buscar por email/teléfono como fallback
      $contacto = null;
      $contactoIdRequest = $request->input('contacto_id');

      if ($contactoIdRequest) {
        $contacto = Contacto::query()->where('id', $contactoIdRequest)->whereNull('deleted_at')->first();
      }

      if (!$contacto && ($lead->email || $lead->telefono)) {
        $contactosPotenciales = $this->buscarContactosCoincidentes($lead);
        foreach ($contactosPotenciales as $c) {
          if ((int)$c->empresa_id === (int)$empresa->id && $this->contactoCoincideConLead($c, $lead)) {
            $contacto = $c;
            break;
          }
        }
      }

      // 4) Crear requerimiento y marcar lead como procesado
      try {
        DB::beginTransaction();

        $titulo = $lead->asunto_email ?: ('Requerimiento Lead #'.$lead->id);
        $reqId = Requerimiento::query()->insertGetId([
          'empresa_id'      => $empresa->id,
          'contacto_id'     => $contacto ? $contacto->id : null,
          'lead_id'         => $lead->id,
          'titulo'          => $titulo,
          'descripcion'     => $lead->descripcion,
          'monto_estimado'  => $lead->monto_estimado,
          'fecha_requerida' => $lead->fecha_estimada,
          'etapa'           => $lead->etapa ?: 'nuevo',
          'estado'          => 'abierto',
          'created_at'      => now(),
          'updated_at'      => now(),
        ]);

        // Actualizar lead: marcar como convertido con empresa y contacto usados
        Lead::query()->where('id', $lead->id)->update([
          'empresa_id'            => $empresa->id,
          'empresa_detectada_id'  => $empresa->id,
          'contacto_detectado_id' => $contacto ? $contacto->id : null,
          'conflicto_detectado'   => 0,
          'estado_revision'       => 'convertido',
          'procesado'             => 1,
          'updated_at'            => now(),
        ]);

        DB::commit();

        return response()->json([
          'success' => true,
          'message' => 'Lead convertido a Requerimiento correctamente',
          'data' => ['id' => $reqId]
        ]);

      } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
          'success'  => false,
          'message' => 'No se pudo convertir a Requerimiento. Error: '.$e->getMessage().'.'
        ], 500);
      }
  }

}
