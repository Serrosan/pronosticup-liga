import { useEffect, useState } from 'react'
import { useParams, Navigate, Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import client from '../api/client'

function JoinByLinkPage() {
  const { codigo } = useParams()
  const { usuario } = useAuth()
  const [estado, setEstado] = useState('procesando')
  const [ligaId, setLigaId] = useState(null)
  const [mensaje, setMensaje] = useState(null)

  useEffect(() => {
    if (!usuario) return

    client.post('/api/v1/ligas/unirse', { codigo_acceso: codigo })
      .then((respuesta) => {
        setLigaId(respuesta.data.data.id)
        setEstado('exito')
      })
      .catch((err) => {
        setMensaje(err.response?.data?.message ?? 'No se pudo unir a la liga.')
        setEstado('error')
      })
  }, [usuario, codigo])

  if (!usuario) return <Navigate to={`/register?codigo=${codigo}`} replace />
  if (estado === 'exito') return <Navigate to={`/ligas/${ligaId}/jornadas/1`} replace />

  return (
    <div className="min-h-screen bg-fondo flex items-center justify-center px-4">
      <div className="text-center">
        {estado === 'procesando' && <p className="font-body text-texto">Uniéndote a la liga...</p>}
        {estado === 'error' && (
          <div>
            <p className="font-body text-red-500 mb-3">{mensaje}</p>
            <Link to="/" className="font-body text-acento hover:underline">Ir al inicio</Link>
          </div>
        )}
      </div>
    </div>
  )
}

export default JoinByLinkPage