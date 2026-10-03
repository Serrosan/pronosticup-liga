import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'
import EstadoVacio from '../components/EstadoVacio'

function agruparPorPartido(avisos) {
  const grupos = {}
  avisos.forEach((a) => {
    if (!grupos[a.partido]) grupos[a.partido] = []
    grupos[a.partido].push(a.mensaje)
  })
  return grupos
}

function Ejecucion({ ejecucion, abiertaPorDefecto }) {
  const [abierta, setAbierta] = useState(abiertaPorDefecto)
  const grupos = agruparPorPartido(ejecucion.avisos)

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
      <button
        onClick={() => setAbierta(!abierta)}
        className="w-full flex items-center justify-between px-4 py-3 bg-borde/10 hover:bg-borde/15 transition text-left"
      >
        <span className="font-body text-sm text-texto">{ejecucion.cabecera}</span>
        <div className="flex items-center gap-3 shrink-0">
          <span className="font-marcador text-xs font-bold text-premio bg-premio/10 rounded-full px-2.5 py-1">
            {ejecucion.total_avisos} aviso{ejecucion.total_avisos > 1 ? 's' : ''}
          </span>
          <span className="text-borde text-xs">{abierta ? '▲' : '▼'}</span>
        </div>
      </button>

      {abierta && (
        <div className="divide-y divide-borde/10">
          {Object.entries(grupos).map(([partido, mensajes]) => (
            <div key={partido} className="px-4 py-3">
              <p className="font-body text-sm font-semibold text-texto mb-1.5">{partido}</p>
              <ul className="flex flex-col gap-1">
                {mensajes.map((m, i) => (
                  <li key={i} className="font-body text-xs text-borde flex items-start gap-1.5">
                    <span className="shrink-0">⚠️</span>
                    <span>{m}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function AdminAvisosScraperPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-avisos-scraper'],
    queryFn: async () => (await client.get('/api/v1/admin/avisos-scraper')).data.data,
  })

  if (isLoading) return <div className="max-w-3xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Avisos del scraper de LaLiga.com" />
        <div className="px-5 py-4">
          <p className="font-body text-sm text-borde">
            Jugadores sin emparejar, colisiones y tipos de evento desconocidos de cada ejecución — para revisar con calma y decidir qué merece investigarse de verdad.
          </p>
        </div>
      </div>

      {data.length === 0 ? (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <EstadoVacio icono="✅" titulo="Sin avisos" texto="Ninguna ejecución del scraper ha dejado avisos pendientes." />
        </div>
      ) : (
        data.map((ejecucion, i) => <Ejecucion key={i} ejecucion={ejecucion} abiertaPorDefecto={i === 0} />)
      )}
    </div>
  )
}

export default AdminAvisosScraperPage