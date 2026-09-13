import { LineChart, Line, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, CartesianGrid } from 'recharts'

const COLORES = ['#2F9E44', '#C97F17', '#5B6B7D', '#a855f7', '#ec4899', '#0ea5e9']
const MAX_LINEAS_DESTACADAS = 6

function EvolutionChart({ evolucion }) {
  if (!evolucion || evolucion.jornadas.length === 0) {
    return <p className="font-body text-sm text-borde py-8 text-center">Aún no hay jornadas cerradas para mostrar la evolución.</p>
  }

  const datos = evolucion.jornadas.map((jornada, i) => {
    const punto = { jornada: `J${jornada}` }
    evolucion.series.forEach((serie) => {
      punto[serie.usuario] = serie.datos[i]
    })
    return punto
  })

  // Si hay muchos participantes, ordenamos por puntos finales y destacamos
  // solo a los primeros — el resto se apaga en gris para no saturar la gráfica.
  const seriesOrdenadas = [...evolucion.series].sort((a, b) => {
    const ultimoA = a.datos[a.datos.length - 1] ?? 0
    const ultimoB = b.datos[b.datos.length - 1] ?? 0
    return ultimoB - ultimoA
  })

  const hayMuchasSeries = seriesOrdenadas.length > MAX_LINEAS_DESTACADAS
  const saltarEtiquetasEje = evolucion.jornadas.length > 12

  return (
    <div>
      <ResponsiveContainer width="100%" height={260}>
        <LineChart data={datos} margin={{ bottom: 4 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-borde)" opacity={0.15} />
          <XAxis
            dataKey="jornada"
            tick={{ fill: 'var(--color-borde)', fontSize: 11 }}
            axisLine={{ stroke: 'var(--color-borde)', opacity: 0.3 }}
            interval={saltarEtiquetasEje ? 'preserveStartEnd' : 0}
          />
          <YAxis tick={{ fill: 'var(--color-borde)', fontSize: 11 }} axisLine={{ stroke: 'var(--color-borde)', opacity: 0.3 }} />
          <Tooltip
            contentStyle={{ backgroundColor: 'var(--color-fondo)', border: '1px solid var(--color-borde)', borderRadius: 8, fontSize: 12 }}
          />
          <Legend
            wrapperStyle={{ fontSize: 11, fontFamily: 'var(--font-body)', color: 'var(--color-texto)', paddingTop: 8 }}
          />
          {seriesOrdenadas.map((serie, i) => {
            const destacada = !hayMuchasSeries || i < MAX_LINEAS_DESTACADAS

            return (
              <Line
                key={serie.usuario}
                type="monotone"
                dataKey={serie.usuario}
                stroke={destacada ? COLORES[i % COLORES.length] : 'var(--color-borde)'}
                strokeOpacity={destacada ? 1 : 0.35}
                strokeWidth={destacada ? 2 : 1}
                dot={{ r: destacada ? 3 : 2 }}
                activeDot={{ r: 5 }}
              />
            )
          })}
        </LineChart>
      </ResponsiveContainer>

      {hayMuchasSeries && (
        <p className="font-body text-[11px] text-borde text-center mt-1">
          Se destacan los {MAX_LINEAS_DESTACADAS} primeros — el resto aparece atenuado en gris.
        </p>
      )}
    </div>
  )
}

export default EvolutionChart