// Estado del bloque Pre-cotización / Preview / Cotización final. Sale siempre de la
// pre-cotización, para que las tres pestañas digan lo mismo.
const ETIQUETAS = {
  borrador: 'Borrador',
  en_revision: 'En revisión',
  ajustada: 'Ajustada',
  validada: 'Validada',
  propuesta_emitida: 'Emitida',
  aprobada: 'Aprobada',
  aprobado: 'Aprobada',
  rechazada: 'Rechazada',
  convertida_en_proyecto: 'Convertida en proyecto',
}

// Tipo de el-tag de Element UI.
const TIPOS = {
  borrador: 'info',
  en_revision: 'warning',
  ajustada: 'warning',
  validada: 'primary',
  propuesta_emitida: 'primary',
  aprobada: 'success',
  aprobado: 'success',
  rechazada: 'danger',
  convertida_en_proyecto: 'success',
}

export function etiquetaEstadoPreCotizacion(prequote) {
  if (!prequote) return ''
  if (prequote.quotation_id) return 'Convertida'
  var estado = String(prequote.estado || '')
  if (ETIQUETAS[estado]) return ETIQUETAS[estado]
  var texto = estado.replace(/_/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

export function tipoEstadoPreCotizacion(prequote) {
  if (!prequote) return 'info'
  if (prequote.quotation_id) return 'success'
  return TIPOS[prequote.estado] || 'info'
}
