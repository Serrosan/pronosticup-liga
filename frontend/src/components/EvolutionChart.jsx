import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts'

const COLORES = ['#2F9E44', '#C97F17', '#5B6B7D', '#a855f7', '#ec4899', '#0ea5e9']

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

  return (
    <ResponsiveContainer width="100%" height={220}>
      <LineChart data={datos}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--color-borde)" opacity={0.15} />
        <XAxis dataKey="jornada" tick={{ fill: 'var(--color-borde)', fontSize: 11 }} axisLine={{ stroke: 'var(--color-borde)', opacity: 0.3 }} />
        <YAxis tick={{ fill: 'var(--color-borde)', fontSize: 11 }} axisLine={{ stroke: 'var(--color-borde)', opacity: 0.3 }} />
        <Tooltip
          contentStyle={{ backgroundColor: 'var(--color-fondo)', border: '1px solid var(--color-borde)', borderRadius: 8, fontSize: 12 }}
        />
        {evolucion.series.map((serie, i) => (
          <Line
            key={serie.usuario}
            type="monotone"
            dataKey={serie.usuario}
            stroke={COLORES[i % COLORES.length]}
            strokeWidth={2}
            dot={{ r: 3 }}
            activeDot={{ r: 5 }}
          />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
}

export default EvolutionChart