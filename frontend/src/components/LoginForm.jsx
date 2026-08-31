import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

function LoginForm() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [enviando, setEnviando] = useState(false)
  const { login } = useAuth()

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setEnviando(true)
    try {
      await login(email.trim(), password)
    } catch (err) {
      console.error(err)
      setError(err.response?.data?.message ?? 'Error desconocido, mira la consola.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="w-full flex flex-col gap-4">
      <div>
        <h2 className="font-display text-2xl text-texto">Bienvenido</h2>
        <p className="font-body text-sm text-borde mt-1">Inicia sesión para ver tus pronósticos</p>
      </div>

      {error && (
        <p className="font-body text-sm text-red-500 bg-red-500/10 border border-red-500/40 rounded px-3 py-2">
          {error}
        </p>
      )}

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Email</label>
        <input
          type="email"
          placeholder="tu@email.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
      </div>

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Contraseña</label>
        <input
          type="password"
          placeholder="••••••••"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
      </div>

      <button
        type="submit"
        disabled={enviando}
        className="font-body font-semibold bg-acento text-fondo rounded py-2.5 mt-1 hover:brightness-110 transition disabled:opacity-50"
      >
        {enviando ? 'Entrando...' : 'Iniciar sesión →'}
      </button>

      <Link to="/forgot-password" className="font-body text-sm text-acento hover:underline text-center">
        ¿Olvidaste tu contraseña?
      </Link>
    </form>
  )
}

export default LoginForm