// Avisos que el usuario debe leer: ventana con "Entendido". Un mensaje arriba se va
// solo y, si no se vio, se pierde. Los de éxito siguen como mensaje corto.
// tipo: 'warning' (por defecto) o 'error'.
export function avisar(vm, mensaje, tipo) {
  var error = tipo === 'error'
  return vm.$alert(String(mensaje || ''), error ? 'No se pudo completar' : 'Revisa antes de continuar', {
    confirmButtonText: 'Entendido', type: error ? 'error' : 'warning'
  }).catch(function () {})
}
