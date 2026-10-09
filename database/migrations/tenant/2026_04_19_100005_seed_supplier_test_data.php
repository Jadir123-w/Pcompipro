<?php

use App\Models\Tenant\Person;
use App\Models\Tenant\SupplierContact;
use App\Models\Tenant\SupplierContactEmail;
use App\Models\Tenant\SupplierContactPhone;
use App\Models\Tenant\SupplierCategory;
use App\Models\Tenant\SupplierSubcategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga 8 proveedores de prueba extraídos del SQL propuesto por el cliente
 * (provedores-contacto_provedor.sql), adaptados a la estructura de `persons`
 * y tablas relacionadas.
 *
 * NO carga los 124 registros completos; solo una muestra representativa para
 * pruebas que incluye variedad de:
 *  - Con/sin razón comercial
 *  - Con/sin categoría
 *  - Con política de pago distinta
 *  - Contactos con email/sin email
 */
class SeedSupplierTestData extends Migration
{
    public function up()
    {
        $now = now();

        // Helpers para buscar IDs de maestros
        $catId = function ($nombre) {
            return SupplierCategory::where('nombre', $nombre)->value('id');
        };
        $subId = function ($catNombre, $subNombre) {
            $cid = SupplierCategory::where('nombre', $catNombre)->value('id');
            return $cid ? SupplierSubcategory::where('supplier_category_id', $cid)->where('nombre', $subNombre)->value('id') : null;
        };

        $suppliers = [
            [
                'number' => '20611321245',
                'name' => 'PUBLITRADE IMPORTACIONES E.I.R.L.',
                'trade_name' => 'PUBLITRADE',
                'categoria' => 'IMPORTADOS',
                'subcategoria' => 'Merchandising',
                'address' => 'Jr. Carabaya Nro. 1146 Lima',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'perfil' => ['comentarios_adicionales' => 'Categoria: Importación Merchandising'],
                'contact' => ['nombres' => 'Eloy', 'celular' => '908834701'],
            ],
            [
                'number' => '20553286175',
                'name' => 'MULTISERVICIOS SCOLY E.I.R.L',
                'trade_name' => null,
                'categoria' => null,
                'subcategoria' => null,
                'address' => 'Jr.Carabaya 783 (Ref. Plaza San Martin)',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => 'Cta ahorros BCP 19196900843037',
                'contact' => ['nombres' => 'Vannessa', 'celular' => '910207701', 'email' => 'vcarabaya@gmail.com'],
            ],
            [
                'number' => '20519014280',
                'name' => 'HERVAS SAC',
                'trade_name' => 'HERVAS',
                'categoria' => 'COURIER',
                'subcategoria' => null,
                'address' => 'Av Perez Salmon Mz I Lote 49 - 50 -51 Callao',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => "Cta Cte BCP Soles 192-1745104-0-12\nTitular: HERVAS SAC",
                'contact' => ['nombres' => 'Juan Carlos', 'apellidos' => 'Sol', 'celular' => '914968596', 'email' => 'jsolsol@hervas.com.pe'],
            ],
            [
                'number' => '20610011374',
                'name' => 'GLOBAL PUFFS E.I.R.L.',
                'trade_name' => null,
                'categoria' => null,
                'subcategoria' => null,
                'address' => 'Inca 1113, Surquillo 15047',
                'condicion_pago' => '50% Adelanto y 50% a Conformidad del Producto',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => "BCP Soles es 1919900389027.\nCCI: 00219100990038902756.",
                'contact' => ['nombres' => 'Eduardo', 'celular' => '924459734', 'email' => 'ventas@mundopuffperu.com'],
            ],
            [
                'number' => '10805756166',
                'name' => 'MDY INDUSTRIAL - DIANA LIZ EUFRACIO QUISPE',
                'trade_name' => null,
                'categoria' => 'TEXTIL',
                'subcategoria' => null,
                'address' => 'Mz. O Lote 8, Don Jose De San Martin - Los Olivos',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => "BCP cuenta Soles: 193-95198973-0-76\nTitular: Diana Liz Eufracio Quispe",
                'contact' => ['nombres' => 'Joel', 'celular' => '927525415', 'email' => 'ventas@mdyindustrial.com'],
            ],
            [
                'number' => '20547284501',
                'name' => 'CERAMICA PUBLICITARIA SAC',
                'trade_name' => null,
                'categoria' => 'CERAMICA',
                'subcategoria' => 'Tazas',
                'address' => 'Jr Manuel Cuadros 418 Int 5',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'contact' => ['nombres' => 'Juan', 'celular' => '947047625', 'email' => 'ceramicapublicitaria@gmail.com'],
            ],
            [
                'number' => '20603738234',
                'name' => 'GLOBAL COPY SOLUCIONES EIRL',
                'trade_name' => 'LASER COLOR',
                'categoria' => 'IMPRENTA',
                'subcategoria' => 'Laser',
                'address' => 'Av. Argentina 144 Stand 2005 - 2 Nivel C (Unicentro)',
                'condicion_pago' => '',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => 'Cta Soles BCP: 191-2536428-0-83 TITULAR: GLOBAL COPY SOLUCIONES EIRL',
                'contact' => ['nombres' => 'Albert', 'celular' => '922202376'],
            ],
            [
                'number' => '10101252197',
                'name' => 'JUAN JOSE RODRIGUEZ INGA',
                'trade_name' => null,
                'categoria' => null,
                'subcategoria' => null,
                'address' => 'Jr. Jose Antonio Encinas 249 Urb. Las Flores',
                'condicion_pago' => '70% de adelanto y saldo contra entrega',
                'tipo_provee' => 'nuevo',
                'calificacion' => 'nacional',
                'numero_cuenta' => '191-22676296-0-47 BCP A nombre de Alejandro Ruiz Pastor',
                'contact' => ['nombres' => 'Juan Jose', 'apellidos' => 'Rodriguez Inga', 'celular' => '928352980', 'email' => 'jjwoodsart@yahoo.com'],
            ],
        ];

        foreach ($suppliers as $data) {
            // Evitar duplicados si ya existe por RUC
            $existing = Person::where('type', 'suppliers')->where('number', $data['number'])->first();
            if ($existing) continue;

            $person = new Person();
            $person->type = 'suppliers';
            $person->identity_document_type_id = '6'; // RUC
            $person->number = $data['number'];
            $person->name = $data['name'];
            $person->trade_name = $data['trade_name'];
            $person->country_id = 'PE';
            $person->nationality_id = 'PE';
            $person->address = $data['address'] ?? null;
            $person->condition = 'HABIDO';
            $person->state = 'ACTIVO';
            $person->enabled = 1;
            $person->status = 1;

            // Campos nuevos
            $person->tipo_provee = $data['tipo_provee'] ?? null;
            $person->calificacion = $data['calificacion'] ?? null;
            $person->supplier_category_id = $data['categoria'] ? $catId($data['categoria']) : null;
            $person->supplier_subcategory_id = ($data['categoria'] && $data['subcategoria'])
                ? $subId($data['categoria'], $data['subcategoria']) : null;
            $person->condicion_pago = $data['condicion_pago'] ?? null;
            $person->numero_cuenta = $data['numero_cuenta'] ?? null;
            $person->perfil_adicional = $data['perfil'] ?? null;

            $person->save();

            // Contacto asociado
            if (!empty($data['contact'])) {
                $c = $data['contact'];
                $contact = SupplierContact::create([
                    'person_id' => $person->id,
                    'nombres' => $c['nombres'],
                    'apellidos' => $c['apellidos'] ?? null,
                    'estado' => 1,
                ]);
                if (!empty($c['celular'])) {
                    SupplierContactPhone::create([
                        'supplier_contact_id' => $contact->id,
                        'telefono' => $c['celular'],
                        'tipo' => 'trabajo',
                        'es_principal' => 1,
                        'estado' => 1,
                    ]);
                }
                if (!empty($c['email'])) {
                    SupplierContactEmail::create([
                        'supplier_contact_id' => $contact->id,
                        'email' => $c['email'],
                        'tipo' => 'trabajo',
                        'es_principal' => 1,
                        'estado' => 1,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $rucs = [
            '20611321245', '20553286175', '20519014280', '20610011374',
            '10805756166', '20547284501', '20603738234', '10101252197',
        ];

        // Limpiar contactos hijos primero (cascadas FK deberían manejar, pero explícito)
        $personIds = Person::where('type', 'suppliers')->whereIn('number', $rucs)->pluck('id');
        SupplierContact::whereIn('person_id', $personIds)->get()->each(function ($c) {
            $c->phones()->delete();
            $c->emails()->delete();
            $c->forceDelete();
        });

        Person::where('type', 'suppliers')->whereIn('number', $rucs)->forceDelete();
    }
}
