import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import TicketHeader from '../components/TicketHeader'

function ProfilePage() {
  const { usuario, refrescar } = useAuth()
  const [nombre, setNombre] = useState(usuario?.nombre ?? '')
  const [passwordActual, setPasswordActual] = useState('')
  const [passwordNueva, setPasswordNueva] = useState('')
  const [passwordConfirmar, setPasswordConfirmar] = useState('')
  const [mensajePassword, setMensajePassword] = useState(null)

  const { data: ligas } = useQuery({
    queryKey: ['mis-ligas'],
    queryFn: async () => (await client.get('/api/v1/ligas')).data.data,
  })

  const subirAvatar = useMutation({
    mutationFn: (archivo) => {
      const formData = new FormData()
      formData.append('avatar', archivo)
      return client.post('/api/v1/profile/avatar', formData)
    },
    onSuccess: () => refrescar(),
  })

  const guardarNombre = useMutation({
    mutationFn: () => client.patch('/api/v1/profile', { nombre_visible: nombre }),
    onSuccess: () => refrescar(),
  })

  const cambiarPassword = useMutation({
    mutationFn: () => client.post('/api/v1/profile/password', {
      current_password: passwordActual,
      password: passwordNueva,
      password_confirmation: passwordConfirmar,
    }),
    onSuccess: () => {
      setMensajePassword({ tipo: 'exito', texto: 'Contraseña actualizada correctamente.' })
      setPasswordActual('')
      setPasswordNueva('')
      setPasswordConfirmar('')
    },
    onError: (err) => {
      setMensajePassword({ tipo: 'error', texto: err.response?.data?.message ?? 'Error al cambiar la contraseña.' })
    },
  })

  function handleAvatarChange(event) {
    const archivo = event.target.files[0]
    if (archivo) subirAvatar.mutate(archivo)
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8 flex flex-col gap-6">
      {/* Avatar y nombre */}
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Mi perfil" />
        <div className="p-6 flex flex-col items-center gap-4">
          <label className="relative cursor-pointer group">
            {usuario?.avatar_url ? (
              <img src={usuario.avatar_url} alt={usuario.nombre} className="w-24 h-24 rounded-full object-cover border-2 border-acento" />
            ) : (
              <div className="w-24 h-24 rounded-full bg-acento/15 border-2 border-acento flex items-center justify-center">
                <span className="font-display text-3xl text-acento">{usuario?.nombre?.[0]?.toUpperCase()}</span>
              </div>
            )}
            <span className="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center font-body text-[10px] text-white">
              Cambiar
            </span>
            <input type="file" accept="image/*" className="hidden" onChange={handleAvatarChange} />
          </label>
          {subirAvatar.isPending && <p className="font-body text-xs text-borde">Subiendo foto...</p>}

          <div className="w-full flex flex-col gap-2 max-w-sm">
            <label className="font-body text-xs text-borde">Nombre visible</label>
            <div className="flex gap-2">
              <input
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                className="flex-1 font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
              />
              <button
                onClick={() => guardarNombre.mutate()}
                disabled={guardarNombre.isPending}
                className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 hover:brightness-110 disabled:opacity-50"
              >
                Guardar
              </button>
            </div>
            {guardarNombre.isSuccess && <p className="font-body text-xs text-acento">✓ Nombre actualizado</p>}
          </div>
        </div>
      </div>

      {/* Tus ligas */}
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Tus ligas" />
        <div className="p-4 flex flex-wrap gap-2">
          {ligas?.length === 0 && <p className="font-body text-sm text-borde">Aún no perteneces a ninguna liga.</p>}
          {ligas?.map((liga) => (
            <span key={liga.id} className="font-body text-sm text-texto border border-borde/30 rounded-full px-3 py-1">
              🏆 {liga.nombre}
            </span>
          ))}
        </div>
      </div>

      {/* Cambiar contraseña */}
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Cambiar contraseña" />
        <form
          onSubmit={(e) => { e.preventDefault(); setMensajePassword(null); cambiarPassword.mutate() }}
          className="p-4 flex flex-col gap-3 max-w-sm"
        >
          {mensajePassword && (
            <p className={`font-body text-xs px-3 py-2 rounded ${
              mensajePassword.tipo === 'exito' ? 'bg-acento/10 text-acento' : 'bg-red-500/10 text-red-500'
            }`}>
              {mensajePassword.texto}
            </p>
          )}
          <input
            type="password"
            placeholder="Contraseña actual"
            value={passwordActual}
            onChange={(e) => setPasswordActual(e.target.value)}
            className="font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
          />
          <input
            type="password"
            placeholder="Contraseña nueva"
            value={passwordNueva}
            onChange={(e) => setPasswordNueva(e.target.value)}
            className="font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
          />
          <input
            type="password"
            placeholder="Confirma la contraseña nueva"
            value={passwordConfirmar}
            onChange={(e) => setPasswordConfirmar(e.target.value)}
            className="font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
          />
          <button
            type="submit"
            disabled={cambiarPassword.isPending}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded py-2 hover:brightness-110 disabled:opacity-50"
          >
            {cambiarPassword.isPending ? 'Guardando...' : 'Cambiar contraseña'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default ProfilePage