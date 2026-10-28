// Cómo se pinta una entrada de "Actualizaciones". Lo usan la página de los
// jugadores y la vista previa del admin, para que se vea exactamente igual.

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

export function fechaLarga(iso) {
  if (!iso) return 'sin fecha'
  const [a, m, d] = String(iso).slice(0, 10).split('-').map(Number)
  return `${d} de ${MESES[m - 1]} de ${a}`
}

export function mesDe(iso) {
  const [a, m] = String(iso).slice(0, 10).split('-').map(Number)
  return `${MESES[m - 1][0].toUpperCase()}${MESES[m - 1].slice(1)} ${a}`
}

export function Proximamente({ entrada }) {
  return (
    <div className="bg-fondo border-2 border-premio/50 rounded-lg overflow-hidden">
      <div className="px-4 py-3 bg-premio/10 border-b border-premio/30">
        <p className="font-body text-[11px] uppercase tracking-widest text-premio font-semibold">🔜 Próximamente</p>
        <h2 className="font-display text-xl text-texto mt-1 break-words">
          {entrada.emoji && <span aria-hidden="true">{entrada.emoji} </span>}{entrada.titulo || 'Sin título'}
        </h2>
        <p className="font-body text-xs text-borde mt-1">
          Anunciado el {fechaLarga(entrada.fecha)}
          {entrada.entra_en_vigor && <> · <span className="text-texto font-semibold">Entra en vigor: {entrada.entra_en_vigor}</span></>}
        </p>
      </div>

      {entrada.intro && <p className="font-body text-sm text-texto px-4 pt-3 whitespace-pre-line">{entrada.intro}</p>}

      <ul className="flex flex-col gap-3 p-4">
        {(entrada.puntos ?? []).map((c, i) => (
          <li key={i} className="border border-borde/25 rounded-lg overflow-hidden">
            <p className="font-body text-sm font-semibold text-texto px-3 py-2 bg-borde/10 break-words">{c.carta || '…'}</p>
            {(c.antes || c.despues) && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-px bg-borde/15">
                <div className="bg-fondo px-3 py-2">
                  <p className="font-body text-[10px] uppercase tracking-widest text-borde">Antes</p>
                  <p className="font-body text-sm text-borde break-words">{c.antes || '—'}</p>
                </div>
                <div className="bg-fondo px-3 py-2">
                  <p className="font-body text-[10px] uppercase tracking-widest text-acento font-semibold">Después →</p>
                  <p className="font-body text-sm text-texto font-semibold break-words">{c.despues || '—'}</p>
                </div>
              </div>
            )}
            {c.porque && (
              <p className="font-body text-xs text-borde px-3 py-2 border-t border-borde/15 break-words">
                <span className="text-texto">Por qué:</span> {c.porque}
              </p>
            )}
          </li>
        ))}
      </ul>

      {entrada.notas?.length > 0 && (
        <div className="px-4 pb-4 flex flex-col gap-1.5">
          {entrada.notas.map((n, i) => (
            <p key={i} className="font-body text-xs text-borde break-words">ℹ️ {n}</p>
          ))}
        </div>
      )}
    </div>
  )
}

export function EntradaHistorial({ entrada }) {
  return (
    <li className="relative pl-6">
      <span className="absolute left-0 top-0.5 text-base" aria-hidden="true">{entrada.emoji || '•'}</span>
      <p className="font-body text-[11px] text-borde">{fechaLarga(entrada.fecha)}</p>
      <p className="font-body text-sm font-semibold text-texto break-words">{entrada.titulo || 'Sin título'}</p>
      {entrada.intro && <p className="font-body text-sm text-borde mt-1 whitespace-pre-line">{entrada.intro}</p>}
      <ul className="mt-1 flex flex-col gap-1">
        {(entrada.puntos ?? []).map((c, i) => (
          <li key={i} className="font-body text-sm text-borde flex gap-2">
            <span aria-hidden="true" className="text-acento">•</span>
            <span className="break-words min-w-0">{c}</span>
          </li>
        ))}
      </ul>
    </li>
  )
}
