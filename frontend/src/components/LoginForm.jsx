import { useState } from 'react'
import { useAuth } from '../context/AuthContext'

function LoginForm() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const { login } = useAuth()

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    try {
      await login(email, password)
    } catch (err) {
      console.error(err)
      setError(err.response?.data?.message ?? 'Error desconocido, mira la consola.')
    }
  }

  return (
    <div className="flex items-center justify-center px-4 py-16">
      <form onSubmit={handleSubmit} className="w-full max-w-sm flex flex-col gap-3">
        <h2 className="font-display text-xl text-texto mb-2">Iniciar sesión</h2>

        {error && (
          <p className="font-body text-sm text-red-500 bg-red-500/10 border border-red-500/40 rounded px-3 py-2">
            {error}
          </p>
        )}

        <input
          type="email"
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="font-body bg-borde/20 text-texto placeholder:text-borde rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
        />
        <input
          type="password"
          placeholder="Contraseña"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="font-body bg-borde/20 text-texto placeholder:text-borde rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
        />
        <button
          type="submit"
          className="font-body font-semibold bg-acento text-fondo rounded py-2 mt-1 hover:brightness-110"
        >
          Entrar
        </button>
      </form>
    </div>
  )
}

export default LoginForm