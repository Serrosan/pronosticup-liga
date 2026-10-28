import { useEffect, useState } from 'react'
import ReproductorAudio from './ReproductorAudio'
import useGrabadorAudio, { puedeGrabarAudio } from '../hooks/useGrabadorAudio'
import { useToast } from '../context/ToastContext'

const MAX_SEGUNDOS = 15

// Audio opcional al jugar una Falta: grabar (máx. 15 s), escucharlo y, si no
// convence, borrarlo y repetir. `audio` es { blob, extension } o null.
export default function GrabadorAudioFalta({ audio, onCambiar }) {
  const toast = useToast()
  const { grabando, segundos, empezar, parar } = useGrabadorAudio({
    maxSegundos: MAX_SEGUNDOS,
    onGrabado: (blob, extension) => onCambiar({ blob, extension }),
    onError: (mensaje) => toast.error(mensaje),
  })

  // La URL del audio grabado se crea y se libera en el mismo efecto (seguro también en modo estricto).
  const [url, setUrl] = useState(null)
  useEffect(() => {
    if (!audio) {
      setUrl(null)
      return undefined
    }
    const nueva = URL.createObjectURL(audio.blob)
    setUrl(nueva)
    return () => URL.revokeObjectURL(nueva)
  }, [audio])

  if (!puedeGrabarAudio()) return null

  if (audio && url) {
    return (
      <div className="flex items-center gap-2 mt-2">
        <div className="flex-1 min-w-0"><ReproductorAudio src={url} /></div>
        <button type="button" onClick={() => onCambiar(null)} className="font-body text-xs text-borde hover:text-red-500 shrink-0" title="Borrar el audio">
          🗑️ Borrar
        </button>
      </div>
    )
  }

  return (
    <button
      type="button"
      onClick={grabando ? parar : empezar}
      className={`w-full mt-2 font-body text-xs rounded border px-2 py-1.5 transition ${
        grabando ? 'border-red-500 text-red-500 bg-red-500/10' : 'border-borde/40 text-texto hover:bg-borde/10'
      }`}
    >
      {grabando
        ? <><span className="animate-pulse">⏺</span> Grabando… {segundos}s / {MAX_SEGUNDOS}s · toca para parar</>
        : '🎙️ Grabar un audio de burla (opcional, máx. 15 s)'}
    </button>
  )
}
