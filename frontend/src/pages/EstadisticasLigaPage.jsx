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

function SinDatos() {
  return <p className="font-body text-sm text-borde">Aún sin datos suficientes.</p>
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
          ) : <SinDatos />}
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
          ) : <SinDatos />}
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
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🔥" titulo="La mejor racha de la historia de la liga">
          {data.mejor_racha_historica ? (
            <>
              {data.mejor_racha_historica.avatar_url && (
                <img src={data.mejor_racha_historica.avatar_url} alt={data.mejor_racha_historica.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.mejor_racha_historica.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">
                {data.mejor_racha_historica.racha} aciertos seguidos
              </p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🎇" titulo="La jornada más goleadora">
          {data.jornada_mas_goleadora ? (
            <>
              <p className="font-marcador text-4xl font-bold text-premio">{data.jornada_mas_goleadora.goles}</p>
              <p className="font-body text-xs text-borde mt-1">
                goles en la Jornada {data.jornada_mas_goleadora.jornada}
              </p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="😱" titulo="La mayor sorpresa de la liga">
          {data.partido_sorpresa ? (
            <>
              <div className="flex items-center justify-center gap-2 mb-1">
                {data.partido_sorpresa.escudo_local && <img src={data.partido_sorpresa.escudo_local} alt="" className="w-6 h-6 object-contain" />}
                <p className="font-body text-sm text-texto">{data.partido_sorpresa.equipo_local} {data.partido_sorpresa.goles_casa}-{data.partido_sorpresa.goles_fuera} {data.partido_sorpresa.equipo_visitante}</p>
                {data.partido_sorpresa.escudo_visitante && <img src={data.partido_sorpresa.escudo_visitante} alt="" className="w-6 h-6 object-contain" />}
              </div>
              <p className="font-body text-xs text-borde mt-1">
                Solo el {data.partido_sorpresa.porcentaje_acierto}% de la liga acertó el signo
              </p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🎯" titulo="El rey de los resultados exactos">
          {data.rey_de_los_exactos ? (
            <>
              {data.rey_de_los_exactos.avatar_url && (
                <img src={data.rey_de_los_exactos.avatar_url} alt={data.rey_de_los_exactos.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.rey_de_los_exactos.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">
                {data.rey_de_los_exactos.total} resultados exactos
              </p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>
      </div>
    </div>
  )
}

export default EstadisticasLigaPage