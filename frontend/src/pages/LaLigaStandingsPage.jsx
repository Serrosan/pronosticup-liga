import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'

function Escudo({ url, alt }) {
  if (!url) return <span className="w-7 h-7 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-7 h-7 object-contain shrink-0" />
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
]

function LaLigaStandingsPage() {
  const [vista, setVista] = useState('general')

  const { data, isLoading, error } = useQuery({
    queryKey: ['clasificacion-liga'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/clasificacion-liga')
      return respuesta.data.data
    },
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista filas={20} /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const tabla = data[vista]

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="flex gap-2 mb-4">
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

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo={`Clasificación de LaLiga — ${VISTAS.find((v) => v.key === vista).label}`} />

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
        </div>

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
    </div>
  )
}

export default LaLigaStandingsPage