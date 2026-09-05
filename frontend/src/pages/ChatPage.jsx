import { useState, useRef, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'
import ReproductorAudio from '../components/ReproductorAudio'
import SelectorStickers from '../components/SelectorStickers'

const REACCIONES = ['👍', '🔥', '😂', '😢', '🎉']
const LIMITE_TEXTO = 500
const PATRON_URL = /(https?:\/\/[^\s]+)/g
const SEGUNDOS_PARA_DESHACER = 3

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

function TextoConEnlaces({ texto, esMio }) {
  const partes = texto.split(PATRON_URL)

  return (
    <p className="font-body text-sm break-words">
      {partes.map((parte, i) => {
        const esUrl = /^https?:\/\//.test(parte)
        if (esUrl) {
          return (
            <a
              key={i}
              href={parte}
              target="_blank"
              rel="noopener noreferrer"
              className={`underline ${esMio ? 'text-fondo/90 hover:text-fondo' : 'text-acento hover:brightness-110'}`}
              onClick={(evento) => evento.stopPropagation()}
            >
              {parte}
            </a>
          )
        }
        return <span key={i}>{parte}</span>
      })}
    </p>
  )
}

function ContenidoMensaje({ mensaje, esMio }) {
  if (mensaje.tipo === 'imagen') {
    return <img src={mensaje.adjunto_url} alt="Imagen enviada" className="rounded-xl max-w-full max-h-64 object-cover" />
  }
  if (mensaje.tipo === 'audio') {
    return <ReproductorAudio src={mensaje.adjunto_url} />
  }
  return <TextoConEnlaces texto={mensaje.texto} esMio={esMio} />
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
          const esSticker = mensaje.tipo === 'imagen' && mensaje.adjunto_url?.startsWith('/stickers/')

          return (
            <div key={mensaje.id} className="w-full">
              <div className={esMultimedia ? '' : `rounded-2xl px-3.5 py-2 ${esMio ? 'bg-acento text-fondo rounded-tr-sm' : 'bg-borde/10 text-texto rounded-tl-sm'}`}>
                {esSticker ? (
                  <img src={mensaje.adjunto_url} alt="Sticker" className="w-28 h-28 object-contain" />
                ) : (
                  <ContenidoMensaje mensaje={mensaje} esMio={esMio} />
                )}
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

function MensajePendiente({ texto, segundosRestantes, onDeshacer }) {
  return (
    <div className="flex flex-row-reverse gap-2 mb-4">
      <div className="w-8 h-8 shrink-0" />
      <div className="flex flex-col items-end max-w-[75%] gap-1">
        <div className="rounded-2xl px-3.5 py-2 bg-acento/40 text-fondo rounded-tr-sm">
          <p className="font-body text-sm break-words opacity-70">{texto}</p>
        </div>
        <button onClick={onDeshacer} className="font-body text-xs text-premio hover:underline">
          Enviando en {segundosRestantes}s · Deshacer
        </button>
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
  const [mostrarStickers, setMostrarStickers] = useState(false)
  const [mensajePendiente, setMensajePendiente] = useState(null)
  const [segundosRestantes, setSegundosRestantes] = useState(SEGUNDOS_PARA_DESHACER)
  const [imagenPreview, setImagenPreview] = useState(null)
  const finRef = useRef(null)
  const inputImagenRef = useRef(null)
  const timeoutEnvioRef = useRef(null)
  const intervaloCuentaRef = useRef(null)
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
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['chat'] }),
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

  const enviarSticker = useMutation({
    mutationFn: (archivo) => client.post('/api/v1/chat', { tipo: 'imagen', adjunto_url: `/stickers/${archivo}` }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['chat'] }),
    onError: () => toast.error('No se pudo enviar el sticker.'),
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
  }, [data, mensajePendiente])

  useEffect(() => {
    return () => {
      clearTimeout(timeoutEnvioRef.current)
      clearInterval(intervaloCuentaRef.current)
    }
  }, [])

  function handleSubmit(event) {
    event.preventDefault()
    const textoAEnviar = texto.trim()
    if (!textoAEnviar) return

    setTexto('')
    setMensajePendiente(textoAEnviar)
    setSegundosRestantes(SEGUNDOS_PARA_DESHACER)

    intervaloCuentaRef.current = setInterval(() => {
      setSegundosRestantes((prev) => Math.max(0, prev - 1))
    }, 1000)

    timeoutEnvioRef.current = setTimeout(() => {
      clearInterval(intervaloCuentaRef.current)
      setMensajePendiente(null)
      enviarTexto.mutate(textoAEnviar)
    }, SEGUNDOS_PARA_DESHACER * 1000)
  }

  function deshacerEnvio() {
    clearTimeout(timeoutEnvioRef.current)
    clearInterval(intervaloCuentaRef.current)
    setTexto(mensajePendiente)
    setMensajePendiente(null)
  }

  function handleImagenSeleccionada(event) {
    const archivo = event.target.files[0]
    if (archivo) {
      setImagenPreview({ archivo, url: URL.createObjectURL(archivo) })
    }
    event.target.value = ''
  }

  function confirmarEnvioImagen() {
    setSubiendo(true)
    enviarImagen.mutate(imagenPreview.archivo)
    setImagenPreview(null)
  }

  function cancelarEnvioImagen() {
    URL.revokeObjectURL(imagenPreview.url)
    setImagenPreview(null)
  }

  const grupos = data ? agruparPorFechaYAutor(data.mensajes) : []

  return (
    <div className="max-w-3xl mx-auto px-4 py-6 flex flex-col" style={{ height: 'calc(100vh - 5rem)' }}>
      <div className="bg-fondo border border-borde/30 rounded-t-lg overflow-hidden">
        <TicketHeader
          titulo="Chat de la liga"
          accion={
            data?.totalMiembros && (
              <span className="font-body text-[11px] text-acento bg-acento/10 rounded-full px-2.5 py-1 font-semibold">
                {data.totalMiembros} miembros
              </span>
            )
          }
        />
      </div>

      <div className="flex-1 overflow-y-auto bg-fondo border-x border-borde/30 p-4">
        {!data ? (
          <p className="font-body text-sm text-borde text-center">Cargando...</p>
        ) : data.mensajes.length === 0 && !mensajePendiente ? (
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
        {mensajePendiente && (
          <MensajePendiente texto={mensajePendiente} segundosRestantes={segundosRestantes} onDeshacer={deshacerEnvio} />
        )}
        {subiendo && <p className="font-body text-xs text-borde text-center">Subiendo...</p>}
        <div ref={finRef} />
      </div>

      {imagenPreview && (
        <div className="bg-fondo border-x border-borde/30 p-3 flex items-center gap-3">
          <img src={imagenPreview.url} alt="Vista previa" className="w-16 h-16 rounded-lg object-cover" />
          <p className="font-body text-sm text-borde flex-1">¿Enviar esta imagen?</p>
          <button onClick={cancelarEnvioImagen} className="font-body text-sm text-borde hover:text-texto px-3 py-1.5">
            Cancelar
          </button>
          <button onClick={confirmarEnvioImagen} className="font-body text-sm font-semibold bg-acento text-fondo rounded-full px-4 py-1.5 hover:brightness-110">
            Enviar
          </button>
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-fondo border border-borde/30 rounded-b-lg p-3">
        <div className="flex gap-2 items-center relative">
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

          <div className="relative">
            <button
              type="button"
              onClick={() => setMostrarStickers(!mostrarStickers)}
              disabled={grabando}
              className="font-body text-lg text-borde hover:text-texto shrink-0 disabled:opacity-30"
              title="Stickers"
            >
              😄
            </button>
            {mostrarStickers && (
              <SelectorStickers
                onSeleccionar={(archivo) => enviarSticker.mutate(archivo)}
                onCerrar={() => setMostrarStickers(false)}
              />
            )}
          </div>

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
              disabled={!texto.trim()}
              className="bg-acento text-fondo font-body font-semibold text-sm rounded-full px-5 py-2.5 hover:brightness-110 disabled:opacity-50 shrink-0"
            >
              ➤
            </button>
          )}
        </div>
        {!grabando && (
          <p className={`font-body text-[10px] mt-1 px-2 ${texto.length >= LIMITE_TEXTO ? 'text-red-500' : 'text-borde'}`}>
            {texto.length}/{LIMITE_TEXTO}
          </p>
        )}
      </form>
    </div>
  )
}

export default ChatPage
