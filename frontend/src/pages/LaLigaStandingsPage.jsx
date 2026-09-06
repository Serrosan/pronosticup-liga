import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function FotoJugador({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-8 h-8 rounded-full object-cover shrink-0" />
  return (
    <span className="w-8 h-8 rounded-full bg-acento/10 border border-acento/20 flex items-center justify-center text-xs font-semibold shrink-0 text-acento">
      {nombre?.[0]}
    </span>
  )
}

const COLOR_FORMA = { G: 'bg-acento', E: 'bg-borde', P: 'bg-red-500' }

function Forma({ resultados }) {
  return (
    <div className="flex gap-1 justify-center">
      {resultados.map((r, i) => (
        <span key={i} className={`w-2.5 h-2.5 rounded-full ${COLOR_FORMA[r]}`} title={r === 'G' ? 'Ganado' : r === 'E' ? 'Empate' : 'Perdido'} />
      ))}
    </div>
  )
}

function bandaZona(posicion) {
  if (posicion <= 4) return 'border-l-4 border-l-blue-400'
  if (posicion <= 6) return 'border-l-4 border-l-premio'
  if (posicion >= 18) return 'border-l-4 border-l-red-500'
  return 'border-l-4 border-l-transparent'
}

const VISTAS = [
  { key: 'general', label: 'General' },
  { key: 'casa', label: 'En casa' },
  { key: 'fuera', label: 'Fuera' },
  { key: 'goleadores', label: '⚽ Goleadores' },
  { key: 'asistencias', label: '🎯 Asistencias' },
  { key: 'tarjetas', label: '🟨 Tarjetas' },
]

function TablaEquipos({ tabla }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-borde/20">
            <th className="font-body text-xs uppercase tracking-wider text-borde px-3 py-3 w-8">#</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-3 py-3">Equipo</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center">PJ</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center">G</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center">E</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center">P</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center hidden sm:table-cell">GF</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center hidden sm:table-cell">GC</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-2 py-3 text-center">DG</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-3 py-3 text-center hidden md:table-cell">Forma</th>
            <th className="font-body text-xs uppercase tracking-wider text-borde px-3 py-3 text-right">Pts</th>
          </tr>
        </thead>
        <tbody>
          {tabla.map((equipo, index) => (
            <tr key={equipo.id} className={`border-b border-borde/10 last:border-b-0 odd:bg-borde/5 ${bandaZona(index + 1)}`}>
              <td className="font-marcador text-sm text-borde px-3 py-3">{index + 1}</td>
              <td className="px-3 py-3">
                <div className="flex items-center gap-2 min-w-0">
                  <Escudo url={equipo.escudo_url} alt={equipo.nombre} />
                  <p className="font-body text-base font-medium text-texto truncate">{equipo.nombre}</p>
                </div>
              </td>
              <td className="font-marcador text-sm text-texto px-2 py-3 text-center">{equipo.pj}</td>
              <td className="font-marcador text-sm text-acento px-2 py-3 text-center">{equipo.pg}</td>
              <td className="font-marcador text-sm text-borde px-2 py-3 text-center">{equipo.pe}</td>
              <td className="font-marcador text-sm text-red-500 px-2 py-3 text-center">{equipo.pp}</td>
              <td className="font-marcador text-sm text-texto px-2 py-3 text-center hidden sm:table-cell">{equipo.gf}</td>
              <td className="font-marcador text-sm text-texto px-2 py-3 text-center hidden sm:table-cell">{equipo.gc}</td>
              <td className="font-marcador text-sm text-texto px-2 py-3 text-center">{equipo.dg > 0 ? `+${equipo.dg}` : equipo.dg}</td>
              <td className="px-3 py-3 hidden md:table-cell"><Forma resultados={equipo.forma} /></td>
              <td className="font-marcador text-lg font-bold text-texto px-3 py-3 text-right">{equipo.pts}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="flex flex-wrap gap-4 px-4 py-3 border-t border-borde/20">
        <span className="flex items-center gap-1.5 font-body text-xs text-borde">
          <span className="w-3 h-3 rounded-sm bg-blue-400" /> Champions League
        </span>
        <span className="flex items-center gap-1.5 font-body text-xs text-borde">
          <span className="w-3 h-3 rounded-sm bg-premio" /> Europa League
        </span>
        <span className="flex items-center gap-1.5 font-body text-xs text-borde">
          <span className="w-3 h-3 rounded-sm bg-red-500" /> Descenso
        </span>
      </div>
    </div>
  )
}

function TablaGoleadores({ jugadores, campo, etiqueta }) {
  if (jugadores.length === 0) {
    return <p className="font-body text-sm text-borde text-center py-12">Aún no hay datos suficientes de eventos de partido.</p>
  }

  return (
    <div className="divide-y divide-borde/10">
      {jugadores.map((j, index) => (
        <div key={j.id} className="flex items-center gap-3 px-4 py-2.5 odd:bg-borde/5">
          <span className="font-marcador text-sm text-borde w-6 text-center shrink-0">{index + 1}</span>
          <FotoJugador url={j.foto_url} nombre={j.nombre} />
          <div className="min-w-0 flex-1">
            <p className="font-body text-sm font-medium text-texto truncate">{j.nombre}</p>
            <div className="flex items-center gap-1.5">
              {j.escudo_url && <img src={j.escudo_url} alt={j.equipo} className="w-3.5 h-3.5 object-contain" />}
              <p className="font-body text-[11px] text-borde truncate">{j.equipo}</p>
            </div>
          </div>
          <span className="font-marcador text-lg font-bold text-acento shrink-0">{j[campo]}</span>
          <span className="font-body text-[10px] text-borde shrink-0 w-16 text-right">{etiqueta}</span>
        </div>
      ))}
    </div>
  )
}

function TablaTarjetas({ jugadores }) {
  if (jugadores.length === 0) {
    return <p className="font-body text-sm text-borde text-center py-12">Aún no hay datos suficientes de eventos de partido.</p>
  }

  return (
    <div className="divide-y divide-borde/10">
      {jugadores.map((j, index) => (
        <div key={j.id} className="flex items-center gap-3 px-4 py-2.5 odd:bg-borde/5">
          <span className="font-marcador text-sm text-borde w-6 text-center shrink-0">{index + 1}</span>
          <FotoJugador url={j.foto_url} nombre={j.nombre} />
          <div className="min-w-0 flex-1">
            <p className="font-body text-sm font-medium text-texto truncate">{j.nombre}</p>
            <div className="flex items-center gap-1.5">
              {j.escudo_url && <img src={j.escudo_url} alt={j.equipo} className="w-3.5 h-3.5 object-contain" />}
              <p className="font-body text-[11px] text-borde truncate">{j.equipo}</p>
            </div>
          </div>
          <div className="flex items-center gap-3 shrink-0">
            <span className="flex items-center gap-1 font-marcador text-sm font-bold text-yellow-500">
              🟨 {j.amarillas}
            </span>
            <span className="flex items-center gap-1 font-marcador text-sm font-bold text-red-500">
              🟥 {j.rojas}
            </span>
          </div>
        </div>
      ))}
    </div>
  )
}

function LaLigaStandingsPage() {
  const [vista, setVista] = useState('general')
  const esVistaDeEquipos = ['general', 'casa', 'fuera'].includes(vista)

  const { data, isLoading, error } = useQuery({
    queryKey: ['clasificacion-liga'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/clasificacion-liga')
      return { tablas: respuesta.data.data, ultimaActualizacion: respuesta.data.meta?.ultima_actualizacion }
    },
    enabled: esVistaDeEquipos,
  })

  const { data: goleadores, isLoading: cargandoGoleadores } = useQuery({
    queryKey: ['estadisticas-jugadores', 'goleadores'],
    queryFn: async () => (await client.get('/api/v1/estadisticas-jugadores/goleadores')).data.data,
    enabled: vista === 'goleadores',
  })

  const { data: asistencias, isLoading: cargandoAsistencias } = useQuery({
    queryKey: ['estadisticas-jugadores', 'asistencias'],
    queryFn: async () => (await client.get('/api/v1/estadisticas-jugadores/asistencias')).data.data,
    enabled: vista === 'asistencias',
  })

  const { data: tarjetas, isLoading: cargandoTarjetas } = useQuery({
    queryKey: ['estadisticas-jugadores', 'tarjetas'],
    queryFn: async () => (await client.get('/api/v1/estadisticas-jugadores/tarjetas')).data.data,
    enabled: vista === 'tarjetas',
  })

  const minutosDesdeActualizacion = data?.ultimaActualizacion
    ? Math.max(0, Math.round((Date.now() - new Date(data.ultimaActualizacion)) / 60000))
    : null

  const tituloVista = VISTAS.find((v) => v.key === vista)?.label

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div className="flex gap-2 flex-wrap">
          {VISTAS.map((v) => (
            <button
              key={v.key}
              onClick={() => setVista(v.key)}
              className={`font-body text-sm px-3 py-1.5 rounded-full transition ${
                vista === v.key ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
              }`}
            >
              {v.label}
            </button>
          ))}
        </div>
        {esVistaDeEquipos && minutosDesdeActualizacion !== null && (
          <p className="font-body text-[11px] text-borde">Actualizado hace {minutosDesdeActualizacion} min</p>
        )}
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo={`LaLiga — ${tituloVista}`} />

        {esVistaDeEquipos && (
          isLoading || !data ? (
            <div className="p-4"><SkeletonLista filas={20} /></div>
          ) : error ? (
            <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
          ) : (
            <TablaEquipos tabla={data.tablas[vista]} />
          )
        )}

        {vista === 'goleadores' && (
          cargandoGoleadores ? <div className="p-4"><SkeletonLista /></div> :
          <TablaGoleadores jugadores={goleadores ?? []} campo="goles" etiqueta="goles" />
        )}

        {vista === 'asistencias' && (
          cargandoAsistencias ? <div className="p-4"><SkeletonLista /></div> :
          <TablaGoleadores jugadores={asistencias ?? []} campo="asistencias" etiqueta="asist." />
        )}

        {vista === 'tarjetas' && (
          cargandoTarjetas ? <div className="p-4"><SkeletonLista /></div> :
          <TablaTarjetas jugadores={tarjetas ?? []} />
        )}
      </div>
    </div>
  )
}

export default LaLigaStandingsPage