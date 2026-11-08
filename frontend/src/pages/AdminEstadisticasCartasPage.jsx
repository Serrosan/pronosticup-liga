import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import TicketHeader from '../components/TicketHeader'
import SelectTema from '../components/SelectTema'

// Admin → Cartas → Estadísticas: qué cartas salen, cuáles se juegan, cuáles se
// cumplen y cuántos puntos mueven. Para balances y cartas nuevas con datos.

const RAREZAS = [['Comun', 'Común'], ['PocoComun', 'Poco común'], ['Rara', 'Rara'], ['Legendaria', 'Legendaria']]
const NOMBRE_RAREZA = Object.fromEntries(RAREZAS)
const ORIGENES = { reparto_semanal: 'Reparto semanal', bonus_top3: 'Bonus Top 3', manual: 'Manual / inicial', compensacion: 'Compensación', codigo: 'Códigos' }

const COLUMNAS = [
  ['nombre', 'Carta'], ['salidas', 'Salen'], ['en_mano', 'En mano'], ['jugadas', 'Jugadas'], ['uso', '% uso'],
  ['cumplidas', 'Cumplidas'], ['acierto', '% cumple'], ['puntos', 'Puntos'], ['puntos_media', 'Pts/carta'], ['descartadas', 'Descart.'],
]

function Cifra({ valor, etiqueta }) {
  return (
    <div className="bg-borde/5 border border-borde/20 rounded-lg px-3 py-2">
      <p className="font-marcador text-xl text-texto">{valor}</p>
      <p className="font-body text-[11px] text-borde">{etiqueta}</p>
    </div>
  )
}

// Señales para fijarse: con texto, no solo color.
function avisos(t) {
  const lista = []
  if (t.salidas >= 5 && (t.uso ?? 0) < 20) lista.push('🪦 casi nunca se juega')
  if ((t.cumplidas + t.no_cumplidas) >= 3 && (t.acierto ?? 0) < 25 && t.categoria === 'Jugadas') lista.push('🎯 rara vez se cumple')
  if (t.descartadas >= 2 && t.descartadas >= t.jugadas) lista.push('🗑️ se descarta más que se juega')
  if (!t.activa) lista.push('⛔ retirada')
  return lista
}

