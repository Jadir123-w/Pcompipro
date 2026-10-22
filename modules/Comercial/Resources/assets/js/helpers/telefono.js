// Formato de teléfonos en pantalla. La regla manda en el servidor
// (Modules\Comercial\Support\Telefono), que vuelve a normalizar al guardar.
export const SEPARADOR_ANEXO = ' anexo '

// "014581458 anexo 003" → { numero: '014581458', anexo: '003' }
export function separarAnexo(valor) {
  const texto = String(valor || '')
  const i = texto.indexOf(SEPARADOR_ANEXO)
  return i === -1
    ? { numero: texto, anexo: '' }
    : { numero: texto.slice(0, i), anexo: texto.slice(i + SEPARADOR_ANEXO.length) }
}

function agrupar(digitos, tramos) {
  const partes = []
  let resto = digitos
  tramos.forEach(n => {
    if (resto) {
      partes.push(resto.slice(0, n))
      resto = resto.slice(n)
    }
  })
  if (resto) partes.push(resto)
  return partes.join(' ')
}

// Agrupa los dígitos mientras se escribe:
// 939 446 847 · (01) 458 1458 · (044) 123 456 · 470 6050 · +56912345678
export function formatearTelefono(valor) {
  const texto = String(valor || '').trim()
  if (texto === '') return ''
  if (texto[0] === '+') return '+' + texto.replace(/\D/g, '').slice(0, 15)

  const d = texto.replace(/\D/g, '').slice(0, 15)
  if (d.startsWith('51') && d.length > 7) return d
  if (d[0] === '9') return agrupar(d.slice(0, 9), [3, 3, 3])
  if (d.startsWith('01')) return ('(01) ' + agrupar(d.slice(2, 9), [3, 4])).trim()
  if (d[0] === '0') {
    return d.length <= 3 ? d : '(' + d.slice(0, 3) + ') ' + agrupar(d.slice(3, 9), [3, 3])
  }
  return agrupar(d, [3, 4])
}

export function soloDigitos(valor, maximo = 6) {
  return String(valor || '').replace(/\D/g, '').slice(0, maximo)
}
