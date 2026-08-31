import useConexion from '../hooks/useConexion'

function AvisoSinConexion() {
  const conectado = useConexion()

  if (conectado) return null

  return (
    <div className="fixed top-0 left-0 right-0 bg-red-500 text-white text-center py-2 z-50">
      <p className="font-body text-sm font-semibold">📡 Sin conexión a internet — algunas acciones no funcionarán hasta que vuelvas a conectarte</p>
    </div>
  )
}

export default AvisoSinConexion