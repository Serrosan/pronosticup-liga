import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import TicketHeader from '../components/TicketHeader'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'
import useTitulo from '../hooks/useTitulo'

const MEDALLAS = ['🥇', '🥈', '🥉']

function Avatar({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-8 h-8 rounded-full object-cover shrink-0" />
  return (
    <div className="w-8 h-8 rounded-full bg-acento/15 flex items-center justify-center shrink-0">
      <span className="font-display text-xs text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

function FlechaTendencia({ tendencia }) {
  if (!tendencia || tendencia === 'igual') {
    return <span className="text-borde text-xs w-4 text-center shrink-0">–</span>
  }
  if (tendencia === 'sube') {
    return <span className="text-acento text-xs w-4 text-center shrink-0" title="Ha subido puesto(s)">▲</span>
  }
  return <span className="text-red-500 text-xs w-4 text-center shrink-0" title="Ha bajado puesto(s)">▼</span>
}

function StandingsPage() {
  const { usuario } = useAuth()

  useTitulo('Clasificación')

  const { data: clasificacion, isLoading, error } = useQuery({
    queryKey: ['clasificacion'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/clasificacion')
      return respuesta.data.data
    },
  })

  if (isLoading) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const puntosLider = clasificacion[0]?.puntos_totales ?? 0

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Clasificación de la liga" />

        {clasificacion.length === 0 ? (
          <EstadoVacio icono="🏆" titulo="Aún no hay puntos" texto="En cuanto se cierre la primera jornada, aparecerá aquí la clasificación." />
        ) : (
          <div>
            <div className="flex items-center gap-2 px-4 py-2 border-b border-borde/10">
              <span className="w-4 shrink-0" />
              <span className="w-8 shrink-0" />
              <span className="flex-1 min-w-0" />
              <span className="font-body text-[9px] uppercase tracking-wider text-borde w-8 text-center">Ac.</span>
              <span className="font-body text-[9px] uppercase tracking-wider text-borde w-8 text-center">Fa.</span>
              <span className="font-body text-[9px] uppercase tracking-wider text-borde w-8 text-center">Ex.</span>
              <span className="font-body text-[9px] uppercase tracking-wider text-borde w-12 text-right">Pts</span>
            </div>

            {clasificacion.map((fila, index) => {
              const resueltos = fila.aciertos + fila.fallos
              const porcentajeAcierto = resueltos > 0 ? Math.round((fila.aciertos / resueltos) * 100) : 0
              const diferenciaLider = puntosLider - fila.puntos_totales

              return (
                <Link
                  key={fila.id_usuario}
                  to={`/clasificacion/usuarios/${fila.id_usuario}`}
                  state={{ nombre: fila.usuario, avatar_url: fila.avatar_url }}
                  className={`flex items-center gap-2 px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5 hover:bg-acento/5 transition ${
                    fila.id_usuario === usuario?.id ? 'bg-acento/5' : ''
                  }`}
                >
                  <FlechaTendencia tendencia={fila.tendencia} />
                  <span className="w-8 shrink-0 font-marcador text-sm text-borde text-center">
                    {MEDALLAS[index] ?? index + 1}
                  </span>
                  <div className="flex items-center gap-2 flex-1 min-w-0">
                    <Avatar url={fila.avatar_url} nombre={fila.usuario} />
                    <div className="min-w-0">
                      <p className="font-body text-base font-medium text-texto truncate">{fila.usuario}</p>
                      <p className="font-body text-[10px] text-borde">
                        {index === 0 ? '🏆 Líder' : `−${diferenciaLider} pts del líder`} · {porcentajeAcierto}% acierto
                      </p>
                    </div>
                  </div>
                  <span className="font-marcador text-sm text-acento w-8 text-center">{fila.aciertos}</span>
                  <span className="font-marcador text-sm text-red-500 w-8 text-center">{fila.fallos}</span>
                  <span className="font-marcador text-sm text-premio w-8 text-center">{fila.exactos}</span>
                  <span className="font-marcador text-sm font-bold text-texto w-12 text-right">{fila.puntos_totales}</span>
                </Link>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}

export default StandingsPage