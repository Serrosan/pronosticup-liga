import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

function TarjetaDato({ icono, titulo, children }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg p-5 text-center">
      <p className="text-3xl mb-2">{icono}</p>
      <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2">{titulo}</p>
      {children}
    </div>
  )
}

function EstadisticasLigaPage() {
  useTitulo('Estadísticas de la liga')

  const { data, isLoading, error } = useQuery({
    queryKey: ['estadisticas-liga'],
    queryFn: async () => (await client.get('/api/v1/estadisticas-liga')).data.data,
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Estadísticas de la liga" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Curiosidades de esta temporada, calculadas con los datos reales de LaLiga y de vuestros pronósticos.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <TarjetaDato icono="⚽" titulo="Goles marcados esta temporada">
          <p className="font-marcador text-4xl font-bold text-acento">{data.goles_totales}</p>
        </TarjetaDato>

        <TarjetaDato icono="🔁" titulo="Resultado real más repetido">
          {data.resultado_mas_repetido ? (
            <>
              <p className="font-marcador text-4xl font-bold text-premio">{data.resultado_mas_repetido}</p>
              <p className="font-body text-xs text-borde mt-1">
                {data.veces_resultado_mas_repetido} veces esta temporada
              </p>
            </>
          ) : (
            <p className="font-body text-sm text-borde">Aún sin datos suficientes.</p>
          )}
        </TarjetaDato>

        <TarjetaDato icono="😰" titulo="El equipo que más os cuesta acertar">
          {data.equipo_mas_dificil ? (
            <>
              {data.equipo_mas_dificil.escudo_url && (
                <img src={data.equipo_mas_dificil.escudo_url} alt={data.equipo_mas_dificil.nombre} className="w-10 h-10 object-contain mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.equipo_mas_dificil.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">
                {data.equipo_mas_dificil.porcentaje_fallo}% de fallo en sus partidos
              </p>
            </>
          ) : (
            <p className="font-body text-sm text-borde">Aún sin datos suficientes.</p>
          )}
        </TarjetaDato>

        <TarjetaDato icono="🥅" titulo="Vuestro goleador favorito">
          {data.jugador_mas_elegido ? (
            <>
              {data.jugador_mas_elegido.foto_url && (
                <img src={data.jugador_mas_elegido.foto_url} alt={data.jugador_mas_elegido.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.jugador_mas_elegido.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">
                Elegido {data.jugador_mas_elegido.veces} veces
              </p>
            </>
          ) : (
            <p className="font-body text-sm text-borde">Aún sin datos suficientes.</p>
          )}
        </TarjetaDato>
      </div>
    </div>
  )
}

export default EstadisticasLigaPage
