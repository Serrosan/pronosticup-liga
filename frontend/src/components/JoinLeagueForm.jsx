import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'

function JoinLeagueForm() {
  const [codigo, setCodigo] = useState('')
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(null)
  const navigate = useNavigate()

  async function handleSubmit(event) {
    event.preventDefault()
    setEnviando(true)
    setError(null)
    try {
      const respuesta = await client.post('/api/v1/ligas/unirse', { codigo_acceso: codigo })
      navigate(`/ligas/${respuesta.data.data.id}/jornadas/1`)
    } catch (err) {
      setError(err.response?.data?.message ?? 'Código no válido.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
      <div>
        <label className="font-body text-xs text-borde block mb-1">Código de acceso</label>
        <input
          value={codigo}
          onChange={(e) => setCodigo(e.target.value.toUpperCase())}
          placeholder="Ej. HLFHCA"
          className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento tracking-widest"
        />
      </div>
      {error && <p className="font-body text-xs text-red-500">{error}</p>}
      <button
        type="submit"
        disabled={enviando || !codigo}
        className="font-body font-semibold bg-acento text-fondo rounded py-2 hover:brightness-110 disabled:opacity-50"
      >
        {enviando ? 'Uniéndote...' : 'Unirme a la liga'}
      </button>
    </form>
  )
}

export default JoinLeagueForm