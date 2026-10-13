import { Link } from 'react-router-dom'

// Interruptor bajo el selector de jornada: "Pronosticar" (/jornadas/n) y
// "La liga" (/jornadas/n/liga, qué ha puesto cada uno). Misma jornada, dos vistas.
function PestanasJornada({ jornada, activa }) {
  const opciones = [
    { clave: 'pronosticar', texto: 'Pronosticar', to: `/jornadas/${jornada}` },
    { clave: 'liga', texto: '👥 La liga', to: `/jornadas/${jornada}/liga` },
  ]

  return (
    <div className="flex justify-center mb-3">
      <div className="inline-flex bg-borde/10 border border-borde/20 rounded-full p-1" role="tablist" aria-label="Vista de la jornada">
        {opciones.map((op) => (
          <Link
            key={op.clave}
            to={op.to}
            role="tab"
            aria-selected={activa === op.clave}
            className={`font-body text-sm px-4 py-1.5 rounded-full transition whitespace-nowrap ${
              activa === op.clave ? 'bg-acento text-fondo font-semibold shadow-sm' : 'text-texto hover:bg-borde/10'
            }`}
          >
            {op.texto}
          </Link>
        ))}
      </div>
    </div>
  )
}

export default PestanasJornada
