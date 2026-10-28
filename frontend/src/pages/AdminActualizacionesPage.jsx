import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import { Proximamente, EntradaHistorial, fechaLarga } from '../components/VistaActualizaciones'

// Admin → Actualizaciones: lo que ven los jugadores en 📜 Actualizaciones.
// - "Próximamente": cada punto tiene qué cambia, antes, después y por qué.
// - "Ya hecho": cada punto es una frase.
// Lo que no está publicado (borrador) no lo ve nadie más.

const CAMBIO_VACIO = { carta: '', antes: '', despues: '', porque: '' }

function hoy() {
  const d = new Date()
  const dos = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
}

function entradaNueva(tipo) {
  return {
    id: null,
    tipo,
    fecha: hoy(),
    emoji: tipo === 'proximamente' ? '⚖️' : '✨',
    titulo: '',
    intro: '',
    entra_en_vigor: tipo === 'proximamente' ? 'Fecha por confirmar' : '',
    puntos: tipo === 'proximamente' ? [{ ...CAMBIO_VACIO }] : [''],
    notas: [],
    publicada: false,
  }
}

// Al pasar un "Próximamente" a "Ya hecho", cada cambio se convierte en una frase.
function aFrases(puntos) {
  return puntos.map((p) => {
    if (typeof p === 'string') return p
    const cambio = p.antes || p.despues ? `: ${p.antes || '—'} → ${p.despues || '—'}` : ''
    return `${p.carta}${cambio}`
  })
}

function mover(lista, i, delta) {
  const j = i + delta
  if (j < 0 || j >= lista.length) return lista
  const copia = [...lista]
  ;[copia[i], copia[j]] = [copia[j], copia[i]]
  return copia
}

const CAMPO = 'w-full font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento'
const BOTON_MINI = 'font-body text-xs text-borde hover:text-texto border border-borde/30 rounded px-2 py-1 disabled:opacity-30'

function BotonesFila({ i, total, onMover, onQuitar }) {
  return (
    <div className="flex gap-1 shrink-0">
      <button type="button" className={BOTON_MINI} onClick={() => onMover(i, -1)} disabled={i === 0} aria-label="Subir">↑</button>
      <button type="button" className={BOTON_MINI} onClick={() => onMover(i, 1)} disabled={i === total - 1} aria-label="Bajar">↓</button>
      <button type="button" className={`${BOTON_MINI} hover:text-red-500`} onClick={() => onQuitar(i)} aria-label="Quitar">✕</button>
    </div>
  )
}

