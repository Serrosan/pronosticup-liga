import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import client from '../api/client'

function RegisterForm() {
  const [searchParams] = useSearchParams()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [codigoLiga, setCodigoLiga] = useState(searchParams.get('codigo') ?? '')
  const [errores, setErrores] = useState({})
  const [enviando, setEnviando] = useState(false)
  const [exito, setExito] = useState(false)
  const navigate = useNavigate()

  async function handleSubmit(event) {
    event.preventDefault()
    setErrores({})
    setEnviando(true)
    try {
      await client.get('/sanctum/csrf-cookie')
      await client.post('/api/v1/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        codigo_liga: codigoLiga || null,
      })
      setExito(true)
    } catch (err) {
      if (err.response?.status === 422) {
        setErrores(err.response.data.errors)
      } else {
        setErrores({ general: ['Error desconocido, mira la consola.'] })
        console.error(err)
      }
    } finally {
      setEnviando(false)
    }
  }

  if (exito) {
    return (
      <div className="w-full text-center">
        <h2 className="font-display text-2xl text-texto mb-2">¡Cuenta creada!</h2>
        <p className="font-body text-sm text-borde mb-6">
          Revisa tu email para activar la cuenta antes de entrar.
        </p>
        <button
          onClick={() => navigate('/login')}
          className="font-body font-semibold bg-acento text-fondo rounded py-2.5 px-6 hover:brightness-110 transition"
        >
          Ir a iniciar sesión
        </button>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="w-full flex flex-col gap-4">
      <div>
        <h2 className="font-display text-2xl text-texto">Crear cuenta</h2>
        <p className="font-body text-sm text-borde mt-1">Únete y empieza a pronosticar</p>
      </div>

      {errores.general && (
        <p className="font-body text-sm text-red-500 bg-red-500/10 border border-red-500/40 rounded px-3 py-2">
          {errores.general[0]}
        </p>
      )}

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Nombre</label>
        <input
          type="text"
          placeholder="Tu nombre"
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
        {errores.name && <p className="font-body text-xs text-red-500 mt-1">{errores.name[0]}</p>}
      </div>

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Email</label>
        <input
          type="email"
          placeholder="tu@email.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
        {errores.email && <p className="font-body text-xs text-red-500 mt-1">{errores.email[0]}</p>}
      </div>

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Contraseña</label>
        <input
          type="password"
          placeholder="Mínimo 8 caracteres"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
        {errores.password && <p className="font-body text-xs text-red-500 mt-1">{errores.password[0]}</p>}
      </div>

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Confirmar contraseña</label>
        <input
          type="password"
          placeholder="Repite la contraseña"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento"
        />
      </div>

      <div>
        <label className="font-body text-xs font-medium text-borde block mb-1">Código de liga (opcional)</label>
        <input
          type="text"
          placeholder="Ej. HLFHCA"
          value={codigoLiga}
          onChange={(e) => setCodigoLiga(e.target.value.toUpperCase())}
          className="w-full font-body bg-borde/10 text-texto placeholder:text-borde/60 rounded border border-borde/40 px-3 py-2.5 focus:outline-none focus:border-acento focus:ring-1 focus:ring-acento tracking-widest"
        />
        <p className="font-body text-xs text-borde mt-1">Pide el código al administrador de tu liga</p>
      </div>

      <button
        type="submit"
        disabled={enviando}
        className="font-body font-semibold bg-acento text-fondo rounded py-2.5 mt-1 hover:brightness-110 transition disabled:opacity-50"
      >
        {enviando ? 'Creando...' : 'Crear cuenta →'}
      </button>
    </form>
  )
}

export default RegisterForm