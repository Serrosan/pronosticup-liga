import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

function CreateLeagueForm() {
  const [nombre, setNombre] = useState('')
  const [tipo, setTipo] = useState('Normal')
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(null)
  const navigate = useNavigate()

  async function handleSubmit(event) {
    event.preventDefault()
    setEnviando(true)
    setError(null)
    try {
      const respuesta = await client.post('/api/v1/ligas', { nombre, tipo })
      navigate(`/ligas/${respuesta.data.data.id}/jornadas/1`)
    } catch (err) {
      setError(err.response?.data?.message ?? 'Error al crear la liga.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
      <div>
        <label className="font-body text-xs text-borde block mb-1">Nombre de la liga</label>
        <input
          value={nombre}
          onChange={(e) => setNombre(e.target.value)}
          placeholder="Ej. Liga de los Amigos"
          className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
        />
      </div>
      <div>
        <label className="font-body text-xs text-borde block mb-1">Tipo de liga</label>
        <div className="flex gap-3">
          <label className="font-body text-sm text-texto flex items-center gap-1.5">
            <input type="radio" checked={tipo === 'Normal'} onChange={() => setTipo('Normal')} />
            Normal
          </label>
          <label className="font-body text-sm text-texto flex items-center gap-1.5">
            <input type="radio" checked={tipo === 'ConExtras'} onChange={() => setTipo('ConExtras')} />
            Con extras (cartas)
          </label>
        </div>
      </div>
      {error && <p className="font-body text-xs text-red-500">{error}</p>}
      <button
        type="submit"
        disabled={enviando || !nombre}
        className="font-body font-semibold bg-acento text-fondo rounded py-2 hover:brightness-110 disabled:opacity-50"
      >
        {enviando ? 'Creando...' : 'Crear liga'}
      </button>
    </form>
  )
}

export default CreateLeagueForm