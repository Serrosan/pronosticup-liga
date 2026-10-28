import { useEffect, useRef, useState } from 'react'

// Grabar una nota de voz corta. Misma lógica que la del chat (formato que el
// navegador soporta de verdad: webm en Chrome/Android, mp4/m4a en iPhone), con
// un máximo de segundos: al llegar, para sola.

const TIPOS_AUDIO_PREFERIDOS = ['audio/webm', 'audio/mp4', 'audio/ogg']

export function puedeGrabarAudio() {
  return typeof window !== 'undefined' && typeof MediaRecorder !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia)
}

function tipoSoportado() {
  if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') return null
  return TIPOS_AUDIO_PREFERIDOS.find((tipo) => MediaRecorder.isTypeSupported(tipo)) ?? null
}

function extensionParaTipo(tipo) {
  if (!tipo) return 'webm'
  if (tipo.includes('mp4')) return 'm4a'
  if (tipo.includes('ogg')) return 'ogg'
  return 'webm'
}

export default function useGrabadorAudio({ maxSegundos = 15, onGrabado, onError }) {
  const [grabando, setGrabando] = useState(false)
  const [segundos, setSegundos] = useState(0)
  const recorderRef = useRef(null)
  const chunksRef = useRef([])
  const temporizadorRef = useRef(null)
  const inicioRef = useRef(0)

  function limpiar() {
    clearInterval(temporizadorRef.current)
    temporizadorRef.current = null
  }

  useEffect(() => () => {
    limpiar()
    if (recorderRef.current?.state === 'recording') recorderRef.current.stop()
  }, [])

  async function empezar() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const tipo = tipoSoportado()
      const recorder = tipo ? new MediaRecorder(stream, { mimeType: tipo }) : new MediaRecorder(stream)
      chunksRef.current = []

      recorder.ondataavailable = (e) => chunksRef.current.push(e.data)
      recorder.onstop = () => {
        limpiar()
        const tipoReal = recorder.mimeType || tipo || 'audio/webm'
        const blob = new Blob(chunksRef.current, { type: tipoReal })
        stream.getTracks().forEach((t) => t.stop())
        setGrabando(false)
        if (blob.size > 0) onGrabado(blob, extensionParaTipo(tipoReal))
      }

      recorder.start()
      recorderRef.current = recorder
      inicioRef.current = Date.now()
      setSegundos(0)
      setGrabando(true)

      temporizadorRef.current = setInterval(() => {
        const pasados = Math.floor((Date.now() - inicioRef.current) / 1000)
        setSegundos(pasados)
        if (pasados >= maxSegundos && recorder.state === 'recording') recorder.stop()
      }, 250)
    } catch {
      onError?.('No se pudo usar el micrófono. Revisa los permisos del navegador.')
    }
  }

  function parar() {
    if (recorderRef.current?.state === 'recording') recorderRef.current.stop()
  }

  return { grabando, segundos, empezar, parar }
}
