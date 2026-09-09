import useCerrarConEscape from '../hooks/useCerrarConEscape'

const STICKERS = [
  { archivo: 'gol.png', alt: '¡Gol!' },
  { archivo: 'var.png', alt: 'VAR revisando' },
  { archivo: 'roja.png', alt: 'Te has lucido' },
  { archivo: 'fichaje.png', alt: 'Fichaje confirmado' },
  { archivo: 'banquillo.png', alt: 'Directo al banquillo' },
  { archivo: 'suerte.png', alt: 'Suerte de principiante' },
  { archivo: 'manager_sofa.png', alt: 'Míster de sofá' },
  { archivo: 'lesion_ego.png', alt: 'Lesión de ego' },
  { archivo: 'ojo_arbitro.png', alt: '¿Eso es penalti?' },
  { archivo: 'modo_fantasma.png', alt: 'Modo fantasma' },
  { archivo: 'dropeo.png', alt: 'Se le cayó' },
  { archivo: 'analista.png', alt: 'Soy el mejor analista' },
]

function SelectorStickers({ onSeleccionar, onCerrar }) {
  useCerrarConEscape(true, onCerrar)

  return (
    <div
      className="
        fixed left-1/2 bottom-24 -translate-x-1/2 w-[calc(100vw-2rem)] max-w-[280px]
        sm:absolute sm:left-0 sm:bottom-full sm:translate-x-0 sm:mb-2 sm:w-[280px] sm:max-w-none
        bg-fondo border border-borde/30 rounded-lg p-3 shadow-lg z-20 grid grid-cols-4 gap-2 max-h-[240px] overflow-y-auto
      "
    >
      {STICKERS.map((s) => (
        <button
          key={s.archivo}
          onClick={() => { onSeleccionar(s.archivo); onCerrar() }}
          className="hover:bg-borde/10 rounded-lg p-1 transition"
          title={s.alt}
        >
          <img src={`/stickers/${s.archivo}`} alt={s.alt} className="w-full h-auto" />
        </button>
      ))}
    </div>
  )
}

export default SelectorStickers