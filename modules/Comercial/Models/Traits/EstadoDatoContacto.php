<?php

namespace Modules\Comercial\Models\Traits;

/**
 * Estado de un teléfono o correo del contacto (ContactoTelefono, ContactoEmail).
 */
trait EstadoDatoContacto
{
    /**
     * Único cambio de estado del dato:
     *  - 'activo':     estado 1 sin fecha (también al reactivar un dato que la persona ya tuvo).
     *  - 'suspendido': estado 0 sin fecha (contacto inactivo; se recupera al activarlo).
     *  - 'baja':       estado 0 con fecha (se quitó o su contacto se eliminó).
     */
    public function cambiarEstado($estado)
    {
        if (!in_array($estado, ['activo', 'suspendido', 'baja'], true)) {
            throw new \InvalidArgumentException("Estado no válido: {$estado}");
        }

        $this->estado = $estado === 'activo' ? 1 : 0;
        $this->deleted_at = $estado === 'baja' ? now() : null;

        return $this->save();
    }
}
