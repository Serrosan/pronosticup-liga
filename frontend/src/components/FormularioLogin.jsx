import { useState } from 'react'
import { useAuth } from '../context/AuthContext'

function FormularioLogin() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const { login } = useAuth()

  async function manejarEnvio(evento) {
    evento.preventDefault()
    setError(null)
    try {
      await login(email, password)
    } catch (err) {
      console.error(err)
      setError(err.response?.data?.message ?? 'Error desconocido, mira la consola.')
    }
  }

  return (
    <form onSubmit={manejarEnvio}>
      <h2>Iniciar sesión</h2>
      {error && <p style={{ color: 'red' }}>{error}</p>}
      <div>
        <input
          type="email"
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
      </div>
      <div>
        <input
          type="password"
          placeholder="Contraseña"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </div>
      <button type="submit">Entrar</button>
    </form>
  )
}

export default FormularioLogin