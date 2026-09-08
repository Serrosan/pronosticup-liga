export function formatearActualizacion(fechaISO) {
  if (!fechaISO) return null

  const fecha = new Date(fechaISO)
  const minutos = Math.round((Date.now() - fecha) / 60000)

  if (minutos < 1) return 'Actualizado justo ahora'
  if (minutos < 60) return `Actualizado hace ${minutos} min`

  const horas = Math.round(minutos / 60)
  if (horas < 24) return `Actualizado hace ${horas}h`

  const dia = String(fecha.getDate()).padStart(2, '0')
  const mes = String(fecha.getMonth() + 1).padStart(2, '0')
  const horaStr = fecha.toTimeString().slice(0, 5)
  return `Actualizado el ${dia}/${mes} a las ${horaStr}`
}