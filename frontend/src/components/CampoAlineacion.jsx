const ALTURA_CAMPO = 150
const ANCHO_CAMPO = 110 // un poco más ancho que el alto real (relación 100:150) — a propósito, para dar más aire horizontal entre jugadores de la misma línea

function lineasDeFormacion(formacion) {
  if (!formacion) return null
  const lineas = formacion.split('').map(Number)
  return lineas.every((n) => Number.isInteger(n) && n > 0) ? lineas : null
}

// Portero = el de posicion_formacion más baja (siempre 1 en los datos reales de
// LaLiga). El resto se reparte en líneas según la formación, en el mismo orden
// que ya vienen numerados (2,3,4... = de defensa a ataque).
function calcularPosiciones(formacion, titulares) {
  const lineas = lineasDeFormacion(formacion)
  if (!lineas) return null

  const conPosicion = titulares.filter((j) => j.posicion_formacion != null)
  if (conPosicion.length < 11) return null // falta algún dato — mejor no dibujar a medias

  const ordenados = [...conPosicion].sort((a, b) => a.posicion_formacion - b.posicion_formacion)
  const portero = ordenados[0]
  const resto = ordenados.slice(1)

  const yPortero = ALTURA_CAMPO - 12
  const yDefensaMasBaja = ALTURA_CAMPO - 34 // justo por delante de su propia área
  const yAtaqueMasAlto = 16 // pegado al área rival, como en cualquier alineación real

  const posiciones = [{ jugador: portero, x: ANCHO_CAMPO / 2, y: yPortero }]

  let indice = 0
  lineas.forEach((cantidad, indiceLinea) => {
    const jugadoresLinea = resto.slice(indice, indice + cantidad)
    indice += cantidad
    // La defensa se queda abajo, el ataque sube hasta arriba del todo — cada
    // línea intermedia se reparte el espacio restante, en vez de apiñarse
    // todas cerca del centro del campo.
    const fraccion = lineas.length > 1 ? indiceLinea / (lineas.length - 1) : 0
    const y = yDefensaMasBaja - fraccion * (yDefensaMasBaja - yAtaqueMasAlto)
    jugadoresLinea.forEach((jugador, i) => {
      const x = ((i + 1) / (cantidad + 1)) * ANCHO_CAMPO
      posiciones.push({ jugador, x, y })
    })
  })

  return posiciones
}

function MarcasDelCampo() {
  const centro = ANCHO_CAMPO / 2
  return (
    <g stroke="white" strokeOpacity="0.4" strokeWidth="0.6" fill="none">
      <rect x="2.2" y="2" width={ANCHO_CAMPO - 4.4} height="146" />
      <line x1="2.2" y1="75" x2={ANCHO_CAMPO - 2.2} y2="75" />
      <circle cx={centro} cy="75" r="11" />
      <circle cx={centro} cy="75" r="0.8" fill="white" fillOpacity="0.4" stroke="none" />
      <rect x={centro - 27.5} y="2" width="55" height="20" />
      <rect x={centro - 13.2} y="2" width="26.4" height="7" />
      <rect x={centro - 27.5} y="126" width="55" height="20" />
      <rect x={centro - 13.2} y="141" width="26.4" height="7" />
    </g>
  )
}

// Reparte el nombre en 2 líneas lo más equilibradas posible, por la palabra
// más cercana al centro — en vez de cortar con "…" y perder información.
// Un nombre de 1 sola palabra (lo habitual en nombre_camiseta) se queda en 1 línea.
function dividirEnDosLineas(texto) {
  const partes = texto.split(' ')
  if (partes.length === 1) return [texto, null]

  let mejorIndice = 1
  let mejorDiferencia = Infinity
  for (let i = 1; i < partes.length; i++) {
    const linea1 = partes.slice(0, i).join(' ')
    const linea2 = partes.slice(i).join(' ')
    const diferencia = Math.abs(linea1.length - linea2.length)
    if (diferencia < mejorDiferencia) {
      mejorDiferencia = diferencia
      mejorIndice = i
    }
  }
  return [partes.slice(0, mejorIndice).join(' '), partes.slice(mejorIndice).join(' ')]
}

function Chapita({ jugador, x, y }) {
  const [linea1, linea2] = dividirEnDosLineas(jugador.nombre)

  return (
    <g transform={`translate(${x}, ${y})`}>
      <circle r="6.5" className="fill-acento" stroke="var(--color-fondo)" strokeWidth="0.8" />
      <text
        y="2"
        textAnchor="middle"
        className="fill-fondo font-marcador font-bold"
        style={{ fontSize: '6px' }}
      >
        {jugador.dorsal ?? '?'}
      </text>
      <text y="11" textAnchor="middle" className="fill-texto font-body" style={{ fontSize: '4px' }}>
        {linea1}
      </text>
      {linea2 && (
        <text y="15" textAnchor="middle" className="fill-texto font-body" style={{ fontSize: '4px' }}>
          {linea2}
        </text>
      )}
    </g>
  )
}

/**
 * Dibuja el once titular sobre un campo, en su posición de formación real
 * (defensa/centro/ataque). Si falta el dato de posicion_formacion en algún
 * jugador (partidos antiguos aún sin reimportar con el scraper actualizado),
 * no se dibuja nada — se vuelve null para que quien lo use muestre la lista
 * de texto de siempre en su lugar.
 */
function CampoAlineacion({ formacion, titulares }) {
  const posiciones = calcularPosiciones(formacion, titulares)
  if (!posiciones) return null

  return (
    <svg viewBox={`0 0 ${ANCHO_CAMPO} ${ALTURA_CAMPO}`} className="w-full rounded-lg" style={{ backgroundColor: '#1E5B32' }}>
      <MarcasDelCampo />
      {posiciones.map(({ jugador, x, y }, i) => <Chapita key={i} jugador={jugador} x={x} y={y} />)}
    </svg>
  )
}

// Para que quien use este componente pueda decidir ANTES de renderizar si
// mostrar el campo o su alternativa de lista de texto (sin duplicar la lógica
// de "¿tenemos datos suficientes?").
export function puedeDibujarCampo(formacion, titulares) {
  return calcularPosiciones(formacion, titulares) !== null
}

export default CampoAlineacion