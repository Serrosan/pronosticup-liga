// Única fuente en el frontend para "qué tipo de acierto fue un pronóstico" y
// cómo se pinta. La usan MatchCard, Mis Pronósticos y el detalle de puntos de
// un usuario, para que las tres pantallas digan lo mismo con el mismo aspecto.
//
// Cada tipo lleva color + icono + palabra: el color nunca es la única pista.

export const TIPOS = {
  exacto: { color: '#22C55E', icono: '★', texto: 'Exacto', leyenda: 'Resultado exacto' },
  diferencia: { color: '#F59E0B', icono: '≈', texto: 'Diferencia', leyenda: 'Diferencia (o empate cercano)' },
  signo: { color: 'var(--color-acento)', icono: '✓', texto: 'Signo', leyenda: 'Solo el signo' },
  fallo: { color: '#EF4444', icono: '✕', texto: 'Fallo', leyenda: 'Fallo' },
  pendiente: { color: 'var(--color-borde)', icono: '○', texto: 'Pendiente', leyenda: 'Pendiente' },
}

export const TIPOS_RESUELTOS = ['exacto', 'diferencia', 'signo', 'fallo']

// Nombres que usa el backend en tipo_evento.
const TIPO_POR_EVENTO = {
  AciertoExacto: 'exacto',
  AciertoDiferencia: 'diferencia',
  Acierto1x2: 'signo',
  Fallo: 'fallo',
}

// Fondo suave del mismo color. color-mix funciona también cuando el color es
// una variable CSS (pegarle "1F" detrás a un var(...) no es un color válido).
export function fondoSuave(color) {
  return `color-mix(in srgb, ${color} 14%, transparent)`
}

export function estaResuelto(golesCasa, golesFuera, estadoPartido) {
  return (
    estadoPartido === 'Jugado' &&
    golesCasa !== null && golesCasa !== undefined &&
    golesFuera !== null && golesFuera !== undefined
  )
}

function resultado1x2(golesLocal, golesVisitante) {
  if (golesLocal > golesVisitante) return 'Local'
  if (golesLocal < golesVisitante) return 'Visitante'
  return 'Empate'
}

// Con los cuatro números sueltos (así lo tiene MatchCard).
export function tipoDeMarcador(golesLocalPred, golesVisitantePred, golesCasa, golesFuera) {
  const localPred = Number(golesLocalPred)
  const visitantePred = Number(golesVisitantePred)
  const casa = Number(golesCasa)
  const fuera = Number(golesFuera)

  if (localPred === casa && visitantePred === fuera) return 'exacto'

  const resultadoReal = resultado1x2(casa, fuera)
  if (resultado1x2(localPred, visitantePred) !== resultadoReal) return 'fallo'

  if (resultadoReal === 'Empate') {
    return Math.abs(localPred - casa) === 1 ? 'diferencia' : 'signo'
  }

  return casa - fuera === localPred - visitantePred ? 'diferencia' : 'signo'
}

// Con el pronóstico como texto "2-1".
export function tipoDePronostico(prediccionTexto, golesCasa, golesFuera) {
  const [golesLocalPred, golesVisitantePred] = String(prediccionTexto).split('-')
  return tipoDeMarcador(golesLocalPred, golesVisitantePred, golesCasa, golesFuera)
}

// Con un partido tal como llega en las listas de pronósticos. Si el backend
// ya manda tipo_evento, manda él; si no, se calcula con el marcador.
export function tipoDePartido(partido) {
  if (partido.oculto || !partido.mi_pronostico) return 'pendiente'
  if (!estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado_partido)) return 'pendiente'
  if (TIPO_POR_EVENTO[partido.tipo_evento]) return TIPO_POR_EVENTO[partido.tipo_evento]
  return tipoDePronostico(partido.mi_pronostico, partido.goles_casa, partido.goles_fuera)
}
