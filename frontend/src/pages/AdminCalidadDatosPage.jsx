import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SkeletonLista from '../components/SkeletonLista'

// Gravedad: siempre icono + palabra, el color solo acompaña.
const GRAVEDAD = {
  error: { icono: '✗', texto: 'Error', clase: 'text-red-400', borde: 'border-l-red-400' },
  aviso: { icono: '!', texto: 'Aviso', clase: 'text-premio', borde: 'border-l-premio' },
  incompleto: { icono: '…', texto: 'Le falta', clase: 'text-borde', borde: 'border-l-borde/40' },
}

const SECCIONES = [
  { gravedad: 'error', titulo: 'Errores', texto: 'Pueden hacer que el importador no empareje bien o que una plantilla salga mal.' },
  { gravedad: 'aviso', titulo: 'Avisos', texto: 'No rompen nada, pero conviene revisarlos.' },
  { gravedad: 'incompleto', titulo: 'Datos que faltan', texto: 'Solo afectan a cómo se ve la app.' },
]

function Revision({ revision }) {
  const [abierta, setAbierta] = useState(false)
  const g = GRAVEDAD[revision.gravedad]
  const limpia = revision.total === 0

  return (
    <div className={`border-b border-borde/10 last:border-0 border-l-4 ${limpia ? 'border-l-acento' : g.borde}`}>
      <button
        onClick={() => !limpia && setAbierta((v) => !v)}
        className={`w-full text-left px-4 py-3 flex items-start justify-between gap-3 ${limpia ? 'cursor-default' : 'hover:bg-borde/5'}`}
      >
        <div className="min-w-0">
          <p className="font-body text-sm font-semibold text-texto">
            {!limpia && <span className="text-borde text-xs mr-1.5">{abierta ? '▾' : '▸'}</span>}
            {revision.titulo}
          </p>
          {revision.explicacion && <p className="font-body text-xs text-borde mt-0.5">{revision.explicacion}</p>}
        </div>
        <span className={`font-body text-xs font-semibold whitespace-nowrap ${limpia ? 'text-acento' : g.clase}`}>
          {limpia ? '✓ Ninguno' : `${g.icono} ${revision.total}`}
        </span>
      </button>

      {abierta && (
        <div className="pb-2">
          {revision.filas.map((f, i) => (
            <Link
              key={`${f.id_jugador}-${i}`}
              to={`/admin/jugadores/detalle/${f.id_jugador}`}
              className="flex items-start justify-between gap-3 px-4 py-2 ml-4 border-t border-borde/10 hover:bg-borde/5"
            >
              <div className="min-w-0">
                <p className="font-body text-sm text-texto">{f.nombre}</p>
                <p className="font-body text-[11px] text-borde">{f.detalle}</p>
              </div>
              {f.equipo && <span className="font-body text-[11px] text-borde whitespace-nowrap">{f.equipo}</span>}
            </Link>
          ))}
          {revision.total > revision.filas.length && (
            <p className="font-body text-[11px] text-borde px-4 py-2 ml-4">
              Y {revision.total - revision.filas.length} más. Arregla estos y vuelve a mirar.
            </p>
          )}
        </div>
      )}
    </div>
  )
}

function AdminCalidadDatosPage() {
  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['admin-calidad-datos'],
    queryFn: async () => (await client.get('/api/v1/admin/calidad-datos')).data.data,
  })

  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>
  if (isLoading || !data) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>

  const errores = data.revisiones.filter((r) => r.gravedad === 'error').reduce((s, r) => s + r.total, 0)

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-6">
        <TicketHeader titulo="Calidad de datos" />
        <div className="px-4 py-4 flex items-start justify-between gap-3 flex-wrap">
          <div className="min-w-0">
            <p className="font-body text-sm text-borde">
              Lo raro en jugadores y plantillas de esta temporada ({data.jugadores_activos} jugadores en activo). Solo enseña:
              pulsa un jugador para ir a su ficha y arreglarlo.
            </p>
            <p className={`font-body text-sm font-semibold mt-2 ${errores ? 'text-red-400' : 'text-acento'}`}>
              {errores ? `✗ ${errores} error(es) por revisar` : '✓ Ningún error'}
            </p>
            <p className="font-body text-[11px] text-borde mt-1">
              Lo que LaLiga trae distinto al importar está en{' '}
              <Link to="/admin/cambios-plantilla" className="text-acento hover:underline">Cambios de plantilla</Link>.
            </p>
          </div>
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="font-body text-xs text-texto border border-borde/40 rounded px-3 py-1.5 hover:bg-borde/10 disabled:opacity-40"
          >
            {isFetching ? 'Mirando…' : 'Volver a mirar'}
          </button>
        </div>
      </div>

      {SECCIONES.map((s) => (
        <div key={s.gravedad} className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
          <div className="px-4 py-2.5 bg-borde/10">
            <p className="font-body text-sm font-semibold text-texto">{s.titulo}</p>
            <p className="font-body text-[11px] text-borde">{s.texto}</p>
          </div>
          {data.revisiones.filter((r) => r.gravedad === s.gravedad).map((r) => <Revision key={r.clave} revision={r} />)}
        </div>
      ))}
    </div>
  )
}

export default AdminCalidadDatosPage
