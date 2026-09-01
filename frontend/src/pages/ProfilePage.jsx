import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import TicketHeader from '../components/TicketHeader'
import ConfirmModal from '../components/ConfirmModal'

function ProfilePage() {
  const { usuario, refrescar, logout } = useAuth()
  const navigate = useNavigate()
  const [nombre, setNombre] = useState(usuario?.nombre ?? '')
  const [passwordActual, setPasswordActual] = useState('')
  const [passwordNueva, setPasswordNueva] = useState('')
  const [passwordConfirmar, setPasswordConfirmar] = useState('')
  const [mensajePassword, setMensajePassword] = useState(null)
  const [mostrarDesactivar, setMostrarDesactivar] = useState(false)
  const [passwordDesactivar, setPasswordDesactivar] = useState('')
  const [errorDesactivar, setErrorDesactivar] = useState(null)

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

  const desactivarCuenta = useMutation({
    mutationFn: () => client.post('/api/v1/cuenta/desactivar', { password: passwordDesactivar }),
    onSuccess: () => {
      logout()
      navigate('/login')
    },
    onError: (err) => setErrorDesactivar(err.response?.data?.message ?? 'Error al desactivar la cuenta.'),
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
          <div>
            <input
              type="password"
              placeholder="Contraseña nueva"
              value={passwordNueva}
              onChange={(e) => setPasswordNueva(e.target.value)}
              className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 focus:outline-none focus:border-acento"
            />
            <p className="font-body text-[10px] text-borde mt-1">Mínimo 8 caracteres, con al menos una letra y un número</p>
          </div>
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

      {/* Zona peligrosa */}
      <div className="bg-fondo border border-red-500/30 rounded-lg overflow-hidden">
        <div className="bg-red-500/10 px-4 py-3">
          <p className="font-body text-xs uppercase tracking-widest text-red-500">Zona peligrosa</p>
        </div>
        <div className="p-4">
          <p className="font-body text-sm text-borde mb-3">
            Desactivar tu cuenta te desconecta y evita que puedas volver a iniciar sesión. Tus pronósticos e histórico se
            conservan (no se borran), y podrías reactivarla contactando con el administrador.
          </p>
          <button
            onClick={() => setMostrarDesactivar(true)}
            className="font-body text-sm font-semibold text-red-500 border border-red-500/40 rounded px-4 py-2 hover:bg-red-500/10"
          >
            Desactivar mi cuenta
          </button>
        </div>
      </div>

      {mostrarDesactivar && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
          <div className="bg-fondo border-2 border-red-500/40 rounded-lg p-6 max-w-sm w-full">
            <h2 className="font-display text-lg text-texto mb-2 text-center">¿Seguro que quieres desactivar tu cuenta?</h2>
            <p className="font-body text-sm text-borde mb-4 text-center">Introduce tu contraseña para confirmar.</p>
            {errorDesactivar && (
              <p className="font-body text-xs text-red-500 bg-red-500/10 rounded px-3 py-2 mb-3">{errorDesactivar}</p>
            )}
            <input
              type="password"
              placeholder="Tu contraseña"
              value={passwordDesactivar}
              onChange={(e) => setPasswordDesactivar(e.target.value)}
              className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 mb-4 focus:outline-none focus:border-red-500"
            />
            <div className="flex gap-3 justify-center">
              <button
                onClick={() => { setMostrarDesactivar(false); setPasswordDesactivar(''); setErrorDesactivar(null) }}
                className="font-body text-sm text-borde hover:text-texto px-4 py-2"
              >
                Cancelar
              </button>
              <button
                onClick={() => desactivarCuenta.mutate()}
                disabled={desactivarCuenta.isPending || !passwordDesactivar}
                className="font-body text-sm font-semibold bg-red-500 text-white rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
              >
                {desactivarCuenta.isPending ? 'Desactivando...' : 'Sí, desactivar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default ProfilePage