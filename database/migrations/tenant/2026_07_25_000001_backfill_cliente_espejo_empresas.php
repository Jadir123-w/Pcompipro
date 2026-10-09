<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reparación de datos: crea el "cliente espejo" (persons type=customers) que
 * falta para las empresas históricas.
 *
 * Problema: al "Convertir a Cotización Formal" (Pre-Cotización), el sistema
 * busca la persona/cliente por RUC:
 *
 *     Person::where('number', $empresa->ruc)->where('type', 'customers')
 *
 * y si no existe corta con 422 "No se encontró un cliente (persona) asociado a
 * la empresa del requerimiento" (QuotationPreQuoteController).
 *
 * Las empresas creadas desde el flujo de LEADS no generaban el espejo. Eso ya
 * se corrigió en el código (LeadsController::storeEmpresa ahora llama a
 * SyncEmpresaToPersons::syncOne), pero las empresas creadas ANTES de ese fix
 * quedaron sin cliente y siguen fallando al convertir.
 *
 * Esta migración crea el cliente faltante replicando el mismo mapeo de campos
 * que usa el listener App\Listeners\Comercial\SyncEmpresaToPersons, de modo que
 * el resultado sea idéntico a volver a guardar la empresa desde el módulo
 * Comercial.
 *
 * ALCANCE — solo empresas CON REQUERIMIENTOS:
 * La tabla `empresa` arrastra miles de registros históricos importados (altas
 * de 2013-2014) que nunca se usaron en el módulo Comercial. Crearles un cliente
 * a todas llenaría el maestro de clientes del ERP con datos que nadie pidió y
 * no habría forma segura de revertirlo. Por eso solo se repara la empresa que
 * tiene al menos un requerimiento: son las únicas que pueden llegar a la
 * pantalla de convertir y toparse con el error.
 *
 * Para el resto (una empresa vieja que algún día se use), el propio convert
 * crea el cliente en el momento — ver el self-heal en QuotationPreQuoteController.
 *
 * Notas:
 *   - Idempotente: solo inserta si no existe ya un persons(type=customers) con
 *     ese número. Correrla dos veces no duplica nada.
 *   - NO filtra por empresa.estado: una empresa inactiva igual puede tener
 *     requerimientos que haya que convertir.
 *   - El número se guarda tal cual está en empresa.ruc (sin TRIM) para que
 *     coincida exactamente con la búsqueda que hace el convert.
 *   - No toca las personas ya existentes (no pisa datos cargados a mano).
 */
class BackfillClienteEspejoEmpresas extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('empresa') ||
            !Schema::connection('tenant')->hasTable('persons') ||
            !Schema::connection('tenant')->hasTable('requerimiento')) {
            return;
        }

        // Empresas vigentes, con RUC, CON al menos un requerimiento, que NO
        // tienen cliente espejo.
        $empresas = DB::connection('tenant')->select(
            'SELECT e.id, e.ruc, e.razon_social, e.nombre_comercial, e.email,
                    e.telefono, e.celular, e.direccion, e.department_id,
                    e.province_id, e.district_id, e.website,
                    e.estado_contribuyente, e.condicion_contribuyente, e.estado
               FROM empresa e
              WHERE e.deleted_at IS NULL
                AND e.ruc IS NOT NULL
                AND TRIM(e.ruc) <> \'\'
                AND EXISTS (
                        SELECT 1 FROM requerimiento r
                         WHERE r.empresa_id = e.id
                    )
                AND NOT EXISTS (
                        SELECT 1 FROM persons p
                         WHERE p.number = e.ruc
                           AND p.type = \'customers\'
                    )'
        );

        if (empty($empresas)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $creados = 0;
        $yaVistos = array(); // evita duplicar si dos empresas comparten el RUC

        foreach ($empresas as $e) {
            if (isset($yaVistos[$e->ruc])) {
                continue;
            }
            $yaVistos[$e->ruc] = true;

            $estado = is_null($e->estado) ? 1 : (int) $e->estado;

            // Mismo mapeo que SyncEmpresaToPersons::syncEmpresa()
            DB::connection('tenant')->table('persons')->insert(array(
                'type'                      => 'customers',
                'identity_document_type_id' => '6', // RUC
                'number'                    => $e->ruc,
                'name'                      => $e->razon_social,
                'trade_name'                => $e->nombre_comercial,
                'email'                     => $e->email,
                'telephone'                 => is_null($e->telefono) ? $e->celular : $e->telefono,
                'address'                   => $e->direccion,
                'department_id'             => $e->department_id,
                'province_id'               => $e->province_id,
                'district_id'               => $e->district_id,
                'website'                   => $e->website,
                'state'                     => $e->estado_contribuyente,
                'condition'                 => $e->condicion_contribuyente,
                'enabled'                   => $estado === 1 ? 1 : 0,
                'status'                    => $estado,
                'country_id'                => 'PE',
                'created_at'                => $now,
                'updated_at'                => $now,
            ));

            $creados++;
        }

        Log::info('Backfill cliente espejo: clientes creados desde empresa', array(
            'creados' => $creados,
        ));
    }

    public function down()
    {
        // Reparación de datos: no se revierte. Borrar las personas creadas sería
        // riesgoso (no hay forma segura de distinguirlas de las cargadas a mano
        // y podrían haber quedado referenciadas por cotizaciones ya emitidas).
    }
}
