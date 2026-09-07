import { useRef, useState } from 'react'
import html2canvas from 'html2canvas'

const MEDALLAS = ['🥇', '🥈', '🥉']

function CompartirClasificacion({ fila, ligaNombre, totalParticipantes }) {
  const tarjetaRef = useRef(null)
  const [generando, setGenerando] = useState(false)
  const posicion = fila.posicion

  async function compartir() {
    setGenerando(true)
    try {
      const canvas = await html2canvas(tarjetaRef.current, {
        backgroundColor: '#0E1B2B',
        scale: 2,
      })

      canvas.toBlob(async (blob) => {
        const archivo = new File([blob], 'mi-clasificacion.png', { type: 'image/png' })

        if (navigator.canShare && navigator.canShare({ files: [archivo] })) {
          await navigator.share({
            files: [archivo],
            title: 'Mi clasificación en PronostiCup',
          })
        } else {
          const url = URL.createObjectURL(blob)
          const enlace = document.createElement('a')
          enlace.href = url
          enlace.download = 'mi-clasificacion.png'
          enlace.click()
          URL.revokeObjectURL(url)
        }
        setGenerando(false)
      })
    } catch {
      setGenerando(false)
    }
  }

  return (
    <div>
      {/* Tarjeta oculta a los ojos del usuario en la interfaz normal, solo existe para capturarla */}
      <div className="fixed -left-[9999px] top-0" aria-hidden="true">
        <div ref={tarjetaRef} className="w-[400px] p-8" style={{ backgroundColor: '#0E1B2B' }}>
          <div className="flex items-center gap-2 mb-6">
            <span className="font-display text-lg text-white tracking-wide">PronostiCup</span>
            <span className="font-body font-bold text-[10px] rounded px-1.5 py-0.5 tracking-widest" style={{ backgroundColor: '#C8FF4D', color: '#0E1B2B' }}>
              LIGA
            </span>
          </div>

          <p className="font-body text-xs uppercase tracking-widest mb-1" style={{ color: '#96ACC2' }}>{ligaNombre}</p>

          <div className="flex items-center gap-4 mb-6">
            {fila.avatar_url ? (
              <img src={fila.avatar_url} alt={fila.usuario} className="w-16 h-16 rounded-full object-cover" crossOrigin="anonymous" />
            ) : (
              <div className="w-16 h-16 rounded-full flex items-center justify-center" style={{ backgroundColor: '#C8FF4D22' }}>
                <span className="font-display text-2xl" style={{ color: '#C8FF4D' }}>{fila.usuario?.[0]?.toUpperCase()}</span>
              </div>
            )}
            <div>
              <p className="font-display text-2xl text-white">{fila.usuario}</p>
              <p className="font-body text-sm" style={{ color: '#96ACC2' }}>
                {posicion <= 3 ? `${MEDALLAS[posicion - 1]} ` : ''}
                {posicion}º de {totalParticipantes}
              </p>
            </div>
          </div>

          <div className="rounded-xl border p-5 text-center mb-4" style={{ borderColor: '#C8FF4D44', backgroundColor: '#C8FF4D11' }}>
            <p className="font-marcador text-5xl font-bold" style={{ color: '#C8FF4D' }}>{fila.puntos_totales}</p>
            <p className="font-body text-[10px] uppercase tracking-widest mt-1" style={{ color: '#96ACC2' }}>Puntos</p>
          </div>

          <div className="flex justify-around">
            <div className="text-center">
              <p className="font-marcador text-lg font-bold text-white">{fila.aciertos}</p>
              <p className="font-body text-[9px] uppercase tracking-widest" style={{ color: '#96ACC2' }}>Aciertos</p>
            </div>
            <div className="text-center">
              <p className="font-marcador text-lg font-bold" style={{ color: '#FFB238' }}>{fila.exactos}</p>
              <p className="font-body text-[9px] uppercase tracking-widest" style={{ color: '#96ACC2' }}>Exactos</p>
            </div>
          </div>

          <p className="font-body text-[10px] text-center mt-6" style={{ color: '#96ACC244' }}>pronosticup.es</p>
        </div>
      </div>

      <button
        onClick={compartir}
        disabled={generando}
        className="font-body text-sm font-semibold text-acento border border-acento/40 rounded-full px-4 py-2 hover:bg-acento/10 disabled:opacity-50"
      >
        {generando ? 'Generando...' : '📤 Compartir mi clasificación'}
      </button>
    </div>
  )
}

export default CompartirClasificacion