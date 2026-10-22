<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\SupplierContact;
use App\Models\Tenant\SupplierContactEmail;
use App\Models\Tenant\SupplierContactPhone;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRUD de Contactos de Proveedor.
 *
 * Rutas (declaradas en src/routes/web.php):
 *   GET    /persons/suppliers/{id}/contacts          -> index
 *   POST   /persons/suppliers/{id}/contacts          -> store
 *   GET    /persons/suppliers/{id}/contacts/{cid}    -> show
 *   PUT    /persons/suppliers/{id}/contacts/{cid}    -> update
 *   DELETE /persons/suppliers/{id}/contacts/{cid}    -> destroy
 */
class SupplierContactController extends Controller
{
    /**
     * Lista los contactos de un proveedor.
     */
    public function index($supplierId)
    {
        $supplier = Person::where('type', 'suppliers')->findOrFail($supplierId);

        $contacts = SupplierContact::with(['phones', 'emails'])
            ->where('person_id', $supplier->id)
            ->orderBy('nombres')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombres' => $c->nombres,
                    'apellidos' => $c->apellidos,
                    'nombre_completo' => $c->nombre_completo,
                    'cargo' => $c->cargo,
                    'cumpleanios' => optional($c->cumpleanios)->format('Y-m-d'),
                    'origen' => $c->origen,
                    'estado' => $c->estado,
                    'telefonos' => $c->phones->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'telefono' => $p->telefono,
                            'tipo' => $p->tipo,
                            'es_principal' => (bool) $p->es_principal,
                            'estado' => $p->estado,
                        ];
                    }),
                    'emails' => $c->emails->map(function ($e) {
                        return [
                            'id' => $e->id,
                            'email' => $e->email,
                            'tipo' => $e->tipo,
                            'es_principal' => (bool) $e->es_principal,
                            'estado' => $e->estado,
                        ];
                    }),
                    'created_at' => optional($c->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => optional($c->updated_at)->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['ok' => true, 'data' => $contacts]);
    }

    /**
     * Muestra un contacto en detalle.
     */
    public function show($supplierId, $contactId)
    {
        $contact = SupplierContact::with(['phones', 'emails'])
            ->where('person_id', $supplierId)
            ->findOrFail($contactId);

        return response()->json(['ok' => true, 'data' => $contact]);
    }

    /**
     * Crea un contacto asociado al proveedor.
     */
    public function store($supplierId, Request $request)
    {
        $supplier = Person::where('type', 'suppliers')->findOrFail($supplierId);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:191'],
            'apellidos' => ['nullable', 'string', 'max:191'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'cumpleanios' => ['nullable', 'date'],
            'origen' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'integer', 'in:0,1'],

            'telefonos' => ['nullable', 'array'],
            'telefonos.*.telefono' => ['required_with:telefonos', 'string', 'max:50'],
            'telefonos.*.tipo' => ['nullable', 'string', 'in:trabajo,personal'],
            'telefonos.*.es_principal' => ['nullable', 'boolean'],

            'emails' => ['nullable', 'array'],
            'emails.*.email' => ['required_with:emails', 'email', 'max:191'],
            'emails.*.tipo' => ['nullable', 'string', 'in:trabajo,personal'],
            'emails.*.es_principal' => ['nullable', 'boolean'],
        ]);

        // Exigir al menos un teléfono o email
        if (empty($data['telefonos'] ?? []) && empty($data['emails'] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe agregar al menos un teléfono o un email',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $contact = new SupplierContact();
            $contact->person_id = $supplier->id;
            $contact->nombres = $data['nombres'];
            $contact->apellidos = $data['apellidos'] ?? null;
            $contact->cargo = $data['cargo'] ?? null;
            $contact->cumpleanios = $data['cumpleanios'] ?? null;
            $contact->origen = $data['origen'] ?? null;
            $contact->estado = $data['estado'] ?? 1;
            $contact->user_id = Auth::id();
            $contact->save();

            $this->syncPhones($contact, $data['telefonos'] ?? []);
            $this->syncEmails($contact, $data['emails'] ?? []);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto registrado correctamente',
                'id' => $contact->id,
                'data' => ['id' => $contact->id],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SupplierContactController@store error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el contacto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza un contacto.
     */
    public function update($supplierId, $contactId, Request $request)
    {
        $contact = SupplierContact::where('person_id', $supplierId)->findOrFail($contactId);

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:191'],
            'apellidos' => ['nullable', 'string', 'max:191'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'cumpleanios' => ['nullable', 'date'],
            'origen' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'integer', 'in:0,1'],

            'telefonos' => ['nullable', 'array'],
            'telefonos.*.id' => ['nullable', 'integer'],
            'telefonos.*.telefono' => ['required_with:telefonos', 'string', 'max:50'],
            'telefonos.*.tipo' => ['nullable', 'string', 'in:trabajo,personal'],
            'telefonos.*.es_principal' => ['nullable', 'boolean'],

            'emails' => ['nullable', 'array'],
            'emails.*.id' => ['nullable', 'integer'],
            'emails.*.email' => ['required_with:emails', 'email', 'max:191'],
            'emails.*.tipo' => ['nullable', 'string', 'in:trabajo,personal'],
            'emails.*.es_principal' => ['nullable', 'boolean'],
        ]);

        if (empty($data['telefonos'] ?? []) && empty($data['emails'] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe agregar al menos un teléfono o un email',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $contact->fill([
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'] ?? null,
                'cargo' => $data['cargo'] ?? null,
                'cumpleanios' => $data['cumpleanios'] ?? null,
                'origen' => $data['origen'] ?? null,
                'estado' => $data['estado'] ?? 1,
                'user_id' => Auth::id(),
            ])->save();

            $this->syncPhones($contact, $data['telefonos'] ?? []);
            $this->syncEmails($contact, $data['emails'] ?? []);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contacto actualizado correctamente',
                'id' => $contact->id,
                'data' => ['id' => $contact->id],
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('SupplierContactController@update error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el contacto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devuelve los audits (bitácora) de un proveedor y sus contactos.
     * Usa owen-it/laravel-auditing sobre la tabla `audits`.
     *
     * GET /persons/suppliers/{id}/audits
     */
    public function audits($supplierId)
    {
        $supplier = Person::where('type', 'suppliers')->findOrFail($supplierId);

        $contactIds = SupplierContact::where('person_id', $supplier->id)->pluck('id')->all();

        // La auditoría se guarda en la BD de la empresa (config/audit.php → conexión tenant).
        $audits = DB::connection('tenant')->table('audits')
            ->leftJoin('users', 'users.id', '=', 'audits.user_id')
            ->where(function ($q) use ($supplier, $contactIds) {
                $q->where(function ($q2) use ($supplier) {
                    $q2->where('auditable_type', \App\Models\Tenant\Person::class)
                       ->where('auditable_id', $supplier->id);
                });
                if (!empty($contactIds)) {
                    $q->orWhere(function ($q2) use ($contactIds) {
                        $q2->where('auditable_type', \App\Models\Tenant\SupplierContact::class)
                           ->whereIn('auditable_id', $contactIds);
                    });
                }
            })
            ->orderBy('audits.created_at', 'desc')
            ->limit(100)
            ->get([
                'audits.id',
                'audits.auditable_type',
                'audits.auditable_id',
                'audits.event',
                'audits.old_values',
                'audits.new_values',
                'audits.ip_address',
                'audits.created_at',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->map(function ($a) use ($supplier) {
                $old = json_decode($a->old_values, true) ?: [];
                $new = json_decode($a->new_values, true) ?: [];
                $changes = [];
                foreach ($new as $k => $v) {
                    $changes[] = [
                        'field' => $k,
                        'old' => $old[$k] ?? null,
                        'new' => $v,
                    ];
                }
                return [
                    'id' => $a->id,
                    'event' => $a->event,
                    'auditable_type' => $a->auditable_type,
                    'auditable_id' => $a->auditable_id,
                    'origen' => $a->auditable_type === \App\Models\Tenant\SupplierContact::class ? 'Contacto' : null,
                    'user' => $a->user_name ? ['name' => $a->user_name, 'email' => $a->user_email] : null,
                    'ip_address' => $a->ip_address,
                    'created_at' => $a->created_at,
                    'changes' => $changes,
                ];
            });

        return response()->json(['ok' => true, 'data' => $audits]);
    }

    /**
     * Elimina (soft delete) un contacto y sus teléfonos/emails.
     */
    public function destroy($supplierId, $contactId)
    {
        $contact = SupplierContact::where('person_id', $supplierId)->findOrFail($contactId);
        $contact->phones()->delete();
        $contact->emails()->delete();
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contacto eliminado',
        ]);
    }

    /**
     * Sincroniza teléfonos del contacto (reemplaza todos).
     */
    private function syncPhones(SupplierContact $contact, array $phones)
    {
        $contact->phones()->delete();
        foreach ($phones as $p) {
            if (empty($p['telefono'])) continue;
            SupplierContactPhone::create([
                'supplier_contact_id' => $contact->id,
                'telefono' => $p['telefono'],
                'tipo' => $p['tipo'] ?? 'trabajo',
                'es_principal' => !empty($p['es_principal']) ? 1 : 0,
                'estado' => 1,
            ]);
        }
    }

    /**
     * Sincroniza emails del contacto (reemplaza todos).
     */
    private function syncEmails(SupplierContact $contact, array $emails)
    {
        $contact->emails()->delete();
        foreach ($emails as $e) {
            if (empty($e['email'])) continue;
            SupplierContactEmail::create([
                'supplier_contact_id' => $contact->id,
                'email' => $e['email'],
                'tipo' => $e['tipo'] ?? 'trabajo',
                'es_principal' => !empty($e['es_principal']) ? 1 : 0,
                'estado' => 1,
            ]);
        }
    }
}
