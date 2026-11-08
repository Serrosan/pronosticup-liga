// Un partido que ya debería haber terminado y sigue como "Programado" o "En juego":
// casi siempre es la fuente de datos (football-data), que tarda en publicar el final.
// En vez de enseñar un estado que confunde, se dice que está pendiente de confirmar.

/** Minutos desde la hora del partido a partir de los que se considera "debería haber terminado". */
export const MINUTOS_PENDIENTE = 135

/** Más allá de esto no se marca (algo raro de verdad: que lo mire el admin). */
const DIAS_MAXIMO = 3

export const AYUDA_PENDIENTE =
  'Ya debería haber terminado: el resultado oficial llegará en cuanto lo publique la fuente de datos. Tus puntos no se pierden.'

function aFecha(horario) {
  if (!horario) return null
  const texto = String(horario)
  const fecha = new Date(texto.includes('T') ? texto : texto.replace(' ', 'T'))
  return Number.isNaN(fecha.getTime()) ? null : fecha
}

export function pendienteDeConfirmar(estado, horario, ahora = Date.now()) {
  if (estado !== 'Programado' && estado !== 'En juego') return false
  const fecha = aFecha(horario)
  if (!fecha) return false
  const minutos = (ahora - fecha.getTime()) / 60000
  return minutos > MINUTOS_PENDIENTE && minutos < DIAS_MAXIMO * 24 * 60
}
