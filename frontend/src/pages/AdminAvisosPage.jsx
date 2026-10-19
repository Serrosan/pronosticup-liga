import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'

// Admin → Enviar aviso: un mensaje tuyo que llega a la campana, sale como aviso
// al entrar en la app y llega al móvil de quien tenga los avisos activados.

const MAX_TITULO = 60
const MAX_MENSAJE = 300

function Contador({ texto, maximo }) {
  const cerca = texto.length > maximo * 0.9
  return (
    <span className={`font-body text-[11px] ${cerca ? 'text-premio font-semibold' : 'text-borde'}`}>
      {texto.length}/{maximo}
    </span>
  )
}

function VistaPrevia({ titulo, mensaje }) {
  return (
    <div>
      <p className="font-body text-[11px] uppercase tracking-widest text-borde mb-2">Así se verá en el móvil</p>
      <div className="flex gap-3 items-start bg-borde/10 border border-borde/20 rounded-2xl px-4 py-3 max-w-sm">
        <img src="/icons/icon-192.png" alt="" className="w-9 h-9 rounded-full shrink-0" />
        <div className="min-w-0">
          <p className="font-body text-[11px] text-borde">PronostiCup · ahora</p>
          <p className="font-body text-sm font-semibold text-texto break-words">{titulo || 'Título del aviso'}</p>
          <p className="font-body text-sm text-texto/80 break-words">{mensaje || 'El mensaje que quieres mandar.'}</p>
        </div>
      </div>
    </div>
  )
}

function AdminAvisosPage() {
  const toast = useToast()
  const [para, setPara] = useState('todos')
  const [titulo, setTitulo] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [url, setUrl] = useState('')
  const [confirmando, setConfirmando] = useState(false)
  const [resultado, setResultado] = useState(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-avisos'],
    queryFn: async () => (await client.get('/api/v1/admin/avisos')).data.data,
  })

  const enviar = useMutation({
    mutationFn: () => client.post('/api/v1/admin/avisos', { para, titulo: titulo.trim(), mensaje: mensaje.trim(), url: url || null }),
    onSuccess: (respuesta) => {
      setResultado(respuesta.data.message)
      setTitulo('')
      setMensaje('')
      setUrl('')
      setConfirmando(false)
      toast.exito('Aviso enviado.')
    },
    onError: (err) => {
      setConfirmando(false)
      toast.error(err.response?.data?.message ?? 'No se pudo enviar el aviso.')
    },
  })

  if (isLoading) return <p className="font-body text-sm text-borde">Cargando…</p>
  if (error) return <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>

  const personas = para === 'todos' ? data.todos : (data.ligas.find((l) => String(l.id) === para)?.personas ?? 0)
  const nombrePara = para === 'todos' ? 'todo el mundo' : `la liga ${data.ligas.find((l) => String(l.id) === para)?.nombre ?? ''}`
  const listo = titulo.trim().length > 0 && mensaje.trim().length > 0 && personas > 0

  const campo = 'w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento'

  return (
    <div className="max-w-2xl">
      <h1 className="font-display text-2xl text-texto mb-1">Enviar aviso</h1>
      <p className="font-body text-sm text-borde mb-6">
        Llega a la campana 🔔, sale como aviso al entrar en la app y llega al móvil de quien tenga los avisos activados.
      </p>

      <div className="flex flex-col gap-4 mb-6">
        <label className="flex flex-col gap-1">
          <span className="font-body text-xs text-borde">Para</span>
          <select value={para} onChange={(e) => setPara(e.target.value)} className={campo}>
            <option value="todos">Todo el mundo ({data.todos})</option>
            {data.ligas.map((l) => (
              <option key={l.id} value={String(l.id)}>Liga {l.nombre} ({l.personas})</option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1">
          <span className="flex justify-between font-body text-xs text-borde">Título <Contador texto={titulo} maximo={MAX_TITULO} /></span>
          <input value={titulo} maxLength={MAX_TITULO} onChange={(e) => setTitulo(e.target.value)} placeholder="📅 El aplazado de la jornada 6, el miércoles" className={campo} />
        </label>

        <label className="flex flex-col gap-1">
          <span className="flex justify-between font-body text-xs text-borde">Mensaje <Contador texto={mensaje} maximo={MAX_MENSAJE} /></span>
          <textarea value={mensaje} maxLength={MAX_MENSAJE} onChange={(e) => setMensaje(e.target.value)} rows={3} placeholder="Se juega a las 21:00 y todavía estáis a tiempo de pronosticarlo." className={campo} />
        </label>

        <label className="flex flex-col gap-1">
          <span className="font-body text-xs text-borde">Al tocarlo, abre</span>
          <select value={url} onChange={(e) => setUrl(e.target.value)} className={campo}>
            {data.destinos.map((d) => (
              <option key={d.url} value={d.url}>{d.etiqueta}</option>
            ))}
          </select>
        </label>
      </div>

      <div className="mb-6">
        <VistaPrevia titulo={titulo} mensaje={mensaje} />
      </div>

      {confirmando ? (
        <div className="border border-premio/40 bg-premio/10 rounded-lg p-4 flex flex-col gap-3">
          <p className="font-body text-sm text-texto">⚠️ Se enviará a <strong>{nombrePara}</strong>: {personas} persona(s). No se puede deshacer.</p>
          <div className="flex gap-3">
            <button
              type="button"
              onClick={() => enviar.mutate()}
              disabled={enviar.isPending}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
            >
              {enviar.isPending ? 'Enviando…' : '📣 Sí, enviar'}
            </button>
            <button type="button" onClick={() => setConfirmando(false)} className="font-body text-sm text-borde hover:text-texto px-3">
              Cancelar
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => { setResultado(null); setConfirmando(true) }}
          disabled={!listo}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
        >
          📣 Enviar a {personas} persona(s)
        </button>
      )}

      {resultado && <p className="font-body text-sm text-texto mt-4">✓ {resultado}</p>}
    </div>
  )
}

export default AdminAvisosPage
