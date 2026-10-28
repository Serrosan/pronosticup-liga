import { useEffect } from 'react'
import TicketHeader from '../components/TicketHeader'
import { marcarActualizacionesVistas } from '../components/BotonActualizaciones'
import { PROXIMAMENTE, HISTORIAL } from '../data/actualizaciones'

// "Actualizaciones": lo que va a cambiar y lo que ya ha cambiado, en lenguaje de
// jugador. El contenido vive en src/data/actualizaciones.js.

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

function fechaLarga(iso) {
  const [a, m, d] = iso.split('-').map(Number)
  return `${d} de ${MESES[m - 1]} de ${a}`
}

function mesDe(iso) {
  const [a, m] = iso.split('-').map(Number)
  return `${MESES[m - 1][0].toUpperCase()}${MESES[m - 1].slice(1)} ${a}`
}

function Proximamente({ entrada }) {
  return (
    <div className="bg-fondo border-2 border-premio/50 rounded-lg overflow-hidden">
      <div className="px-4 py-3 bg-premio/10 border-b border-premio/30">
        <p className="font-body text-[11px] uppercase tracking-widest text-premio font-semibold">🔜 Próximamente</p>
        <h2 className="font-display text-xl text-texto mt-1">
          <span aria-hidden="true">{entrada.emoji} </span>{entrada.titulo}
        </h2>
        <p className="font-body text-xs text-borde mt-1">
          Anunciado el {fechaLarga(entrada.fecha)} · <span className="text-texto font-semibold">Entra en vigor: {entrada.entraEnVigor}</span>
        </p>
      </div>

      <p className="font-body text-sm text-texto px-4 pt-3">{entrada.intro}</p>

      <ul className="flex flex-col gap-3 p-4">
        {entrada.cambios.map((c) => (
          <li key={c.carta} className="border border-borde/25 rounded-lg overflow-hidden">
            <p className="font-body text-sm font-semibold text-texto px-3 py-2 bg-borde/10">{c.carta}</p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-px bg-borde/15">
              <div className="bg-fondo px-3 py-2">
                <p className="font-body text-[10px] uppercase tracking-widest text-borde">Antes</p>
                <p className="font-body text-sm text-borde">{c.antes}</p>
              </div>
              <div className="bg-fondo px-3 py-2">
                <p className="font-body text-[10px] uppercase tracking-widest text-acento font-semibold">Después →</p>
                <p className="font-body text-sm text-texto font-semibold">{c.despues}</p>
              </div>
            </div>
            {c.porque && <p className="font-body text-xs text-borde px-3 py-2 border-t border-borde/15"><span className="text-texto">Por qué:</span> {c.porque}</p>}
          </li>
        ))}
      </ul>

      {entrada.notas?.length > 0 && (
        <div className="px-4 pb-4 flex flex-col gap-1.5">
          {entrada.notas.map((n) => (
            <p key={n} className="font-body text-xs text-borde">ℹ️ {n}</p>
          ))}
        </div>
      )}
    </div>
  )
}

function EntradaHistorial({ entrada }) {
  return (
    <li className="relative pl-6">
      <span className="absolute left-0 top-0.5 text-base" aria-hidden="true">{entrada.emoji}</span>
      <p className="font-body text-[11px] text-borde">{fechaLarga(entrada.fecha)}</p>
      <p className="font-body text-sm font-semibold text-texto">{entrada.titulo}</p>
      <ul className="mt-1 flex flex-col gap-1">
        {entrada.cambios.map((c) => (
          <li key={c} className="font-body text-sm text-borde flex gap-2">
            <span aria-hidden="true" className="text-acento">•</span>
            <span>{c}</span>
          </li>
        ))}
      </ul>
    </li>
  )
}

export default function ActualizacionesPage() {
  useEffect(() => {
    marcarActualizacionesVistas()
  }, [])

  // El historial agrupado por mes, en el orden en que viene (lo más reciente arriba).
  const porMes = []
  for (const entrada of HISTORIAL) {
    const mes = mesDe(entrada.fecha)
    const grupo = porMes.at(-1)
    if (grupo?.mes === mes) grupo.entradas.push(entrada)
    else porMes.push({ mes, entradas: [entrada] })
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-8 flex flex-col gap-6">
      <div>
        <h1 className="font-display text-3xl text-texto">📜 Actualizaciones</h1>
        <p className="font-body text-sm text-borde mt-1">
          Lo que va a cambiar y lo que ya ha cambiado en PronostiCup, explicado. Si algo no te cuadra o tienes una idea, dilo en el chat.
        </p>
      </div>

      {PROXIMAMENTE.map((e) => <Proximamente key={e.id} entrada={e} />)}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Lo que ya ha cambiado" />
        <div className="p-4 flex flex-col gap-6">
          {porMes.map((grupo) => (
            <section key={grupo.mes}>
              <h2 className="font-body text-[11px] uppercase tracking-widest text-borde font-semibold mb-3 border-b border-borde/20 pb-1">{grupo.mes}</h2>
              <ul className="flex flex-col gap-4">
                {grupo.entradas.map((e) => <EntradaHistorial key={e.id} entrada={e} />)}
              </ul>
            </section>
          ))}
        </div>
      </div>
    </div>
  )
}
