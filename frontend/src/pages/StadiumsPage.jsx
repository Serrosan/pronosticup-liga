import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

const MEDALLAS = ['🥇', '🥈', '🥉']

function BarraCapacidad({ capacidad, maxima }) {
  const porcentaje = Math.max(4, Math.round((capacidad / maxima) * 100))
  return (
    <div className="w-full h-2 bg-borde/10 rounded-full overflow-hidden">
      <div className="h-full bg-acento rounded-full" style={{ width: `${porcentaje}%` }} />
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

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <h1 className="font-display text-2xl text-texto mb-1">Estadios de LaLiga</h1>
      <p className="font-body text-sm text-borde mb-6">Ordenados por capacidad, de mayor a menor</p>

      <div className="flex flex-col gap-4">
        {data.estadios.map((estadio, index) => (
          <div key={estadio.id} className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
            {estadio.foto_url && (
              <div className="w-full h-40 overflow-hidden">
                <img src={estadio.foto_url} alt={estadio.nombre} className="w-full h-full object-cover" loading="lazy" />
              </div>
            )}
            <div className="p-4">
              <div className="flex items-start justify-between gap-3 mb-2">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="font-marcador text-lg text-borde shrink-0">{MEDALLAS[index] ?? `#${index + 1}`}</span>
                  <div className="min-w-0">
                    <p className="font-display text-base text-texto truncate">{estadio.nombre}</p>
                    {estadio.equipo && (
                      <Link
                        to={`/equipos/${estadio.equipo.id}`}
                        state={{ nombre: estadio.equipo.nombre, escudo_url: estadio.equipo.escudo_url }}
                        className="flex items-center gap-1.5 mt-0.5 hover:opacity-80 transition"
                      >
                        {estadio.equipo.escudo_url && <img src={estadio.equipo.escudo_url} alt={estadio.equipo.nombre} className="w-4 h-4 object-contain" />}
                        <span className="font-body text-xs text-acento">{estadio.equipo.nombre}</span>
                      </Link>
                    )}
                  </div>
                </div>
                {estadio.ciudad && <span className="font-body text-xs text-borde shrink-0">📍 {estadio.ciudad}</span>}
              </div>

              <BarraCapacidad capacidad={estadio.capacidad} maxima={data.capacidadMaxima} />
              <p className="font-marcador text-sm text-texto mt-1">{estadio.capacidad?.toLocaleString('es-ES')} espectadores</p>

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
        ))}
      </div>
    </div>
  )
}

export default StadiumsPage