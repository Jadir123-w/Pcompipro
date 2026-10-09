<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Tenant\Person;


class TenantAddDataDefaultVariousClientsToPersons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $number = '99999999';
        $name = 'Clientes - Varios';
        $identity_document_type_id = '0';
        $type = 'customers';

        $person = Person::where('number', $number)
                            ->where('type', $type)
                            ->first();

        if(!$person)
        {
            // El modelo Person registra auditoría en la tabla `audits`, que se crea
            // en una migración posterior. Al generar una base nueva, esta migración
            // se ejecuta antes y el registro de auditoría fallaría con
            // "Table 'audits' doesn't exist". Se desactiva solo para esta inserción.
            Person::disableAuditing();

            try {
                Person::create([
                    'number' => $number,
                    'name' => $name,
                    'identity_document_type_id' => $identity_document_type_id,
                    'type' => $type,
                    'country_id' => 'PE',
                ]);
            } finally {
                Person::enableAuditing();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
