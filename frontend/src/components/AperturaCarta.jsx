import { useState, useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import CartaJuego from './CartaJuego'

const COLORES_RAREZA = {
  Comun: '#C8FF4D',
  PocoComun: '#4DA6FF',
  Rara: '#B44DFF',
  Legendaria: '#FFB238',
}

const TOTAL_SLOTS = 22
const INDICE_GANADOR = 18
const ANCHO_SLOT = 155 // px — ajustado para que quepa la escala base de 1.1 sin que las cartas se pisen

// Genera los "tics" de un carrusel que empieza rápido y va frenando, imitando la
// misma curva de frenado que usa la animación visual (cubic-bezier decelerando).
function programarTics(duracionMs, reproducirTic) {
  const intervalos = []
  let acumulado = 0
  let paso = 55

  while (acumulado < duracionMs) {
    intervalos.push(acumulado)
    acumulado += paso
    paso = Math.min(paso * 1.13, 420) // se va espaciando cada vez más, como una ruleta real
  }

  const timeouts = intervalos.map((t) => setTimeout(reproducirTic, t))
  return () => timeouts.forEach(clearTimeout)
}

function reproducirTic(audioCtxRef) {
  try {
    if (!audioCtxRef.current) {
      audioCtxRef.current = new (window.AudioContext || window.webkitAudioContext)()
    }
    const ctx = audioCtxRef.current
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'square'
    osc.frequency.value = 1100
    gain.gain.setValueAtTime(0.05, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.04)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start()
    osc.stop(ctx.currentTime + 0.04)
  } catch {
    // Si el navegador bloquea el audio (sin interacción previa, etc.), simplemente no suena — no es crítico.
  }
}

const ETIQUETA_RAREZA_REVELACION = {
  Comun: null,
  PocoComun: null,
  Rara: null,
  Legendaria: '🎆 ¡LEGENDARIA! 🎆',
}

function SlotCarrusel({ carta, esGanador, centrando }) {
  return (
    <div
      className="shrink-0 flex items-center justify-center transition-transform duration-300"
      style={{ width: ANCHO_SLOT, transform: esGanador && centrando ? 'scale(1.5)' : 'scale(1.1)', zIndex: esGanador && centrando ? 10 : 1 }}
    >
      <CartaJuego
        carta={carta}
        categoriaNombre={carta.categoria_nombre}
        categoriaIcono={carta.categoria_icono}
        tamano="pequena"
      />
    </div>
  )
}

function AperturaCarta({ cartaGanada, onCerrar }) {
  const [fase, setFase] = useState('preparando') // preparando -> girando -> centrando -> revelada
  const audioCtxRef = useRef(null)

  const { data: catalogo } = useQuery({
    queryKey: ['catalogo-cartas'],
    queryFn: async () => (await client.get('/api/v1/catalogo-cartas')).data.data,
  })

  const colorGanador = COLORES_RAREZA[cartaGanada.tipo_carta.rareza] ?? COLORES_RAREZA.Comun
  const etiquetaRareza = ETIQUETA_RAREZA_REVELACION[cartaGanada.tipo_carta.rareza]
  const esRarezaAlta = cartaGanada.tipo_carta.rareza === 'Rara' || cartaGanada.tipo_carta.rareza === 'Legendaria'

  const [slots] = useState(null) // placeholder, se rellena cuando el catálogo llega — ver abajo
  const slotsRef = useRef(null)

  if (catalogo && !slotsRef.current) {
    const generados = Array.from({ length: TOTAL_SLOTS }, () => catalogo[Math.floor(Math.random() * catalogo.length)])
    // La carta ganadora viene con la forma del modelo (categoria.nombre), no la forma
    // "aplanada" del catálogo público (categoria_nombre) — la normalizamos aquí.
    generados[INDICE_GANADOR] = {
      ...cartaGanada.tipo_carta,
      categoria_nombre: cartaGanada.tipo_carta.categoria?.nombre,
      categoria_icono: cartaGanada.tipo_carta.categoria?.icono,
    }
    slotsRef.current = generados
  }

  useEffect(() => {
    if (!catalogo) return

    const inicioGiro = setTimeout(() => setFase('girando'), 100)
    const inicioCentrado = setTimeout(() => setFase('centrando'), 3900)
    const fin = setTimeout(() => setFase('revelada'), 4400)

    const cancelarTics = programarTics(4300, () => reproducirTic(audioCtxRef))

    return () => {
      clearTimeout(inicioGiro)
      clearTimeout(inicioCentrado)
      clearTimeout(fin)
      cancelarTics()
    }
  }, [catalogo])

  if (!catalogo || !slotsRef.current) {
    return (
      <div className="fixed inset-0 bg-black/85 flex items-center justify-center z-50">
        <p className="font-body text-sm text-white/70">Preparando...</p>
      </div>
    )
  }

  const enMovimiento = fase === 'girando' || fase === 'centrando'
  const offset = enMovimiento ? -(INDICE_GANADOR * ANCHO_SLOT + ANCHO_SLOT / 2) : 0

  return (
    <div className="fixed inset-0 bg-black/95 flex items-center justify-center z-50 p-4">
      {fase !== 'revelada' ? (
        <div className="w-full max-w-3xl">
          <p className="font-body text-sm text-white/70 text-center mb-4">Abriendo carta...</p>
          <div
            className="relative overflow-hidden h-56"
            style={{ maskImage: 'linear-gradient(to right, transparent, black 15%, black 85%, transparent)', WebkitMaskImage: 'linear-gradient(to right, transparent, black 15%, black 85%, transparent)' }}
          >
            <div
              className="absolute top-1/2 left-1/2 flex"
              style={{
                transform: `translate(${offset}px, -50%)`,
                transition: fase === 'girando' || fase === 'centrando' ? 'transform 4.2s cubic-bezier(0.1, 0.7, 0.15, 1)' : 'none',
              }}
            >
              {slotsRef.current.map((carta, i) => (
                <SlotCarrusel key={i} carta={carta} esGanador={i === INDICE_GANADOR} centrando={fase === 'centrando'} />
              ))}
            </div>
            <div className="absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-[2px] bg-premio pointer-events-none" />
            <div className="absolute -top-1 left-1/2 -translate-x-1/2 w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[8px] border-t-premio pointer-events-none" />
          </div>
        </div>
      ) : (
        <div className="relative flex flex-col items-center gap-5 animate-[fadeIn_0.5s_ease-out]">
          <div
            className={`absolute inset-0 -m-20 pointer-events-none ${esRarezaAlta ? 'animate-pulse' : ''}`}
            style={{ background: `radial-gradient(circle, ${colorGanador}${esRarezaAlta ? '55' : '33'} 0%, transparent 65%)` }}
          />
          {etiquetaRareza && (
            <p className="relative z-10 font-display text-base tracking-widest mb-2" style={{ color: colorGanador }}>
              {etiquetaRareza}
            </p>
          )}
          <div className="relative z-0 scale-125 mb-10 mt-2" style={{ filter: `drop-shadow(0 0 45px ${colorGanador}cc)` }}>
            <CartaJuego carta={cartaGanada.tipo_carta} categoriaNombre={cartaGanada.tipo_carta.categoria.nombre} categoriaIcono={cartaGanada.tipo_carta.categoria.icono} />
          </div>
          <button
            onClick={onCerrar}
            className="relative font-body text-sm font-semibold bg-acento text-fondo rounded-full px-6 py-2.5 hover:brightness-110"
          >
            Añadir a mi mano
          </button>
        </div>
      )}
    </div>
  )
}

export default AperturaCarta