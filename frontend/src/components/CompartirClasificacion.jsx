import { useRef, useState } from 'react'
import html2canvas from 'html2canvas'

const MEDALLAS = ['🥇', '🥈', '🥉']

function CompartirClasificacion({ fila, ligaNombre, totalParticipantes }) {
  const tarjetaRef = useRef(null)
  const [generando, setGenerando] = useState(false)
  const posicion = fila.posicion
  const esPodio = posicion <= 3

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
            text: `Voy ${posicion}º en ${ligaNombre} con ${fila.puntos_totales} puntos 🏆`,
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
      {/* Tarjeta oculta a los ojos del usuario en la interfaz normal, solo existe para capturarla.
          IMPORTANTE: solo colores rgba()/hex sólidos, SIN degradados ni hex de 8 dígitos —
          html2canvas no los renderiza de forma fiable y dejan la tarjeta vacía. */}
      <div className="fixed -left-[9999px] top-0" aria-hidden="true">
        <div
          ref={tarjetaRef}
          className="w-[420px]"
          style={{ backgroundColor: '#0E1B2B' }}
        >
          <div className="p-7">
            <div className="flex items-center justify-between" style={{ marginBottom: '28px' }}>
              <div className="flex items-center gap-2">
                <span className="font-display text-lg text-white tracking-wide">PronostiCup</span>
                <span className="font-body font-bold text-[10px] rounded px-1.5 py-0.5 tracking-widest" style={{ backgroundColor: '#C8FF4D', color: '#0E1B2B' }}>
                  LIGA
                </span>
              </div>
              <span
                className="font-body text-[10px] uppercase tracking-widest px-2.5 py-1 rounded-full"
                style={{ color: '#C8FF4D', border: '1px solid rgba(200,255,77,0.35)' }}
              >
                {ligaNombre}
              </span>
            </div>

            <div className="flex items-center gap-4" style={{ marginBottom: '24px' }}>
              <div className="relative shrink-0">
                {fila.avatar_url ? (
                  <img
                    src={fila.avatar_url}
                    alt={fila.usuario}
                    crossOrigin="anonymous"
                    className="w-20 h-20 rounded-full object-cover"
                    style={{ border: `3px solid ${esPodio ? '#C8FF4D' : 'rgba(150,172,194,0.3)'}` }}
                  />
                ) : (
                  <div
                    className="w-20 h-20 rounded-full flex items-center justify-center"
                    style={{ backgroundColor: 'rgba(200,255,77,0.15)', border: `3px solid ${esPodio ? '#C8FF4D' : 'rgba(150,172,194,0.3)'}` }}
                  >
                    <span className="font-display text-3xl" style={{ color: '#C8FF4D' }}>{fila.usuario?.[0]?.toUpperCase()}</span>
                  </div>
                )}
                {esPodio && (
                  <span className="absolute" style={{ bottom: '-4px', right: '-4px', fontSize: '26px' }}>{MEDALLAS[posicion - 1]}</span>
                )}
              </div>
              <div className="min-w-0">
                <p className="font-display text-2xl text-white truncate">{fila.usuario}</p>
                <p className="font-body text-sm font-semibold" style={{ color: esPodio ? '#C8FF4D' : '#96ACC2' }}>
                  {posicion}º de {totalParticipantes} participantes
                </p>
              </div>
            </div>

            <div
              className="rounded-2xl text-center"
              style={{
                border: '1px solid rgba(200,255,77,0.4)',
                backgroundColor: 'rgba(200,255,77,0.10)',
                padding: '24px',
                marginBottom: '20px',
              }}
            >
              <p className="font-body text-[10px] uppercase tracking-[0.3em]" style={{ color: '#96ACC2', marginBottom: '6px' }}>
                Puntos totales
              </p>
              <p className="font-marcador font-bold" style={{ color: '#C8FF4D', fontSize: '64px', lineHeight: 1 }}>
                {fila.puntos_totales}
              </p>
            </div>

            <div className="flex" style={{ gap: '12px', marginBottom: '8px' }}>
              <div className="flex-1 rounded-xl text-center" style={{ backgroundColor: 'rgba(255,255,255,0.06)', padding: '12px' }}>
                <p className="font-marcador text-xl font-bold text-white">{fila.aciertos}</p>
                <p className="font-body text-[9px] uppercase tracking-widest" style={{ color: '#96ACC2', marginTop: '2px' }}>Aciertos</p>
              </div>
              <div className="flex-1 rounded-xl text-center" style={{ backgroundColor: 'rgba(255,255,255,0.06)', padding: '12px' }}>
                <p className="font-marcador text-xl font-bold" style={{ color: '#FFB238' }}>{fila.exactos}</p>
                <p className="font-body text-[9px] uppercase tracking-widest" style={{ color: '#96ACC2', marginTop: '2px' }}>Exactos</p>
              </div>
              {fila.fallos !== undefined && (
                <div className="flex-1 rounded-xl text-center" style={{ backgroundColor: 'rgba(255,255,255,0.06)', padding: '12px' }}>
                  <p className="font-marcador text-xl font-bold" style={{ color: '#EF4444' }}>{fila.fallos}</p>
                  <p className="font-body text-[9px] uppercase tracking-widest" style={{ color: '#96ACC2', marginTop: '2px' }}>Fallos</p>
                </div>
              )}
            </div>

            <div
              className="flex items-center justify-center gap-2"
              style={{ marginTop: '24px', paddingTop: '16px', borderTop: '1px dashed rgba(150,172,194,0.25)' }}
            >
              <span className="font-body text-xs font-semibold" style={{ color: '#C8FF4D' }}>⚽ pronosticup.es</span>
              <span className="font-body text-[11px]" style={{ color: '#96ACC2' }}>· ¿te atreves a superarme?</span>
            </div>
          </div>
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