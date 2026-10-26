// Plazos en días útiles: se escribe solo el número o el rango ("8", "10 a 12", "10-12")
// y se guarda el texto completo ("8 días útiles", "10 a 12 días útiles", "1 día útil"),
// que es lo que leen el Preview y el PDF.
const RANGO = /^(\d{1,3})(?:\s*(?:a|al|-|–)\s*(\d{1,3}))?$/i
const UNIDAD = /\s*d[ií]as?(?:\s+[úu]til(?:es)?)?\.?\s*$/i

// Texto guardado → lo que se muestra en la casilla ("10 a 12 días útiles" → "10 a 12").
export function valorDiasUtiles(texto) {
  return String(texto || '').trim().replace(UNIDAD, '').trim()
}

// Lo escrito en la casilla → { ok, texto } con el texto completo a guardar.
export function normalizarDiasUtiles(valor) {
  const v = String(valor || '').trim()
  if (!v) return { ok: true, texto: '' }
  const m = v.replace(UNIDAD, '').trim().match(RANGO)
  if (!m) return { ok: false, texto: v }
  const desde = Number(m[1])
  const hasta = m[2] !== undefined ? Number(m[2]) : null
  if (hasta === null || hasta === desde) {
    return { ok: true, texto: desde === 1 ? '1 día útil' : desde + ' días útiles' }
  }
  const [a, b] = desde < hasta ? [desde, hasta] : [hasta, desde]
  return { ok: true, texto: a + ' a ' + b + ' días útiles' }
}

// ¿El texto guardado es un plazo válido (o está vacío)?
export function esDiasUtilesValido(texto) {
  return normalizarDiasUtiles(valorDiasUtiles(texto)).ok
}
