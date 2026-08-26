import { useState } from 'react'
import client from '../api/client'

function ForgotPasswordForm() {
  const [email, setEmail] = useState('')
  const [mensaje, setMensaje] = useState(null)
  const [enviando, setEnviando] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setEnviando(true)
    try {
      await client.get('/sanctum/csrf-cookie')
      const respuesta = await client.post('/api/v1/forgot-password', { email })
      setMensaje(respuesta.data.message)
    } catch (err) {
      console.error(err)
      setMensaje('Ha ocurrido un error, inténtalo de nuevo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="w-full max-w-sm flex flex-col gap-3">
      <h2 className="font-display text-xl text-texto mb-2">Recuperar contraseña</h2>
      <p className="font-body text-sm text-borde">
        Escribe tu email y te mandamos un enlace para restablecerla.
      </p>

      {mensaje && (
        <p className="font-body text-sm text-acento bg-acento/10 border border-acento/40 rounded px-3 py-2">
          {mensaje}
        </p>
      )}

      <input
        type="email"
        placeholder="Email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        className="font-body bg-borde/20 text-texto placeholder:text-borde rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
      />
      <button
        type="submit"
        disabled={enviando}
        className="font-body font-semibold bg-acento text-fondo rounded py-2 mt-1 hover:brightness-110 disabled:opacity-50"
      >
        {enviando ? 'Enviando...' : 'Enviar enlace'}
      </button>
    </form>
  )
}

export default ForgotPasswordForm