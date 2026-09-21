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

function SeccionRareza({ rareza, cartas }) {
  return (
    <div className="mb-6">
      <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">{ETIQUETA_RAREZA[rareza] ?? rareza}</p>
      <div className="flex flex-wrap gap-4">
        {cartas.map((carta) => (
          <CartaJuego key={carta.id} carta={carta} categoriaNombre={carta.categoria_nombre} categoriaIcono={carta.categoria_icono} tamano="mini" />
        ))}
      </div>
    </div>
  )
}

function SeccionCategoria({ nombre, porRareza }) {
  return (
    <div className="mb-10">
      <h2 className="font-display text-lg text-texto mb-4">{nombre}</h2>
      {ORDEN_RAREZA.filter((r) => porRareza[r]?.length > 0).map((rareza) => (
        <SeccionRareza key={rareza} rareza={rareza} cartas={porRareza[rareza]} />
      ))}
    </div>
  )
}

function CatalogoCartasPage() {
  useTitulo('Catálogo de Cartas')

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
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-8">
        <TicketHeader titulo="Catálogo de Cartas" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Todas las cartas que existen ahora mismo, agrupadas por categoría y rareza.
          </p>
        </div>
      </div>

      {categorias.length === 0 ? (
        <p className="font-body text-sm text-borde text-center py-12">Aún no hay ninguna carta creada.</p>
      ) : (
        categorias.map((categoria) => (
          <SeccionCategoria key={categoria} nombre={categoria} porRareza={agrupado[categoria]} />
        ))
      )}
    </div>
  )
}

export default CatalogoCartasPage