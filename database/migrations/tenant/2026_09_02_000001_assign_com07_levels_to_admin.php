<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * COM-07 — Habilita los sub-permisos en el árbol de Permisos del usuario.
 *
 * Complementa a 2026_09_01_000001, que creó los niveles pero no los asignó a
 * nadie.
 *
 * El formulario de Usuarios arma el árbol de permisos con los niveles que
 * tiene asignados el usuario administrador principal (UserController@tables
 * consulta User::find(1)): un nivel que ese usuario no posee no se ofrece como
 * opción para ningún otro. Por eso "ChatBot" figura bajo Marketing —el
 * administrador lo tiene— y los niveles del COM-07 no aparecían.
 *
 * Se asignan al administrador principal y a los usuarios de tipo admin que ya
 * cuenten con el módulo Comercial. Al resto NO se les asigna nada: quién ve
 * cada pantalla lo decide Compipro desde la ficha de cada usuario, que es
 * justamente el control solicitado.
 */
class AssignCom07LevelsToAdmin extends Migration
{
    private $valores = ['com07_oportunidades', 'com07_supervision'];

    public function up()
    {
        $conexion = DB::connection('tenant');

        $moduloId = $conexion->table('modules')->where('value', 'comercial')->value('id');
        if (!$moduloId) {
            return;
        }

        $niveles = $conexion->table('module_levels')
            ->where('module_id', $moduloId)
            ->whereIn('value', $this->valores)
            ->pluck('id')
            ->all();

        if (empty($niveles)) {
            return;   // la migración anterior no llegó a crearlos
        }

        // El administrador principal siempre: es de quien se arma el catálogo.
        $usuarios = [1];

        // Y los demás administradores que ya tengan el módulo Comercial.
        $admins = $conexion->table('users as u')
            ->join('module_user as mu', 'mu.user_id', '=', 'u.id')
            ->where('mu.module_id', $moduloId)
            ->where('u.type', 'admin')
            ->pluck('u.id')
            ->all();

        $usuarios = array_values(array_unique(array_merge($usuarios, $admins)));

        foreach ($usuarios as $usuarioId) {
            $existe = $conexion->table('users')->where('id', $usuarioId)->exists();
            if (!$existe) {
                continue;
            }

            foreach ($niveles as $nivelId) {
                $yaAsignado = $conexion->table('module_level_user')
                    ->where('user_id', $usuarioId)
                    ->where('module_level_id', $nivelId)
                    ->exists();

                if (!$yaAsignado) {
                    $conexion->table('module_level_user')->insert([
                        'user_id'         => $usuarioId,
                        'module_level_id' => $nivelId,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $conexion = DB::connection('tenant');

        $moduloId = $conexion->table('modules')->where('value', 'comercial')->value('id');
        if (!$moduloId) {
            return;
        }

        $niveles = $conexion->table('module_levels')
            ->where('module_id', $moduloId)
            ->whereIn('value', $this->valores)
            ->pluck('id');

        if ($niveles->isNotEmpty()) {
            $conexion->table('module_level_user')->whereIn('module_level_id', $niveles)->delete();
        }
    }
}
