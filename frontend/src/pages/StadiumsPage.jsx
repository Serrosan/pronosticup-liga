import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

const MEDALLAS = ['🥇', '🥈', '🥉']

function BarraCapacidad({ capacidad, maxima, color }) {
  const porcentaje = Math.max(4, Math.round((capacidad / maxima) * 100))
  return (
    <div className="w-full h-2 bg-borde/10 rounded-full overflow-hidden">
      <div className="h-full rounded-full" style={{ width: `${porcentaje}%`, backgroundColor: color }} />
    </div>
  )
}

function TarjetaEstadio({ estadio, posicion, capacidadMaxima }) {
  const colorEquipo = estadio.equipo?.color_primario ?? '#178A47'

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
      <div className="relative h-44">
        {estadio.foto_url ? (
          <img src={estadio.foto_url} alt={estadio.nombre} className="w-full h-full object-cover" loading="lazy" />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-4xl bg-borde/10">🏟️</div>
        )}
        <div
          className="absolute inset-0"
          style={{ background: `linear-gradient(to top, ${colorEquipo}E6 0%, ${colorEquipo}55 40%, transparent 75%)` }}
        />
        <span className="absolute top-3 left-3 font-marcador text-lg bg-fondo/90 rounded-full w-9 h-9 flex items-center justify-center shadow">
          {MEDALLAS[posicion] ?? `#${posicion + 1}`}
        </span>
        {estadio.ciudad && (
          <span className="absolute top-3 right-3 font-body text-xs text-white bg-black/30 rounded-full px-2.5 py-1">
            📍 {estadio.ciudad}
          </span>
        )}
        <div className="absolute bottom-0 left-0 right-0 p-4">
          <p className="font-display text-xl text-white leading-tight drop-shadow">{estadio.nombre}</p>
          {estadio.equipo && (
            <Link
              to={`/equipos/${estadio.equipo.id}`}
              state={{ nombre: estadio.equipo.nombre, escudo_url: estadio.equipo.escudo_url }}
              className="flex items-center gap-1.5 mt-1 w-fit hover:opacity-80 transition"
            >
              {estadio.equipo.escudo_url && <img src={estadio.equipo.escudo_url} alt={estadio.equipo.nombre} className="w-5 h-5 object-contain" />}
              <span className="font-body text-sm text-white/90 font-medium">{estadio.equipo.nombre}</span>
            </Link>
          )}
        </div>
      </div>

      <div className="p-4">
        <BarraCapacidad capacidad={estadio.capacidad} maxima={capacidadMaxima} color={colorEquipo} />
        <p className="font-marcador text-base text-texto mt-1.5">{estadio.capacidad?.toLocaleString('es-ES')} espectadores</p>

        {(estadio.anio_construccion || estadio.anio_ult_remodelacion || estadio.tamanio_campo) && (
          <div className="flex gap-4 mt-3 pt-3 border-t border-borde/10">
            {estadio.anio_construccion && (
              <div>
                <p className="font-marcador text-xs text-texto">{estadio.anio_construccion}</p>
                <p className="font-body text-[10px] text-borde">Construcción</p>
              </div>
            )}
            {estadio.anio_ult_remodelacion && (
              <div>
                <p className="font-marcador text-xs text-texto">{estadio.anio_ult_remodelacion}</p>
                <p className="font-body text-[10px] text-borde">Última reforma</p>
              </div>
            )}
            {estadio.tamanio_campo && (
              <div>
                <p className="font-marcador text-xs text-texto">{estadio.tamanio_campo}</p>
                <p className="font-body text-[10px] text-borde">Terreno de juego</p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

function StadiumsPage() {
  useTitulo('Estadios de LaLiga')

  const { data, isLoading, error } = useQuery({
    queryKey: ['estadios'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/estadios')
      return { estadios: respuesta.data.data, capacidadMaxima: respuesta.data.meta?.capacidad_maxima }
    },
  })

  if (isLoading) return <div className="max-w-5xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      <h1 className="font-display text-2xl text-texto mb-1">Estadios de LaLiga</h1>
      <p className="font-body text-sm text-borde mb-6">Ordenados por capacidad, de mayor a menor</p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {data.estadios.map((estadio, index) => (
          <TarjetaEstadio key={estadio.id} estadio={estadio} posicion={index} capacidadMaxima={data.capacidadMaxima} />
        ))}
      </div>
    </div>
  )
}

export default StadiumsPage