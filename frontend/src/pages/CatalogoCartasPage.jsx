import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import CartaJuego from '../components/CartaJuego'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

const ORDEN_RAREZA = ['Comun', 'PocoComun', 'Rara', 'Legendaria']
const ETIQUETA_RAREZA = { Comun: 'Común', PocoComun: 'Poco común', Rara: 'Rara', Legendaria: 'Legendaria' }

function agruparPorCategoriaYRareza(cartas) {
  const porCategoria = {}

  cartas.forEach((carta) => {
    const categoria = carta.categoria_nombre
    if (!porCategoria[categoria]) porCategoria[categoria] = {}

    const rareza = carta.rareza
    if (!porCategoria[categoria][rareza]) porCategoria[categoria][rareza] = []

    porCategoria[categoria][rareza].push(carta)
  })

  return porCategoria
}

function CartaAmpliada({ carta, onCerrar }) {
  useEffect(() => {
    function alPulsarEscape(evento) {
      if (evento.key === 'Escape') onCerrar()
    }
    window.addEventListener('keydown', alPulsarEscape)
    return () => window.removeEventListener('keydown', alPulsarEscape)
  }, [onCerrar])

  return (
    <div
      className="fixed inset-0 bg-black/85 flex items-center justify-center z-50 p-4"
      onClick={onCerrar}
    >
      <button
        onClick={onCerrar}
        className="absolute top-4 right-4 text-white/70 hover:text-white text-2xl leading-none"
        aria-label="Cerrar"
      >
        ✕
      </button>
      <div onClick={(evento) => evento.stopPropagation()} className="flex flex-col items-center gap-3">
        <CartaJuego carta={carta} categoriaNombre={carta.categoria_nombre} categoriaIcono={carta.categoria_icono} />
        {carta.la_tienes ? (
          <span className="font-body text-xs font-semibold text-acento bg-acento/10 border border-acento/30 rounded-full px-3 py-1">
            ✓ La tienes ahora
          </span>
        ) : carta.la_has_tenido ? (
          <span className="font-body text-xs text-borde bg-borde/10 border border-borde/30 rounded-full px-3 py-1">
            Ya la has tenido antes
          </span>
        ) : (
          <span className="font-body text-xs text-borde/70 bg-borde/5 border border-borde/20 rounded-full px-3 py-1">
            Aún no te ha tocado
          </span>
        )}
      </div>
    </div>
  )
}

// Marca en la esquina de cada mini-carta: ✓ si la tienes ahora, un punto discreto si
// ya la has tenido pero no la conservas — nada si nunca te ha tocado.
function MarcaPosesion({ carta }) {
  if (carta.la_tienes) {
    return (
      <span
        className="absolute -top-1.5 -right-1.5 bg-acento text-fondo text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shadow"
        title="La tienes ahora"
      >
        ✓
      </span>
    )
  }
  if (carta.la_has_tenido) {
    return (
      <span
        className="absolute -top-1 -right-1 bg-borde/50 text-fondo text-[9px] font-bold rounded-full w-4 h-4 flex items-center justify-center"
        title="Ya la has tenido"
      >
        •
      </span>
    )
  }
  return null
}

function SeccionRareza({ rareza, cartas, onSeleccionar }) {
  return (
    <div className="mb-6">
      <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">{ETIQUETA_RAREZA[rareza] ?? rareza}</p>
      <div className="flex flex-wrap gap-4">
        {cartas.map((carta) => (
          <button key={carta.id} onClick={() => onSeleccionar(carta)} className="relative hover:scale-105 transition-transform">
            <CartaJuego carta={carta} categoriaNombre={carta.categoria_nombre} categoriaIcono={carta.categoria_icono} tamano="mini" />
            <MarcaPosesion carta={carta} />
          </button>
        ))}
      </div>
    </div>
  )
}

