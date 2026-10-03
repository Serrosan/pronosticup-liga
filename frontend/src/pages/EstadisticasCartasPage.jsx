import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

const COLOR_RAREZA = {
  Comun: '#C8FF4D',
  PocoComun: '#4DA6FF',
  Rara: '#B44DFF',
  Legendaria: '#FFB238',
}

function TarjetaDato({ icono, titulo, children, colorAcento }) {
  return (
    <div
      className="bg-fondo border rounded-lg p-5 text-center"
      style={colorAcento ? { borderColor: `${colorAcento}55`, boxShadow: `0 0 14px ${colorAcento}22` } : { borderColor: 'var(--color-borde)', opacity: 0.9 }}
    >
      <p className="text-3xl mb-2">{icono}</p>
      <p className="font-body text-[10px] uppercase tracking-widest text-borde mb-2">{titulo}</p>
      {children}
    </div>
  )
}

function SinDatos() {
  return <p className="font-body text-sm text-borde">Aún sin datos suficientes.</p>
}

function EstadisticasCartasPage() {
  useTitulo('Estadísticas de Cartas')

  const { data, isLoading, error } = useQuery({
    queryKey: ['estadisticas-cartas'],
    queryFn: async () => (await client.get('/api/v1/estadisticas-cartas')).data.data,
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const colorCartaMasJugada = data.carta_mas_jugada ? COLOR_RAREZA[data.carta_mas_jugada.rareza] : null

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Estadísticas de Cartas" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Curiosidades del modo Cartas de esta liga, calculadas con vuestro historial real.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <TarjetaDato icono="⭐" titulo="Quién ha sacado más Legendarias" colorAcento={COLOR_RAREZA.Legendaria}>
          {data.mas_legendarias ? (
            <>
              {data.mas_legendarias.avatar_url && (
                <img src={data.mas_legendarias.avatar_url} alt={data.mas_legendarias.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.mas_legendarias.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">{data.mas_legendarias.total} Legendarias obtenidas</p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🃏" titulo="La carta más jugada de la liga" colorAcento={colorCartaMasJugada}>
          {data.carta_mas_jugada ? (
            <>
              <p className="font-body text-base font-semibold text-texto">{data.carta_mas_jugada.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">
                {data.carta_mas_jugada.categoria} · jugada {data.carta_mas_jugada.total} veces
              </p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="💰" titulo="El rey de los puntos por cartas">
          {data.rey_de_puntos ? (
            <>
              {data.rey_de_puntos.avatar_url && (
                <img src={data.rey_de_puntos.avatar_url} alt={data.rey_de_puntos.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.rey_de_puntos.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">+{data.rey_de_puntos.total} puntos gracias a sus cartas</p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="⚔️" titulo="El más agresivo">
          {data.mas_agresivo ? (
            <>
              {data.mas_agresivo.avatar_url && (
                <img src={data.mas_agresivo.avatar_url} alt={data.mas_agresivo.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.mas_agresivo.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">{data.mas_agresivo.total} Faltas jugadas contra otros</p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🎯" titulo="El más atacado">
          {data.mas_atacado ? (
            <>
              {data.mas_atacado.avatar_url && (
                <img src={data.mas_atacado.avatar_url} alt={data.mas_atacado.nombre} className="w-10 h-10 rounded-full object-cover mx-auto mb-1" />
              )}
              <p className="font-body text-base font-semibold text-texto">{data.mas_atacado.nombre}</p>
              <p className="font-body text-xs text-borde mt-1">{data.mas_atacado.total} Faltas recibidas</p>
            </>
          ) : <SinDatos />}
        </TarjetaDato>

        <TarjetaDato icono="🛡️" titulo="Escudos que salvaron a alguien">
          <p className="font-marcador text-4xl font-bold text-acento">{data.escudos_exitosos}</p>
          <p className="font-body text-xs text-borde mt-1">ataques bloqueados de verdad</p>
        </TarjetaDato>

        <TarjetaDato icono="🍀" titulo="Amuletos con suerte">
          <p className="font-marcador text-4xl font-bold text-acento">{data.amuletos_exitosos}</p>
          <p className="font-body text-xs text-borde mt-1">fallos protegidos con éxito</p>
        </TarjetaDato>

        <TarjetaDato icono="🎁" titulo="Sobres abiertos en la liga">
          <p className="font-marcador text-4xl font-bold text-premio">{data.sobres_abiertos}</p>
          <p className="font-body text-xs text-borde mt-1">entre todos, desde el principio</p>
        </TarjetaDato>
      </div>
    </div>
  )
}

export default EstadisticasCartasPage