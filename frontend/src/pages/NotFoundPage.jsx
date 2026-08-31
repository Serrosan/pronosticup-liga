import { Link } from 'react-router-dom'

function NotFoundPage() {
  return (
    <div className="min-h-screen flex items-center justify-center px-4">
      <div className="text-center max-w-sm">
        <p className="font-display text-6xl text-acento mb-2">404</p>
        <h1 className="font-display text-xl text-texto mb-2">Fuera de juego</h1>
        <p className="font-body text-sm text-borde mb-6">
          Esta página no existe, o se ha movido de sitio — como un balón que se fue directo a la grada.
        </p>
        <Link to="/dashboard" className="inline-block bg-acento text-fondo font-body font-semibold text-sm rounded px-5 py-2.5 hover:brightness-110">
          Volver al inicio
        </Link>
      </div>
    </div>
  )
}

export default NotFoundPage