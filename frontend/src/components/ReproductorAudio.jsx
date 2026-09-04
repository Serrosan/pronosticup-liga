import { useState, useRef, useEffect } from 'react'

function formatearTiempo(segundos) {
  if (!isFinite(segundos)) return '0:00'
  const min = Math.floor(segundos / 60)
  const seg = Math.floor(segundos % 60)
  return `${min}:${seg.toString().padStart(2, '0')}`
}

function ReproductorAudio({ src }) {
  const audioRef = useRef(null)
  const [reproduciendo, setReproduciendo] = useState(false)
  const [progreso, setProgreso] = useState(0)
  const [duracion, setDuracion] = useState(0)

  useEffect(() => {
    const audio = audioRef.current
    if (!audio) return

    function actualizarProgreso() {
      setProgreso(audio.currentTime)
    }
    function alCargarMetadatos() {
      setDuracion(audio.duration)
    }
    function alTerminar() {
      setReproduciendo(false)
      setProgreso(0)
    }

    audio.addEventListener('timeupdate', actualizarProgreso)
    audio.addEventListener('loadedmetadata', alCargarMetadatos)
    audio.addEventListener('ended', alTerminar)

    return () => {
      audio.removeEventListener('timeupdate', actualizarProgreso)
      audio.removeEventListener('loadedmetadata', alCargarMetadatos)
      audio.removeEventListener('ended', alTerminar)
    }
  }, [])

  function alternarReproduccion() {
    const audio = audioRef.current
    if (!audio) return
    if (reproduciendo) {
      audio.pause()
    } else {
      audio.play()
    }
    setReproduciendo(!reproduciendo)
  }

  function moverProgreso(event) {
    const audio = audioRef.current
    if (!audio || !duracion) return
    const barra = event.currentTarget
    const porcentaje = (event.clientX - barra.getBoundingClientRect().left) / barra.offsetWidth
    audio.currentTime = porcentaje * duracion
    setProgreso(porcentaje * duracion)
  }

  const porcentajeProgreso = duracion ? (progreso / duracion) * 100 : 0

  return (
    <div className="flex items-center gap-2.5 bg-borde/10 rounded-full px-3 py-2 min-w-[200px]">
      <audio ref={audioRef} src={src} preload="metadata" className="hidden" />
      <button
        onClick={alternarReproduccion}
        className="w-8 h-8 rounded-full bg-acento text-fondo flex items-center justify-center shrink-0 hover:brightness-110 transition"
      >
        {reproduciendo ? (
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><rect x="1" y="1" width="3.5" height="10" rx="1" /><rect x="7" y="1" width="3.5" height="10" rx="1" /></svg>
        ) : (
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><path d="M2 1.5v9l8-4.5-8-4.5z" /></svg>
        )}
      </button>
      <div className="flex-1 h-1.5 bg-borde/20 rounded-full cursor-pointer" onClick={moverProgreso}>
        <div className="h-full bg-acento rounded-full transition-all" style={{ width: `${porcentajeProgreso}%` }} />
      </div>
      <span className="font-marcador text-[10px] text-borde shrink-0 tabular-nums">
        {formatearTiempo(reproduciendo || progreso > 0 ? progreso : duracion)}
      </span>
    </div>
  )
}

export default ReproductorAudio