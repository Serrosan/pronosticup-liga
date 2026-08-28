function esReciente(fechaCreacion) {
  if (!fechaCreacion) return false
  const horas = (Date.now() - new Date(fechaCreacion)) / 3600000
  return horas < 48
}

function TickerNovedades({ novedades }) {
  if (!novedades || novedades.length === 0) return null

  return (
    <div className="bg-fondo border border-acento/30 rounded-lg overflow-hidden mb-6 relative">
      <div
        className="absolute inset-0 pointer-events-none opacity-40"
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
        {novedades.map((n, i) => {
          const reciente = esReciente(n.created_at)
          return (
            <div key={i} className="flex items-center gap-2">
              <span className="text-sm shrink-0">{n.emoji || '🆕'}</span>
              <p
                className="font-marcador text-xs tracking-wide"
                style={{
                  color: 'var(--color-acento)',
                  textShadow: reciente
                    ? '0 0 8px var(--color-acento), 0 0 16px var(--color-acento)66'
                    : '0 0 3px var(--color-acento)55',
                }}
              >
                {n.titulo}
              </p>
              {reciente && (
                <span className="font-body text-[8px] font-bold text-fondo bg-acento rounded-full px-1.5 py-0.5 shrink-0">
                  NUEVO
                </span>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default TickerNovedades