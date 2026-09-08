import { useEffect, useState } from 'react'
import { useParams, useLocation, Link } from 'react-router-dom'
import client from '../api/client'

function VerifyEmailPage() {
  const { id, hash } = useParams()
  const location = useLocation()
  const [estado, setEstado] = useState('verificando')
  const [mensaje, setMensaje] = useState('')

  useEffect(() => {
    client.get(`/api/v1/verify-email/${id}/${hash}${location.search}`)
      .then(() => {
        setEstado('exito')
        setMensaje('Tu cuenta ha sido activada correctamente.')
      })
      .catch((err) => {
        setEstado('error')
        setMensaje(err.response?.data?.message ?? 'No se pudo verificar tu cuenta. El enlace puede haber caducado.')
      })
  }, [id, hash, location.search])

  return (
    <div className="min-h-screen flex items-center justify-center px-4">
      <div className="max-w-sm w-full bg-fondo border border-borde/30 rounded-lg p-8 text-center">
        {estado === 'verificando' && (
          <>
            <div className="text-4xl mb-4">⏳</div>
            <h1 className="font-display text-xl text-texto mb-2">Verificando tu cuenta...</h1>
            <p className="font-body text-sm text-borde">Un momento, por favor.</p>
          </>
        )}

        {estado === 'exito' && (
          <>
            <div className="text-4xl mb-4">✅</div>
            <h1 className="font-display text-xl text-texto mb-2">¡Cuenta activada!</h1>
            <p className="font-body text-sm text-borde mb-6">{mensaje}</p>
            <Link
              to="/login"
              className="inline-block font-body text-sm font-semibold bg-acento text-fondo rounded-full px-6 py-2.5 hover:brightness-110"
            >
              Iniciar sesión →
            </Link>
          </>
        )}

        {estado === 'error' && (
          <>
            <div className="text-4xl mb-4">⚠️</div>
            <h1 className="font-display text-xl text-texto mb-2">No se pudo verificar</h1>
            <p className="font-body text-sm text-borde mb-6">{mensaje}</p>
            <Link
              to="/login"
              className="inline-block font-body text-sm text-acento hover:underline"
            >
              Volver al inicio
            </Link>
          </>
        )}
      </div>
    </div>
  )
}

export default VerifyEmailPage