function EstadoVacio({ icono, titulo, texto }) {
  return (
    <div className="text-center py-10 px-4">
      <div className="text-4xl mb-3">{icono}</div>
      <p className="font-body text-sm font-semibold text-texto">{titulo}</p>
      {texto && <p className="font-body text-xs text-borde mt-1">{texto}</p>}
    </div>
  )
}

export default EstadoVacio