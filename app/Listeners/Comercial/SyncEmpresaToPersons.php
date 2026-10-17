<?php

namespace App\Listeners\Comercial;

use App\Events\Comercial\EmpresaSyncRequested;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use App\Models\Tenant\Person;
use Illuminate\Support\Facades\Log;
use Modules\Comercial\Models\Empresa;

class SyncEmpresaToPersons
{
    /**
     * Handle the event.
     *
     * @param EmpresaSyncRequested $event
     * @return void
     */
    public function handle(EmpresaSyncRequested $event)
    {
        try {
            if ($event->empresa) {
                // Sync a specific empresa
                $this->syncEmpresa($event->empresa, $event->force);
            } else {
                // Bulk sync all empresas
                $this->syncAllEmpresas($event->force);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing Empresa to Persons: ' . $e->getMessage(), [
                'exception' => $e,
                'empresa_id' => $event->empresa ? $event->empresa->id : null,
            ]);
        }
    }

    /**
     * Sincroniza una sola empresa SIN atrapar la excepcion, para que quien la
     * invoque pueda conocer el detalle del error (uso directo / diagnostico).
     *
     * @param Empresa $empresa
     * @param bool $force
     * @return Person|null
     */
    public function syncOne(Empresa $empresa, bool $force = false)
    {
        return $this->syncEmpresa($empresa, $force);
    }

    /**
     * Sync a single empresa to persons table.
     *
     * @param Empresa $empresa
     * @param bool $force
     * @return Person|null
     */
    protected function syncEmpresa(Empresa $empresa, bool $force = false)
    {
        // Check if person already exists by RUC (number)
        $person = Person::query()->where('number', $empresa->ruc)
            ->where('type', 'customers')
            ->first();

        if ($person && !$force) {
            // Person already exists and we're not forcing update
            Log::info("Person already exists for Empresa RUC: {$empresa->ruc}", [
                'empresa_id' => $empresa->id,
                'person_id' => $person->id,
            ]);
            return $person;
        }

        $ubicacion = $this->ubicacionValida($empresa);

        // Prepare data for Person
        $personData = [
            'type' => 'customers',
            'identity_document_type_id' => 6, // RUC (assuming ID 6 is RUC)
            'number' => $empresa->ruc,
            'name' => $empresa->razon_social,
            'trade_name' => $empresa->nombre_comercial,
            'email' => $empresa->email,
            'telephone' => $empresa->telefono ?? $empresa->celular,
            'address' => $empresa->direccion,
            'department_id' => $ubicacion['department_id'],
            'province_id' => $ubicacion['province_id'],
            'district_id' => $ubicacion['district_id'],
            'website' => $empresa->website,
            'state' => $empresa->estado_contribuyente,
            'condition' => $empresa->condicion_contribuyente,
            // Si la empresa no trae estado (p. ej. alta desde SUNAT), se asume activo (1).
            // persons.status es NOT NULL, por lo que nunca debe quedar en null.
            'enabled' => ($empresa->estado ?? 1) == 1,
            'status' => $empresa->estado ?? 1,
            'country_id' => 'PE',
        ];

        if ($person) {
            // Update existing person
            $personData['updated_at'] = now();
            $person->update($personData);
            Log::info("Updated Person from Empresa", [
                'empresa_id' => $empresa->id,
                'person_id' => $person->id,
                'ruc' => $empresa->ruc,
            ]);
        } else {
            $personData['created_at'] = now();
            // Create new person
            $person = Person::create($personData);
            Log::info("Created Person from Empresa", [
                'empresa_id' => $empresa->id,
                'person_id' => $person->id,
                'ruc' => $empresa->ruc,
            ]);
        }

        return $person;
    }

    /**
     * Ubicación de la empresa solo si los tres códigos existen en el catálogo; si no (p. ej. "-"
     * de SUNAT), el cliente se crea sin ubicación en lugar de fallar por la llave foránea.
     */
    protected function ubicacionValida(Empresa $empresa)
    {
        $valida = $empresa->department_id && $empresa->province_id && $empresa->district_id
            && Department::query()->whereKey($empresa->department_id)->exists()
            && Province::query()->whereKey($empresa->province_id)->exists()
            && District::query()->whereKey($empresa->district_id)->exists();

        return [
            'department_id' => $valida ? $empresa->department_id : null,
            'province_id'   => $valida ? $empresa->province_id : null,
            'district_id'   => $valida ? $empresa->district_id : null,
        ];
    }

    /**
     * Sync all empresas to persons table.
     *
     * @param bool $force
     * @return array Statistics about the sync
     */
    protected function syncAllEmpresas(bool $force = false)
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $empresas = Empresa::whereNull('deleted_at')
            ->where('estado', 1)
            ->get();

        $stats['total'] = $empresas->count();

        foreach ($empresas as $empresa) {
            try {
                // Check if person already exists
                $person = Person::where('number', $empresa->ruc)
                    ->where('type', 'customers')
                    ->first();

                if ($person && !$force) {
                    $stats['skipped']++;
                    continue;
                }

                $result = $this->syncEmpresa($empresa, $force);

                if ($result) {
                    if ($result->wasRecentlyCreated) {
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error("Error syncing Empresa ID {$empresa->id}: " . $e->getMessage());
            }
        }

        Log::info("Bulk Empresa sync completed", $stats);

        return $stats;
    }
}
