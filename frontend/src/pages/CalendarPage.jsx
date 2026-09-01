import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import client from '../api/client'

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const DIAS_SEMANA = ['L', 'M', 'X', 'J', 'V', 'S', 'D']

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function diasDelMes(anio, mes) {
  const primerDia = new Date(anio, mes - 1, 1)
  const ultimoDia = new Date(anio, mes, 0)
  const inicioSemana = (primerDia.getDay() + 6) % 7

  const dias = []
  for (let i = 0; i < inicioSemana; i++) dias.push(null)
  for (let d = 1; d <= ultimoDia.getDate(); d++) dias.push(d)
  return dias
}

function CalendarPage() {
  const hoy = new Date()
  const [anio, setAnio] = useState(hoy.getFullYear())
  const [mes, setMes] = useState(hoy.getMonth() + 1)
  const [diaSeleccionado, setDiaSeleccionado] = useState(null)

  const { data } = useQuery({
    queryKey: ['calendario', anio, mes],
    queryFn: async () => (await client.get('/api/v1/calendario', { params: { anio, mes } })).data.data,
    placeholderData: (datosAnteriores) => datosAnteriores,
  })

  function cambiarMes(delta) {
    let nuevoMes = mes + delta
    let nuevoAnio = anio
    if (nuevoMes > 12) { nuevoMes = 1; nuevoAnio++ }
    if (nuevoMes < 1) { nuevoMes = 12; nuevoAnio-- }
    setMes(nuevoMes)
    setAnio(nuevoAnio)
    setDiaSeleccionado(null)
  }

  function fechaDe(dia) {
    return `${anio}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`
  }

  function eventosDelDia(dia) {
    if (!dia || !data) return []
    const fecha = fechaDe(dia)
    return data.eventos.filter((e) => fecha >= e.fecha_inicio.slice(0, 10) && fecha <= e.fecha_fin.slice(0, 10))
  }

  function jornadasDelDia(dia) {
    if (!dia || !data) return []
    return data.jornadas_por_dia?.[fechaDe(dia)] ?? []
  }

  function partidosDelDia(dia) {
    if (!dia || !data) return []
    return data.partidos_por_dia?.[fechaDe(dia)] ?? []
  }

  const partidosSeleccionados = diaSeleccionado ? partidosDelDia(diaSeleccionado) : []
  const eventosSeleccionados = diaSeleccionado ? eventosDelDia(diaSeleccionado) : []

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="flex items-center justify-center gap-4 mb-6">
        <button onClick={() => cambiarMes(-1)} className="font-body text-texto hover:text-acento text-xl px-2">←</button>
        <h1 className="font-display text-xl text-texto w-48 text-center">{MESES[mes - 1]} {anio}</h1>
        <button onClick={() => cambiarMes(1)} className="font-body text-texto hover:text-acento text-xl px-2">→</button>
      </div>

      {!data ? (
        <p className="font-body text-texto text-center">Cargando...</p>
      ) : (
        <div className="bg-fondo border border-borde/30 rounded-lg p-4">
          <div className="grid grid-cols-7 gap-1 mb-2">
            {DIAS_SEMANA.map((d, i) => (
              <p key={d} className={`font-body text-xs text-center font-semibold ${i >= 5 ? 'text-premio' : 'text-borde'}`}>{d}</p>
            ))}
          </div>
          <div className="grid grid-cols-7 gap-1">
            {diasDelMes(anio, mes).map((dia, i) => {
              const jornadas = jornadasDelDia(dia)
              const eventos = eventosDelDia(dia)
              const diaSemana = i % 7
              const esFinde = diaSemana >= 5
              const esHoy = dia === hoy.getDate() && mes === hoy.getMonth() + 1 && anio === hoy.getFullYear()
              const esSeleccionado = dia !== null && dia === diaSeleccionado
              const fechaDia = dia ? new Date(anio, mes - 1, dia) : null
              const yaPaso = fechaDia && fechaDia < new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())
              const tieneContenido = jornadas.length > 0 || eventos.length > 0

              return (
                <button
                  key={i}
                  type="button"
                  disabled={!dia}
                  onClick={() => setDiaSeleccionado(dia === diaSeleccionado ? null : dia)}
                  className={`aspect-square rounded-lg border flex flex-col items-center justify-center gap-0.5 p-1 ${
                    dia ? 'border-borde/20' : 'border-transparent cursor-default'
                  } ${esFinde && dia ? 'bg-premio/5' : ''} ${esHoy ? 'border-acento border-2' : ''} ${yaPaso ? 'opacity-50' : ''} ${
                    esSeleccionado ? 'ring-2 ring-acento' : ''
                  } ${tieneContenido ? 'hover:bg-borde/10' : ''}`}
                >
                  {dia && (
                    <>
                      <span className="font-body text-xs text-texto">{dia}</span>
                      <div className="flex gap-0.5 flex-wrap justify-center">
                        {jornadas.map((j) => (
                          <span key={j} className="font-marcador text-[11px] font-bold bg-acento text-fondo rounded px-1.5 py-0.5 leading-tight">
                            J{j}
                          </span>
                        ))}
                        {eventos.map((e) => (
                          <span key={e.id} className="w-2 h-2 rounded-full" style={{ backgroundColor: e.color }} title={e.titulo} />
                        ))}
                      </div>
                    </>
                  )}
                </button>
              )
            })}
          </div>
        </div>
      )}

      {diaSeleccionado && (
        <div className="mt-4 bg-fondo border border-borde/30 rounded-lg p-4">
          <p className="font-body text-sm font-semibold text-texto mb-3">
            {diaSeleccionado} de {MESES[mes - 1]}
          </p>

          {eventosSeleccionados.map((e) => (
            <div key={e.id} className="flex items-center gap-2 mb-3 rounded px-3 py-2" style={{ backgroundColor: `${e.color}1A` }}>
              <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: e.color }} />
              <span className="font-body text-sm text-texto">{e.titulo}</span>
            </div>
          ))}

          {partidosSeleccionados.length === 0 && eventosSeleccionados.length === 0 && (
            <p className="font-body text-sm text-borde">Sin partidos ni eventos este día.</p>
          )}

          {partidosSeleccionados.map((p) => (
            <Link
              key={p.id}
              to={`/jornadas/${p.jornada}`}
              className="flex items-center justify-between gap-2 py-2 border-b border-borde/10 last:border-0 hover:bg-borde/5 rounded px-2 -mx-2"
            >
              <div className="flex items-center gap-2 min-w-0 flex-1">
                <Escudo url={p.escudo_local} alt={p.equipo_local} />
                <p className="font-body text-sm text-texto truncate">{p.equipo_local} <span className="text-borde">vs</span> {p.equipo_visitante}</p>
                <Escudo url={p.escudo_visitante} alt={p.equipo_visitante} />
              </div>
              <span className="font-marcador text-xs text-borde shrink-0">{p.hora}</span>
            </Link>
          ))}
        </div>
      )}

      {data?.eventos.length > 0 && (
        <div className="mt-4 flex flex-wrap gap-2">
          {data.eventos.map((e) => (
            <span key={e.id} className="font-body text-xs text-borde flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full" style={{ backgroundColor: e.color }} /> {e.titulo}
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

export default CalendarPage