function SeccionCategoria({ nombre, porRareza, onSeleccionar }) {
  return (
    <div className="mb-10">
      <h2 className="font-display text-lg text-texto mb-4">{nombre}</h2>
      {ORDEN_RAREZA.filter((r) => porRareza[r]?.length > 0).map((rareza) => (
        <SeccionRareza key={rareza} rareza={rareza} cartas={porRareza[rareza]} onSeleccionar={onSeleccionar} />
      ))}
    </div>
  )
}

function BotonFiltro({ activo, onClick, children }) {
  return (
    <button
      onClick={onClick}
      className={`font-body text-xs rounded-full px-3 py-1.5 border transition whitespace-nowrap ${
        activo ? 'bg-acento text-fondo border-acento font-semibold' : 'text-borde border-borde/30 hover:text-texto hover:border-borde/50'
      }`}
    >
      {children}
    </button>
  )
}

function CatalogoCartasPage() {
  useTitulo('Catálogo de Cartas')
  const [cartaSeleccionada, setCartaSeleccionada] = useState(null)
  const [filtroCategoria, setFiltroCategoria] = useState('Todas')
  const [filtroRareza, setFiltroRareza] = useState('Todas')
  const [soloTuyas, setSoloTuyas] = useState(false)

  const { data, isLoading, error } = useQuery({
    queryKey: ['catalogo-cartas'],
    queryFn: async () => (await client.get('/api/v1/catalogo-cartas')).data.data,
  })

  if (isLoading) return <div className="max-w-5xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const categoriasDisponibles = [...new Set(data.map((c) => c.categoria_nombre))].sort()
  const descubiertas = data.filter((c) => c.la_has_tenido).length

  const filtradas = data.filter((carta) => {
    if (filtroCategoria !== 'Todas' && carta.categoria_nombre !== filtroCategoria) return false
    if (filtroRareza !== 'Todas' && carta.rareza !== filtroRareza) return false
    if (soloTuyas && !carta.la_tienes) return false
    return true
  })

  const agrupado = agruparPorCategoriaYRareza(filtradas)
  const categorias = Object.keys(agrupado).sort()

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      {cartaSeleccionada && <CartaAmpliada carta={cartaSeleccionada} onCerrar={() => setCartaSeleccionada(null)} />}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader
          titulo="Catálogo de Cartas"
          accion={
            <span className="font-marcador text-xs font-bold text-acento">
              {descubiertas}/{data.length} descubiertas
            </span>
          }
        />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Todas las cartas que existen ahora mismo. Toca cualquiera para verla más grande — el ✓ marca las que tienes hoy en tu mano.
          </p>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 mb-6">
        <BotonFiltro activo={filtroCategoria === 'Todas'} onClick={() => setFiltroCategoria('Todas')}>Todas</BotonFiltro>
        {categoriasDisponibles.map((cat) => (
          <BotonFiltro key={cat} activo={filtroCategoria === cat} onClick={() => setFiltroCategoria(cat)}>{cat}</BotonFiltro>
        ))}
        <div className="w-px h-5 bg-borde/20 mx-1" />
        <BotonFiltro activo={filtroRareza === 'Todas'} onClick={() => setFiltroRareza('Todas')}>Toda rareza</BotonFiltro>
        {ORDEN_RAREZA.map((r) => (
          <BotonFiltro key={r} activo={filtroRareza === r} onClick={() => setFiltroRareza(r)}>{ETIQUETA_RAREZA[r]}</BotonFiltro>
        ))}
        <div className="w-px h-5 bg-borde/20 mx-1" />
        <BotonFiltro activo={soloTuyas} onClick={() => setSoloTuyas(!soloTuyas)}>✓ Solo las tuyas</BotonFiltro>
      </div>

      {categorias.length === 0 ? (
        <p className="font-body text-sm text-borde text-center py-12">
          {data.length === 0 ? 'Aún no hay ninguna carta creada.' : 'Ninguna carta coincide con estos filtros.'}
        </p>
      ) : (
        categorias.map((categoria) => (
          <SeccionCategoria key={categoria} nombre={categoria} porRareza={agrupado[categoria]} onSeleccionar={setCartaSeleccionada} />
        ))
      )}
    </div>
  )
}

export default CatalogoCartasPage