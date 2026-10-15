import { Link } from 'react-router-dom'

const PATRON_URL = /(https?:\/\/[^\s]+)/g

function esReciente(fechaCreacion) {
  if (!fechaCreacion) return false
  const horas = (Date.now() - new Date(fechaCreacion)) / 3600000
  return horas < 48
}

function TituloConEnlaces({ texto, conEnlaces }) {
  // Si la novedad entera ya lleva a algún sitio, no se pintan enlaces dentro
  // (un enlace dentro de otro no es válido y confunde al pulsar).
  if (!conEnlaces) return <span>{texto}</span>

  const partes = texto.split(PATRON_URL)

  return (
    <>
      {partes.map((parte, i) => {
        if (/^https?:\/\//.test(parte)) {
          return (
            <a
              key={i}
              href={parte}
              target="_blank"
              rel="noopener noreferrer"
              className="underline hover:brightness-125"
              onClick={(evento) => evento.stopPropagation()}
            >
              {parte}
            </a>
          )
        }
        return <span key={i}>{parte}</span>
      })}
    </>
  )
}

// Una línea del ticker. Si la novedad trae "enlace" (p. ej. /jornadas/8/liga),
// toda la línea se puede pulsar y lleva allí, con una flecha que lo indica.
function LineaNovedad({ novedad }) {
  const reciente = esReciente(novedad.created_at)
  const enlace = novedad.enlace

  const contenido = (
    <>
      <span className="text-sm shrink-0">{novedad.emoji || '🆕'}</span>
      <p
        className="font-marcador text-xs font-semibold tracking-wide text-acento dark:font-normal"
        style={{
          textShadow: reciente
            ? '0 0 8px var(--color-acento), 0 0 16px var(--color-acento)66'
            : '0 0 3px var(--color-acento)55',
        }}
      >
        <TituloConEnlaces texto={novedad.titulo} conEnlaces={!enlace} />
        {enlace && <span className="font-body ml-1.5 whitespace-nowrap">→</span>}
      </p>
      {reciente && (
        <span className="font-body text-[8px] font-bold text-fondo bg-acento rounded-full px-1.5 py-0.5 shrink-0">
          NUEVO
        </span>
      )}
    </>
  )

  if (!enlace) return <div className="flex items-center gap-2">{contenido}</div>

  const clase = 'flex items-center gap-2 rounded hover:bg-acento/10 -mx-1 px-1 transition'
  if (enlace.startsWith('/')) {
    return <Link to={enlace} className={clase}>{contenido}</Link>
  }
  return (
    <a href={enlace} target="_blank" rel="noopener noreferrer" className={clase}>
      {contenido}
    </a>
  )
}

function TickerNovedades({ novedades }) {
  if (!novedades || novedades.length === 0) return null

  return (
    <div className="bg-fondo border border-acento/30 rounded-lg overflow-hidden mb-6 relative">
      <div
        className="absolute inset-0 pointer-events-none opacity-40 dark:opacity-40 opacity-[0.15]"
        style={{ background: 'radial-gradient(ellipse at top left, var(--color-acento)22, transparent 70%)' }}
      />
      <div className="bg-acento/10 px-4 py-1.5 flex items-center gap-2 relative">
        <span className="relative flex h-2 w-2">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-acento opacity-75" />
          <span className="relative inline-flex rounded-full h-2 w-2 bg-acento" />
        </span>
        <p className="font-body text-[10px] uppercase tracking-widest text-acento font-semibold">Novedades</p>
      </div>
      <div className="px-4 py-2.5 flex flex-col gap-1.5 relative">
        {novedades.map((n, i) => <LineaNovedad key={i} novedad={n} />)}
      </div>
    </div>
  )
}

export default TickerNovedades
