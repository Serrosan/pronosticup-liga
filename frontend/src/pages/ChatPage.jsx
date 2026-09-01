import { useState, useRef, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'

const REACCIONES = ['👍', '🔥', '😂', '😢', '🎉']
const LIMITE_TEXTO = 500

function Avatar({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-8 h-8 rounded-full object-cover shrink-0" />
  return (
    <div className="w-8 h-8 rounded-full bg-acento/15 flex items-center justify-center shrink-0">
      <span className="font-display text-xs text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

function formatearSeparadorFecha(fechaISO) {
  const fecha = new Date(fechaISO)
  const hoy = new Date()
  const ayer = new Date(hoy)
  ayer.setDate(ayer.getDate() - 1)
  const mismaFecha = (a, b) => a.toDateString() === b.toDateString()
  if (mismaFecha(fecha, hoy)) return 'Hoy'
  if (mismaFecha(fecha, ayer)) return 'Ayer'
  return fecha.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: fecha.getFullYear() !== hoy.getFullYear() ? 'numeric' : undefined })
}

function agruparPorFechaYAutor(mensajes) {
  const grupos = []
  let grupoActual = null
  mensajes.forEach((m) => {
    const fechaDia = new Date(m.creado_en).toDateString()
    const nuevoGrupo = !grupoActual || grupoActual.fechaDia !== fechaDia || grupoActual.usuarioId !== m.usuario.id
    if (nuevoGrupo) {
      grupoActual = { fechaDia, usuarioId: m.usuario.id, mensajes: [m] }
      grupos.push(grupoActual)
    } else {
      grupoActual.mensajes.push(m)
    }
  })
  return grupos
}

function ContenidoMensaje({ mensaje }) {
  if (mensaje.tipo === 'imagen') {
    return <img src={mensaje.adjunto_url} alt="Imagen enviada" className="rounded-xl max-w-full max-h-64 object-cover" />
  }
  if (mensaje.tipo === 'audio') {
    return <audio controls src={mensaje.adjunto_url} className="max-w-[220px] h-9" />
  }
  return <p className="font-body text-sm break-words">{mensaje.texto}</p>
}

function GrupoMensajes({ grupo, esMio, miId }) {
  const queryClient = useQueryClient()
  const [selectorAbierto, setSelectorAbierto] = useState(null)

  const reaccionar = useMutation({
    mutationFn: ({ id, emoji }) => client.post(`/api/v1/chat/${id}/reaccionar`, { emoji }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chat'] })
      setSelectorAbierto(null)
    },
  })

  return (
    <div className={`flex gap-2 mb-4 ${esMio ? 'flex-row-reverse' : ''}`}>
      <Avatar url={grupo.mensajes[0].usuario.avatar_url} nombre={grupo.mensajes[0].usuario.nombre} />
      <div className={`flex flex-col ${esMio ? 'items-end' : 'items-start'} max-w-[75%] gap-1`}>
        {!esMio && <p className="font-body text-xs text-borde px-1">{grupo.mensajes[0].usuario.nombre}</p>}

        {grupo.mensajes.map((mensaje) => {
          const hora = new Date(mensaje.creado_en).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
          const tieneReacciones = Object.entries(mensaje.reacciones).some(([, ids]) => ids.length > 0)
          const esMultimedia = mensaje.tipo !== 'texto'

          return (
            <div key={mensaje.id} className="w-full">
              <div className={esMultimedia ? '' : `rounded-2xl px-3.5 py-2 ${esMio ? 'bg-acento text-fondo rounded-tr-sm' : 'bg-borde/10 text-texto rounded-tl-sm'}`}>
                <ContenidoMensaje mensaje={mensaje} />
              </div>

              <div className="flex items-center gap-1 mt-1 px-1 relative">
                <span className="font-body text-[10px] text-borde">{hora}</span>
                <button onClick={() => setSelectorAbierto(selectorAbierto === mensaje.id ? null : mensaje.id)} className="font-body text-xs text-borde hover:text-texto ml-1">
                  +
                </button>

                {selectorAbierto === mensaje.id && (
                  <div className="absolute bottom-full mb-1 left-0 bg-fondo border border-borde/30 rounded-full px-2 py-1 flex gap-1 shadow-lg z-10">
                    {REACCIONES.map((emoji) => (
                      <button key={emoji} onClick={() => reaccionar.mutate({ id: mensaje.id, emoji })} className="hover:scale-125 transition text-base">
                        {emoji}
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {tieneReacciones && (
                <div className="flex gap-1 mt-1 flex-wrap px-1">
                  {Object.entries(mensaje.reacciones).filter(([, ids]) => ids.length > 0).map(([emoji, ids]) => (
                    <button
                      key={emoji}
                      onClick={() => reaccionar.mutate({ id: mensaje.id, emoji })}
                      className={`font-body text-xs rounded-full px-2 py-0.5 border ${
                        ids.includes(miId) ? 'bg-acento/15 border-acento/40 text-acento' : 'bg-borde/10 border-borde/20 text-borde'
                      }`}
                    >
                      {emoji} {ids.length}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}

function useGrabadorAudio(onGrabado) {
  const [grabando, setGrabando] = useState(false)
  const mediaRecorderRef = useRef(null)
  const chunksRef = useRef([])

  async function empezar() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const recorder = new MediaRecorder(stream)
      chunksRef.current = []

      recorder.ondataavailable = (e) => chunksRef.current.push(e.data)
      recorder.onstop = () => {
        const blob = new Blob(chunksRef.current, { type: 'audio/webm' })
        stream.getTracks().forEach((t) => t.stop())
        onGrabado(blob)
      }

      recorder.start()
      mediaRecorderRef.current = recorder
      setGrabando(true)
    } catch {
      alert('No se pudo acceder al micrófono. Revisa los permisos del navegador.')
    }
  }

  function parar() {
    mediaRecorderRef.current?.stop()
    setGrabando(false)
  }

  return { grabando, empezar, parar }
}

function ChatPage() {
  const { usuario } = useAuth()
  const toast = useToast()
  const [texto, setTexto] = useState('')
  const [subiendo, setSubiendo] = useState(false)
  const finRef = useRef(null)
  const inputImagenRef = useRef(null)
  const queryClient = useQueryClient()

  const { data } = useQuery({
    queryKey: ['chat'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/chat')
      return { mensajes: respuesta.data.data, totalMiembros: respuesta.data.meta?.total_miembros }
    },
    refetchInterval: 4000,
  })

  const enviarTexto = useMutation({
    mutationFn: (texto) => client.post('/api/v1/chat', { tipo: 'texto', texto }),
    onSuccess: () => {
      setTexto('')
      queryClient.invalidateQueries({ queryKey: ['chat'] })
    },
    onError: () => toast.error('No se pudo enviar el mensaje.'),
  })

  const enviarImagen = useMutation({
    mutationFn: async (archivo) => {
      const formData = new FormData()
      formData.append('imagen', archivo)
      const subida = await client.post('/api/v1/chat/subir-imagen', formData)
      return client.post('/api/v1/chat', { tipo: 'imagen', adjunto_url: subida.data.url })
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['chat'] }),
    onError: () => toast.error('No se pudo enviar la imagen.'),
    onSettled: () => setSubiendo(false),
  })

  const enviarAudio = useMutation({
    mutationFn: async (blob) => {
      const formData = new FormData()
      formData.append('audio', blob, 'nota-de-voz.webm')
      const subida = await client.post('/api/v1/chat/subir-audio', formData)
      return client.post('/api/v1/chat', { tipo: 'audio', adjunto_url: subida.data.url })
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['chat'] }),
    onError: () => toast.error('No se pudo enviar la nota de voz.'),
    onSettled: () => setSubiendo(false),
  })

  const { grabando, empezar, parar } = useGrabadorAudio((blob) => {
    setSubiendo(true)
    enviarAudio.mutate(blob)
  })

  useEffect(() => {
    finRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [data])

  function handleSubmit(event) {
    event.preventDefault()
    if (!texto.trim()) return
    enviarTexto.mutate(texto.trim())
  }

  function handleImagenSeleccionada(event) {
    const archivo = event.target.files[0]
    if (archivo) {
      setSubiendo(true)
      enviarImagen.mutate(archivo)
    }
    event.target.value = ''
  }

  const grupos = data ? agruparPorFechaYAutor(data.mensajes) : []
  const cercaDelLimite = texto.length > LIMITE_TEXTO - 60

  return (
    <div className="max-w-2xl mx-auto px-4 py-6 flex flex-col" style={{ height: 'calc(100vh - 5rem)' }}>
      <div className="bg-fondo border border-borde/30 rounded-t-lg overflow-hidden">
        <TicketHeader titulo="Chat de la liga" />
        {data?.totalMiembros && (
          <p className="font-body text-xs text-borde px-4 py-2">{data.totalMiembros} miembros</p>
        )}
      </div>

      <div className="flex-1 overflow-y-auto bg-fondo border-x border-borde/30 p-4">
        {!data ? (
          <p className="font-body text-sm text-borde text-center">Cargando...</p>
        ) : data.mensajes.length === 0 ? (
          <div className="text-center py-12">
            <p className="font-body text-sm text-borde">Aún no hay mensajes</p>
            <p className="font-body text-xs text-borde/60 mt-1">Sé el primero en escribir algo</p>
          </div>
        ) : (
          grupos.map((grupo, i) => {
            const mostrarSeparador = i === 0 || grupos[i - 1].fechaDia !== grupo.fechaDia
            return (
              <div key={i}>
                {mostrarSeparador && (
                  <div className="flex items-center gap-3 my-4">
                    <div className="h-px bg-borde/20 flex-1" />
                    <p className="font-body text-[10px] uppercase tracking-widest text-borde whitespace-nowrap">
                      {formatearSeparadorFecha(grupo.mensajes[0].creado_en)}
                    </p>
                    <div className="h-px bg-borde/20 flex-1" />
                  </div>
                )}
                <GrupoMensajes grupo={grupo} esMio={grupo.usuarioId === usuario?.id} miId={usuario?.id} />
              </div>
            )
          })
        )}
        {subiendo && <p className="font-body text-xs text-borde text-center">Subiendo...</p>}
        <div ref={finRef} />
      </div>

      <form onSubmit={handleSubmit} className="bg-fondo border border-borde/30 rounded-b-lg p-3">
        <div className="flex gap-2 items-center">
          <input type="file" accept="image/*" ref={inputImagenRef} onChange={handleImagenSeleccionada} className="hidden" />
          <button
            type="button"
            onClick={() => inputImagenRef.current?.click()}
            disabled={grabando}
            className="font-body text-lg text-borde hover:text-texto shrink-0 disabled:opacity-30"
            title="Enviar imagen"
          >
            📷
          </button>

          <input
            value={texto}
            onChange={(e) => setTexto(e.target.value)}
            placeholder={grabando ? 'Grabando audio...' : 'Escribe un mensaje...'}
            maxLength={LIMITE_TEXTO}
            disabled={grabando}
            className="flex-1 font-body bg-borde/10 text-texto rounded-full border border-borde/40 px-4 py-2.5 focus:outline-none focus:border-acento disabled:opacity-50"
          />

          <button
            type="button"
            onClick={grabando ? parar : empezar}
            className={`font-body text-lg shrink-0 ${grabando ? 'text-red-500 animate-pulse' : 'text-borde hover:text-texto'}`}
            title={grabando ? 'Detener grabación' : 'Grabar nota de voz'}
          >
            {grabando ? '⏹️' : '🎤'}
          </button>

          {!grabando && (
            <button
              type="submit"
              disabled={enviarTexto.isPending || !texto.trim()}
              className="bg-acento text-fondo font-body font-semibold text-sm rounded-full px-5 py-2.5 hover:brightness-110 disabled:opacity-50 shrink-0"
            >
              ➤
            </button>
          )}
        </div>
        {cercaDelLimite && !grabando && (
          <p className={`font-body text-[10px] mt-1 px-2 ${texto.length >= LIMITE_TEXTO ? 'text-red-500' : 'text-borde'}`}>
            {texto.length}/{LIMITE_TEXTO}
          </p>
        )}
      </form>
    </div>
  )
}

export default ChatPage