export default function AdminEstadisticasCartasPage() {
  const [idLiga, setIdLiga] = useState('')
  const [orden, setOrden] = useState({ campo: 'salidas', desc: true })
  const [categoria, setCategoria] = useState('')

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-estadisticas-cartas', idLiga],
    queryFn: async () => (await client.get('/api/v1/admin/estadisticas-cartas', { params: idLiga ? { id_liga: idLiga } : {} })).data.data,
    placeholderData: (anteriores) => anteriores,
  })

  const tipos = useMemo(() => {
    const lista = (data?.por_tipo ?? []).filter((t) => !categoria || t.categoria === categoria)
    const { campo, desc } = orden
    return [...lista].sort((a, b) => {
      const va = a[campo] ?? -1
      const vb = b[campo] ?? -1
      const r = typeof va === 'string' ? va.localeCompare(vb) : va - vb
      return desc ? -r : r
    })
  }, [data, orden, categoria])

  if (error) return <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>
  if (isLoading || !data) return <p className="font-body text-sm text-borde">Cargando…</p>
  if (!data.liga) return <p className="font-body text-sm text-borde">No hay ninguna liga con cartas.</p>

  const r = data.resumen
  const cabecera = (campo, etiqueta) => (
    <th key={campo} className="px-2 py-2 text-left whitespace-nowrap">
      <button onClick={() => setOrden((o) => ({ campo, desc: o.campo === campo ? !o.desc : true }))} className="font-body text-[11px] uppercase tracking-wide text-borde hover:text-texto">
        {etiqueta}{orden.campo === campo ? (orden.desc ? ' ▼' : ' ▲') : ''}
      </button>
    </th>
  )

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl text-texto">📊 Estadísticas de cartas</h1>
          <p className="font-body text-sm text-borde">Qué sale, qué se juega, qué se cumple y cuántos puntos mueve cada carta.</p>
        </div>
        {data.ligas.length > 1 && (
          <SelectTema value={String(data.liga.id)} onChange={(e) => setIdLiga(e.target.value)} options={data.ligas.map((l) => ({ value: String(l.id), label: l.nombre }))} className="bg-fondo" />
        )}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
        <Cifra valor={r.salidas} etiqueta="cartas repartidas" />
        <Cifra valor={r.en_mano} etiqueta="en las manos ahora" />
        <Cifra valor={r.jugadas} etiqueta="jugadas" />
        <Cifra valor={r.descartadas} etiqueta="descartadas" />
        <Cifra valor={r.puntos} etiqueta="puntos movidos" />
        <Cifra valor={r.jornadas} etiqueta="jornadas con cartas" />
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Rarezas: configurado frente a lo que ha salido" />
        <div className="overflow-x-auto">
          <table className="w-full font-body text-sm">
            <thead><tr className="border-b border-borde/20">
              <th className="px-3 py-2 text-left font-body text-[11px] uppercase tracking-wide text-borde">Origen</th>
              {RAREZAS.map(([, n]) => <th key={n} className="px-3 py-2 text-right font-body text-[11px] uppercase tracking-wide text-borde whitespace-nowrap">{n}</th>)}
              <th className="px-3 py-2 text-right font-body text-[11px] uppercase tracking-wide text-borde">Total</th>
            </tr></thead>
            <tbody>
              <tr className="border-b border-borde/10 bg-acento/5">
                <td className="px-3 py-2 text-texto font-semibold">⚙️ Configurado (reparto semanal)</td>
                {RAREZAS.map(([k]) => <td key={k} className="px-3 py-2 text-right text-texto">{data.configurado[k]} %</td>)}
                <td />
              </tr>
              {data.por_origen.map((o) => (
                <tr key={o.origen} className="border-b border-borde/10">
                  <td className="px-3 py-2 text-texto">{ORIGENES[o.origen] ?? o.origen}</td>
                  {RAREZAS.map(([k]) => <td key={k} className="px-3 py-2 text-right text-borde whitespace-nowrap"><span className="text-texto">{o.reparto[k].pct} %</span> ({o.reparto[k].n})</td>)}
                  <td className="px-3 py-2 text-right text-texto">{o.total}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="font-body text-[11px] text-borde px-3 py-2">Con pocas cartas repartidas, lo real se aleja de lo configurado por pura suerte: fíjate en la tendencia.</p>
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Carta a carta" />
        <div className="px-3 py-2 flex flex-wrap gap-2 border-b border-borde/10">
          {['', 'Jugadas', 'Faltas'].map((c) => (
            <button key={c || 'todas'} onClick={() => setCategoria(c)} aria-pressed={categoria === c}
              className={`font-body text-xs rounded-full px-3 py-1 border ${categoria === c ? 'bg-acento text-fondo border-acento font-semibold' : 'text-texto border-borde/40'}`}>
              {categoria === c ? '✓ ' : ''}{c || 'Todas'}
            </button>
          ))}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full font-body text-sm">
            <thead><tr className="border-b border-borde/20">{COLUMNAS.map(([c, e]) => cabecera(c, e))}</tr></thead>
            <tbody>
              {tipos.map((t) => {
                const senales = avisos(t)
                return (
                  <tr key={t.id} className={`border-b border-borde/10 ${t.activa ? '' : 'opacity-60'}`}>
                    <td className="px-2 py-2 min-w-[12rem]">
                      <p className="text-texto font-semibold">{t.nombre}</p>
                      <p className="text-[11px] text-borde">{t.categoria} · {NOMBRE_RAREZA[t.rareza] ?? t.rareza}</p>
                      {senales.length > 0 && <p className="text-[11px] text-premio">{senales.join(' · ')}</p>}
                    </td>
                    <td className="px-2 py-2 text-texto">{t.salidas}</td>
                    <td className="px-2 py-2 text-borde">{t.en_mano}</td>
                    <td className="px-2 py-2 text-texto">{t.jugadas}{t.pendientes ? <span className="text-borde"> ({t.pendientes} pend.)</span> : ''}</td>
                    <td className="px-2 py-2 text-texto">{t.uso ?? '—'}{t.uso !== null ? ' %' : ''}</td>
                    <td className="px-2 py-2 text-texto">{t.cumplidas}/{t.cumplidas + t.no_cumplidas}</td>
                    <td className="px-2 py-2 text-texto">{t.acierto ?? '—'}{t.acierto !== null ? ' %' : ''}</td>
                    <td className="px-2 py-2 text-texto">{t.puntos}</td>
                    <td className="px-2 py-2 text-texto">{t.puntos_media ?? '—'}</td>
                    <td className="px-2 py-2 text-borde">{t.descartadas}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        <p className="font-body text-[11px] text-borde px-3 py-2">
          % uso = jugadas de las que han salido. % cumple = de las ya resueltas, cuántas hicieron su efecto (en las Faltas, que se aplicó). Pts/carta = puntos medios por carta resuelta.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <TicketHeader titulo="Activas que nunca han salido" />
          {data.nunca_salidas.length === 0 ? <p className="font-body text-sm text-borde p-3">Han salido todas al menos una vez.</p> : (
            <ul className="p-3 flex flex-col gap-1">
              {data.nunca_salidas.map((t) => <li key={t.codigo} className="font-body text-sm text-texto">{t.nombre} <span className="text-borde text-xs">· {t.categoria} · {NOMBRE_RAREZA[t.rareza] ?? t.rareza}</span></li>)}
            </ul>
          )}
        </div>
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
          <TicketHeader titulo="Cartas jugadas por jornada" />
          <table className="w-full font-body text-sm">
            <thead><tr className="border-b border-borde/20">
              {['Jornada', 'Jugadas', 'Faltas', 'Puntos'].map((e) => <th key={e} className="px-3 py-2 text-left font-body text-[11px] uppercase tracking-wide text-borde">{e}</th>)}
            </tr></thead>
            <tbody>
              {data.por_jornada.map((j) => (
                <tr key={j.jornada} className="border-b border-borde/10">
                  <td className="px-3 py-1.5 text-texto">J{j.jornada}</td>
                  <td className="px-3 py-1.5 text-texto">{j.jugadas}</td>
                  <td className="px-3 py-1.5 text-texto">{j.faltas}</td>
                  <td className="px-3 py-1.5 text-texto">{j.puntos}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
