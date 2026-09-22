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
      <div onClick={(evento) => evento.stopPropagation()}>
        <CartaJuego carta={carta} categoriaNombre={carta.categoria_nombre} categoriaIcono={carta.categoria_icono} />
      </div>
    </div>
  )
}

function SeccionRareza({ rareza, cartas, onSeleccionar }) {
  return (
    <div className="mb-6">
      <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">{ETIQUETA_RAREZA[rareza] ?? rareza}</p>
      <div className="flex flex-wrap gap-4">
        {cartas.map((carta) => (
          <button key={carta.id} onClick={() => onSeleccionar(carta)} className="hover:scale-105 transition-transform">
            <CartaJuego carta={carta} categoriaNombre={carta.categoria_nombre} categoriaIcono={carta.categoria_icono} tamano="mini" />
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

function CatalogoCartasPage() {
  useTitulo('Catálogo de Cartas')
  const [cartaSeleccionada, setCartaSeleccionada] = useState(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['catalogo-cartas'],
    queryFn: async () => (await client.get('/api/v1/catalogo-cartas')).data.data,
  })

  if (isLoading) return <div className="max-w-5xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const agrupado = agruparPorCategoriaYRareza(data)
  const categorias = Object.keys(agrupado).sort()

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      {cartaSeleccionada && <CartaAmpliada carta={cartaSeleccionada} onCerrar={() => setCartaSeleccionada(null)} />}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-8">
        <TicketHeader titulo="Catálogo de Cartas" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Todas las cartas que existen ahora mismo, agrupadas por categoría y rareza. Toca cualquiera para verla más grande.
          </p>
        </div>
      </div>

      {categorias.length === 0 ? (
        <p className="font-body text-sm text-borde text-center py-12">Aún no hay ninguna carta creada.</p>
      ) : (
        categorias.map((categoria) => (
          <SeccionCategoria key={categoria} nombre={categoria} porRareza={agrupado[categoria]} onSeleccionar={setCartaSeleccionada} />
        ))
      )}
    </div>
  )
}

export default CatalogoCartasPage