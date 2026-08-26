import { useEffect, useState } from 'react'

function calcularRestante(fechaObjetivo) {
  const diferencia = new Date(fechaObjetivo).getTime() - Date.now()
  if (diferencia <= 0) return null

  const dias = Math.floor(diferencia / (1000 * 60 * 60 * 24))
  const horas = Math.floor((diferencia / (1000 * 60 * 60)) % 24)
  const minutos = Math.floor((diferencia / (1000 * 60)) % 60)

  return { dias, horas, minutos }
}

function Countdown({ fecha }) {
  const [restante, setRestante] = useState(() => calcularRestante(fecha))

  useEffect(() => {
    const intervalo = setInterval(() => setRestante(calcularRestante(fecha)), 30000)
    return () => clearInterval(intervalo)
  }, [fecha])

  if (!restante) return <span className="font-marcador text-sm text-red-500">¡Ya empezó!</span>

  return (
    <span className="font-marcador text-lg text-acento tabular-nums">
      {restante.dias > 0 && `${restante.dias}d `}
      {restante.horas}h {restante.minutos}m
    </span>
  )
}

export default Countdown