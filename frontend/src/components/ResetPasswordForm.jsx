import { useState } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import client from '../api/client'

function ResetPasswordForm() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)

  const token = searchParams.get('token')
  const email = searchParams.get('email')

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setEnviando(true)
    try {
      await client.get('/sanctum/csrf-cookie')
      await client.post('/api/v1/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      navigate('/login')
    } catch (err) {
      setError(err.response?.data?.message ?? 'Error desconocido.')
    } finally {
      setEnviando(false)
    }
  }

  if (!token || !email) {
    return <p className="font-body text-red-500 p-4">Enlace no válido. Pide uno nuevo.</p>
  }

  return (
    <form onSubmit={handleSubmit} className="w-full max-w-sm flex flex-col gap-3">
      <h2 className="font-display text-xl text-texto mb-2">Nueva contraseña</h2>

      {error && (
        <p className="font-body text-sm text-red-500 bg-red-500/10 border border-red-500/40 rounded px-3 py-2">
          {error}
        </p>
      )}

      <input
        type="password"
        placeholder="Contraseña nueva"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        className="font-body bg-borde/20 text-texto placeholder:text-borde rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
      />
      <input
        type="password"
        placeholder="Confirma la contraseña"
        value={passwordConfirmation}
        onChange={(e) => setPasswordConfirmation(e.target.value)}
        className="font-body bg-borde/20 text-texto placeholder:text-borde rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
      />
      <button
        type="submit"
        disabled={enviando}
        className="font-body font-semibold bg-acento text-fondo rounded py-2 mt-1 hover:brightness-110 disabled:opacity-50"
      >
        {enviando ? 'Guardando...' : 'Restablecer contraseña'}
      </button>
    </form>
  )
}

export default ResetPasswordForm