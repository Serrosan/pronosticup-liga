const ESTILOS = {
  AciertoExacto: { simbolo: '●', clase: 'text-acento' },
  Acierto1x2: { simbolo: '●', clase: 'text-premio' },
  Fallo: { simbolo: '●', clase: 'text-borde/40' },
}

function FormaReciente({ eventos }) {
  if (!eventos || eventos.length === 0) {
    return <p className="font-body text-xs text-borde">Sin jornadas cerradas todavía</p>
  }

  return (
    <div className="flex items-center gap-1.5">
      {eventos.map((evento, i) => {
        const estilo = ESTILOS[evento.tipo_evento] ?? ESTILOS.Fallo
        return (
          <span key={i} className={`text-lg leading-none ${estilo.clase}`} title={`Jornada ${evento.jornada}: ${evento.tipo_evento}`}>
            {estilo.simbolo}
          </span>
        )
      })}
    </div>
  )
}

export default FormaReciente