function Editor({ inicial, onGuardado, onBorrado }) {
  const toast = useToast()
  const [e, setE] = useState(inicial)
  const [errores, setErrores] = useState(null)
  const esProx = e.tipo === 'proximamente'

  const cambiar = (campo, valor) => setE((x) => ({ ...x, [campo]: valor }))
  const cambiarPunto = (i, valor) => setE((x) => ({ ...x, puntos: x.puntos.map((p, k) => (k === i ? valor : p)) }))
  const quitarPunto = (i) => setE((x) => ({ ...x, puntos: x.puntos.filter((_, k) => k !== i) }))
  const moverPunto = (i, d) => setE((x) => ({ ...x, puntos: mover(x.puntos, i, d) }))
  const anadirPunto = () => setE((x) => ({ ...x, puntos: [...x.puntos, esProx ? { ...CAMBIO_VACIO } : ''] }))
  const cambiarNota = (i, valor) => setE((x) => ({ ...x, notas: x.notas.map((n, k) => (k === i ? valor : n)) }))

  const guardar = useMutation({
    mutationFn: (datos) => (datos.id
      ? client.put(`/api/v1/admin/actualizaciones/${datos.id}`, datos)
      : client.post('/api/v1/admin/actualizaciones', datos)),
    onSuccess: (r) => {
      setErrores(null)
      setE(r.data.data)
      toast.exito(r.data.message ?? 'Guardado.')
      onGuardado(r.data.data)
    },
    onError: (err) => {
      setErrores(err.response?.data?.errors ? Object.values(err.response.data.errors).flat() : null)
      toast.error(err.response?.data?.message ?? 'No se pudo guardar.')
    },
  })

  const borrar = useMutation({
    mutationFn: () => client.delete(`/api/v1/admin/actualizaciones/${e.id}`),
    onSuccess: () => {
      toast.exito('Entrada borrada.')
      onBorrado()
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo borrar.'),
  })

  function guardarCon(extra = {}) {
    guardar.mutate({ ...e, ...extra })
  }

  function pasarAHecho() {
    if (!window.confirm('¿Pasar esta entrada a "Ya hecho"? Cada cambio se convierte en una frase (lo puedes retocar) y la fecha pasa a hoy.')) return
    setE((x) => ({ ...x, tipo: 'hecho', fecha: hoy(), entra_en_vigor: '', puntos: aFrases(x.puntos) }))
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center gap-2">
        <span className={`font-body text-xs font-semibold rounded-full px-3 py-1 border ${esProx ? 'border-premio text-premio' : 'border-acento text-acento'}`}>
          {esProx ? '🔜 Próximamente' : '✓ Ya hecho'}
        </span>
        <span className={`font-body text-xs font-semibold rounded-full px-3 py-1 border ${e.publicada ? 'border-acento text-acento' : 'border-dashed border-borde text-borde'}`}>
          {e.publicada ? '👁 Publicada' : '✎ Borrador (solo tú)'}
        </span>
        {esProx && (
          <button type="button" onClick={pasarAHecho} className="font-body text-xs text-texto underline hover:no-underline ml-auto">
            Ya ha entrado en vigor → pasar a "Ya hecho"
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-[6rem_1fr] gap-3">
        <label className="flex flex-col gap-1">
          <span className="font-body text-xs text-borde">Emoji</span>
          <input value={e.emoji ?? ''} maxLength={16} onChange={(ev) => cambiar('emoji', ev.target.value)} className={CAMPO} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="font-body text-xs text-borde">Título</span>
          <input value={e.titulo} maxLength={120} onChange={(ev) => cambiar('titulo', ev.target.value)} className={CAMPO} placeholder="Primer balance de las cartas" />
        </label>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label className="flex flex-col gap-1">
          <span className="font-body text-xs text-borde">{esProx ? 'Anunciado el' : 'Fecha'}</span>
          <input type="date" value={String(e.fecha).slice(0, 10)} onChange={(ev) => cambiar('fecha', ev.target.value)} className={CAMPO} />
        </label>
        {esProx && (
          <label className="flex flex-col gap-1">
            <span className="font-body text-xs text-borde">Entra en vigor</span>
            <input value={e.entra_en_vigor ?? ''} maxLength={120} onChange={(ev) => cambiar('entra_en_vigor', ev.target.value)} className={CAMPO} placeholder="Tras cerrar la jornada 11" />
          </label>
        )}
      </div>

      <label className="flex flex-col gap-1">
        <span className="font-body text-xs text-borde">Introducción (opcional)</span>
        <textarea value={e.intro ?? ''} maxLength={2000} rows={3} onChange={(ev) => cambiar('intro', ev.target.value)} className={CAMPO} />
      </label>

      <div className="flex flex-col gap-2">
        <span className="font-body text-xs text-borde">{esProx ? 'Cambios' : 'Puntos'}</span>
        {e.puntos.map((p, i) => (esProx ? (
          <div key={i} className="border border-borde/25 rounded-lg p-3 flex flex-col gap-2">
            <div className="flex gap-2 items-start">
              <input value={p.carta ?? ''} maxLength={120} onChange={(ev) => cambiarPunto(i, { ...p, carta: ev.target.value })} className={CAMPO} placeholder="Qué cambia (p. ej. Visita Obligada)" />
              <BotonesFila i={i} total={e.puntos.length} onMover={moverPunto} onQuitar={quitarPunto} />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <input value={p.antes ?? ''} maxLength={500} onChange={(ev) => cambiarPunto(i, { ...p, antes: ev.target.value })} className={CAMPO} placeholder="Antes" />
              <input value={p.despues ?? ''} maxLength={500} onChange={(ev) => cambiarPunto(i, { ...p, despues: ev.target.value })} className={CAMPO} placeholder="Después" />
            </div>
            <input value={p.porque ?? ''} maxLength={500} onChange={(ev) => cambiarPunto(i, { ...p, porque: ev.target.value })} className={CAMPO} placeholder="Por qué (opcional)" />
          </div>
        ) : (
          <div key={i} className="flex gap-2 items-start">
            <textarea value={p} maxLength={500} rows={2} onChange={(ev) => cambiarPunto(i, ev.target.value)} className={CAMPO} />
            <BotonesFila i={i} total={e.puntos.length} onMover={moverPunto} onQuitar={quitarPunto} />
          </div>
        )))}
        <button type="button" onClick={anadirPunto} className="self-start font-body text-xs text-acento hover:underline">＋ Añadir {esProx ? 'cambio' : 'punto'}</button>
      </div>

      <div className="flex flex-col gap-2">
        <span className="font-body text-xs text-borde">Notas al pie (opcional)</span>
        {e.notas.map((n, i) => (
          <div key={i} className="flex gap-2 items-start">
            <input value={n} maxLength={500} onChange={(ev) => cambiarNota(i, ev.target.value)} className={CAMPO} />
            <BotonesFila i={i} total={e.notas.length} onMover={(k, d) => cambiar('notas', mover(e.notas, k, d))} onQuitar={(k) => cambiar('notas', e.notas.filter((_, j) => j !== k))} />
          </div>
        ))}
        <button type="button" onClick={() => cambiar('notas', [...e.notas, ''])} className="self-start font-body text-xs text-acento hover:underline">＋ Añadir nota</button>
      </div>

      {errores && (
        <ul className="font-body text-xs text-red-500 bg-red-500/10 rounded px-3 py-2">
          {errores.map((m, i) => <li key={i}>⚠️ {m}</li>)}
        </ul>
      )}

      <div className="flex flex-wrap gap-2 border-t border-borde/20 pt-4">
        <button type="button" disabled={guardar.isPending} onClick={() => guardarCon()} className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50">
          {guardar.isPending ? 'Guardando…' : '💾 Guardar'}
        </button>
        {e.publicada ? (
          <button type="button" disabled={guardar.isPending} onClick={() => guardarCon({ publicada: false })} className="font-body text-sm text-texto border border-borde/40 rounded px-4 py-2 hover:bg-borde/10 disabled:opacity-50">
            Retirar (pasar a borrador)
          </button>
        ) : (
          <button
            type="button"
            disabled={guardar.isPending}
            onClick={() => window.confirm('¿Publicarla? La verán todos y les saldrá "NUEVO" en 📜.') && guardarCon({ publicada: true })}
            className="font-body text-sm font-semibold text-fondo bg-premio rounded px-4 py-2 hover:brightness-110 disabled:opacity-50"
          >
            👁 Guardar y publicar
          </button>
        )}
        {e.id && (
          <button
            type="button"
            disabled={borrar.isPending}
            onClick={() => window.confirm('¿Borrar esta entrada? No se puede deshacer.') && borrar.mutate()}
            className="font-body text-sm text-red-500 hover:underline ml-auto"
          >
            ✕ Borrar
          </button>
        )}
      </div>

      <div>
        <p className="font-body text-[11px] uppercase tracking-widest text-borde mb-2">Vista previa (así la verán los jugadores)</p>
        <div className="max-w-xl">
          {esProx ? (
            <Proximamente entrada={e} />
          ) : (
            <div className="bg-fondo border border-borde/30 rounded-lg p-4">
              <ul><EntradaHistorial entrada={e} /></ul>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default function AdminActualizacionesPage() {
  const queryClient = useQueryClient()
  const [editando, setEditando] = useState(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-actualizaciones'],
    queryFn: async () => (await client.get('/api/v1/admin/actualizaciones')).data.data,
  })

  function refrescar() {
    queryClient.invalidateQueries({ queryKey: ['admin-actualizaciones'] })
    queryClient.invalidateQueries({ queryKey: ['actualizaciones'] })
  }

  if (isLoading) return <p className="font-body text-sm text-borde">Cargando…</p>
  if (error) return <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
          <h1 className="font-display text-2xl text-texto mb-1">📜 Actualizaciones</h1>
          <p className="font-body text-sm text-borde">
            Lo que ven los jugadores en <Link to="/actualizaciones" className="text-acento hover:underline">📜 Actualizaciones</Link>. Los borradores solo los ves tú.
          </p>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={() => setEditando(entradaNueva('proximamente'))} className="font-body text-sm text-texto border border-premio/60 rounded px-3 py-2 hover:bg-premio/10">＋ Próximamente</button>
          <button type="button" onClick={() => setEditando(entradaNueva('hecho'))} className="font-body text-sm text-texto border border-acento/60 rounded px-3 py-2 hover:bg-acento/10">＋ Ya hecho</button>
        </div>
      </div>

      {editando ? (
        <div className="bg-fondo border border-borde/30 rounded-lg p-4 mb-6">
          <button type="button" onClick={() => setEditando(null)} className="font-body text-xs text-borde hover:text-texto mb-4">← Volver a la lista</button>
          <Editor
            key={editando.id ?? `nueva-${editando.tipo}`}
            inicial={{ ...editando, notas: editando.notas ?? [], intro: editando.intro ?? '', entra_en_vigor: editando.entra_en_vigor ?? '' }}
            onGuardado={(guardada) => { refrescar(); setEditando(guardada) }}
            onBorrado={() => { refrescar(); setEditando(null) }}
          />
        </div>
      ) : (
        <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10">
          {data.length === 0 && <p className="font-body text-sm text-borde p-4">No hay ninguna entrada todavía.</p>}
          {data.map((a) => (
            <button key={a.id} type="button" onClick={() => setEditando(a)} className="w-full text-left px-4 py-3 hover:bg-borde/5 flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="font-body text-[11px] text-borde">
                  {a.tipo === 'proximamente' ? '🔜 Próximamente' : '✓ Ya hecho'} · {fechaLarga(a.fecha)}
                </p>
                <p className="font-body text-sm text-texto font-semibold truncate">{a.emoji} {a.titulo}</p>
              </div>
              <span className={`font-body text-[11px] font-semibold whitespace-nowrap ${a.publicada ? 'text-acento' : 'text-borde'}`}>
                {a.publicada ? '👁 Publicada' : '✎ Borrador'}
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
