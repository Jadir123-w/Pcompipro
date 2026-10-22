// Acciones sobre un contacto compartidas por las pantallas de Contactos y Empresas.
const RECURSO = '/comercial/contactos/record/'

// Ejecuta la acción y avisa el resultado; alTerminar se llama solo si salió bien.
export function ejecutarAccionContacto(vm, metodo, url, verbo, alTerminar) {
  return vm.$http[metodo](url)
    .then(res => {
      const tipo = res.data.success ? (res.data.message.indexOf('No se recuperaron') !== -1 ? 'warning' : 'success') : 'error'
      vm.$message({ type: tipo, message: res.data.message, duration: tipo === 'success' ? 3000 : 8000, showClose: true })
      if (res.data.success && alTerminar) alTerminar()
    })
    .catch(() => vm.$message.error(`No se pudo ${verbo} el contacto`))
}

// Eliminar: primero revisa si tiene historial. Si lo tiene no se puede eliminar y se
// ofrece inactivarlo desde la misma ventana; si no, pide la confirmación normal.
export function eliminarContacto(vm, id, alTerminar) {
  vm.$http.get(RECURSO + id + '/historial')
    .then(res => {
      const datos = res.data
      if (!datos.tiene_historial) {
        vm.$confirm(
          'Eliminar es solo para registros duplicados o mal hechos. Si el contacto dejó la empresa o cambió de trabajo, usa Inactivar. ¿Eliminar el contacto?',
          'Eliminar',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        ).then(() => ejecutarAccionContacto(vm, 'delete', RECURSO + id, 'eliminar', alTerminar)).catch(() => {})
        return
      }

      const h = vm.$createElement
      const titulo = 'Este contacto no se puede eliminar'
      if (!datos.activo) {
        vm.$alert(h('p', [h('strong', datos.nombre), ' tiene historial registrado y ya está inactivo.']), titulo, {
          confirmButtonText: 'Entendido', type: 'warning'
        }).catch(() => {})
        return
      }
      vm.$confirm(
        h('div', [
          h('p', [h('strong', datos.nombre), ' tiene historial registrado.']),
          h('p', { style: { marginTop: '8px' } }, [
            'Si la persona dejó la empresa o ya no es el contacto, usa ', h('strong', 'Inactivar'),
            ': dejará de aparecer como activo y su historial se conserva.'
          ])
        ]),
        titulo,
        { confirmButtonText: 'Inactivar contacto', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(() => ejecutarAccionContacto(vm, 'post', RECURSO + id + '/inactivar', 'inactivar', alTerminar)).catch(() => {})
    })
    .catch(() => vm.$message.error('No se pudo revisar el contacto'))
}
