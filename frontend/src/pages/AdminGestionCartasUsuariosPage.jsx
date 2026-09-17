import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'
import CartaJuego from '../components/CartaJuego'
import { useToast } from '../context/ToastContext'

function AdminGestionCartasUsuariosPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [idLiga, setIdLiga] = useState('')
  const [idUsuario, setIdUsuario] = useState('')
  const [idTipoCartaElegido, setIdTipoCartaElegido] = useState('')

  const { data: ligas } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => (await client.get('/api/v1/admin/ligas')).data.data,
  })

  const { data: usuarios } = useQuery({
    queryKey: ['admin', 'usuarios'],
    queryFn: async () => (await client.get('/api/v1/admin/usuarios')).data.data,
  })

  const { data: tiposCarta } = useQuery({
    queryKey: ['admin', 'tipos-carta'],
    queryFn: async () => (await client.get('/api/v1/admin/tipos-carta')).data.data,
  })

  const { data: cartasEnMano } = useQuery({
    queryKey: ['admin', 'cartas-usuario', idLiga, idUsuario],
    queryFn: async () => (await client.get('/api/v1/admin/cartas-usuario', { params: { id_liga: idLiga, id_usuario: idUsuario } })).data.data,
    enabled: !!idLiga && !!idUsuario,
  })

  const darCarta = useMutation({
    mutationFn: () => client.post('/api/v1/admin/cartas-usuario', {
      id_liga: idLiga,
      id_usuario: idUsuario,
      id_tipo_carta: idTipoCartaElegido,
    }),
    onSuccess: () => {
      toast.exito('Carta entregada.')
      setIdTipoCartaElegido('')
      queryClient.invalidateQueries({ queryKey: ['admin', 'cartas-usuario', idLiga, idUsuario] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo dar la carta.'),
  })

  const retirarCarta = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/admin/cartas-usuario/${id}`),
    onSuccess: () => {
      toast.exito('Carta retirada.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'cartas-usuario', idLiga, idUsuario] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo retirar.'),
  })

  const ligasConExtras = (ligas ?? []).filter((l) => l.tipo === 'ConExtras')

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-1">Gestión de cartas de usuarios</h2>
      <p className="font-body text-xs text-borde mb-5">
        Para corregir fallos o dar/retirar una carta puntual, sin esperar al reparto semanal automático.
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 max-w-lg">
        <div>
          <label className="font-body text-xs text-borde block mb-1">Liga (solo "Con extras")</label>
          <SelectTema
            value={idLiga}
            onChange={(e) => { setIdLiga(e.target.value); setIdUsuario('') }}
            options={[{ value: '', label: 'Elige una liga...' }, ...ligasConExtras.map((l) => ({ value: String(l.id), label: l.nombre }))]}
            className="w-full bg-fondo"
          />
        </div>
        <div>
          <label className="font-body text-xs text-borde block mb-1">Usuario</label>
          <SelectTema
            value={idUsuario}
            onChange={(e) => setIdUsuario(e.target.value)}
            options={[{ value: '', label: 'Elige un usuario...' }, ...(usuarios ?? []).map((u) => ({ value: String(u.id), label: u.name ?? u.email }))]}
            className="w-full bg-fondo"
          />
        </div>
      </div>

      {idLiga && idUsuario && (
        <>
          <div className="bg-borde/5 border border-borde/20 rounded-lg p-4 mb-6 max-w-lg">
            <p className="font-body text-sm font-semibold text-texto mb-3">Dar una carta</p>
            <div className="flex flex-col sm:flex-row gap-2">
              <SelectTema
                value={idTipoCartaElegido}
                onChange={(e) => setIdTipoCartaElegido(e.target.value)}
                options={[
                  { value: '', label: 'Elige una carta del catálogo...' },
                  ...(tiposCarta ?? []).filter((t) => t.activa).map((t) => ({
                    value: String(t.id),
                    label: `${t.nombre} (${t.categoria_nombre} · ${t.rareza})`,
                  })),
                ]}
                className="flex-1 bg-fondo"
              />
              <button
                onClick={() => darCarta.mutate()}
                disabled={darCarta.isPending || !idTipoCartaElegido}
                className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50 shrink-0"
              >
                {darCarta.isPending ? 'Dando...' : '+ Dar carta'}
              </button>
            </div>
            <p className="font-body text-[11px] text-borde mt-2">
              El usuario recibirá un aviso en su campana de notificaciones.
            </p>
          </div>

          <p className="font-body text-sm font-semibold text-texto mb-3">Cartas activas en su mano</p>

          {!cartasEnMano ? (
            <p className="font-body text-sm text-borde">Cargando...</p>
          ) : cartasEnMano.length === 0 ? (
            <p className="font-body text-sm text-borde">Este usuario no tiene ninguna carta activa en esta liga ahora mismo.</p>
          ) : (
            <div className="flex flex-wrap gap-4">
              {cartasEnMano.map((carta) => (
                <div key={carta.id} className="flex flex-col items-center gap-2">
                  <CartaJuego carta={carta.tipo_carta} categoriaNombre={carta.tipo_carta.categoria.nombre} categoriaIcono={carta.tipo_carta.categoria.icono} tamano="pequena" />
                  <div className="text-center">
                    <p className="font-body text-[10px] text-borde">Origen: {carta.origen} · J{carta.jornada_obtenida}</p>
                    <button
                      onClick={() => retirarCarta.mutate(carta.id)}
                      disabled={retirarCarta.isPending}
                      className="font-body text-xs text-red-500 hover:underline mt-1"
                    >
                      Retirar
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  )
}

export default AdminGestionCartasUsuariosPage