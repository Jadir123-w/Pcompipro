// Recorte de textos largos en tablas: se muestra hasta LIMITE_TEXTO caracteres con "…"
// y el texto completo va en un tooltip.
export const LIMITE_TEXTO = 30

export function esLargo(valor, maximo = LIMITE_TEXTO) {
  return String(valor || '').length > maximo
}

export function recortar(valor, maximo = LIMITE_TEXTO) {
  const texto = String(valor || '')
  return texto.length > maximo ? texto.slice(0, maximo).trimEnd() + '…' : texto
}

// Nombre con el que se muestra una empresa: el comercial (así la conocen los usuarios);
// la razón social solo si el nombre comercial está vacío o es "-".
export function nombreEmpresa(empresa) {
  if (!empresa) return ''
  const comercial = String(empresa.nombre_comercial || '').trim()
  return /^[\s-]*$/.test(comercial) ? (empresa.razon_social || '') : comercial
}
