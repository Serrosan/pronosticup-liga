import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'

function AdminUsersPage() {
  const [creando, setCreando] = useState(false)
  const [editandoId, setEditandoId] = useState(null)
  const queryClient = useQueryClient()

  const { data: usuarios, isLoading, error } = useQuery({
    queryKey: ['admin', 'usuarios'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/admin/usuarios')
      return respuesta.data.data
    },
  })

  const invalidar = () => queryClient.invalidateQueries({ queryKey: ['admin', 'usuarios'] })

  const crear = useMutation({
    mutationFn: (datos) => client.post('/api/v1/admin/usuarios', datos),
    onSuccess: () => { invalidar(); setCreando(false) },
  })

  const editar = useMutation({
    mutationFn: ({ id, ...datos }) => client.put(`/api/v1/admin/usuarios/${id}`, datos),
    onSuccess: () => { invalidar(); setEditandoId(null) },
  })

  const activar = useMutation({
    mutationFn: (id) => client.post(`/api/v1/admin/usuarios/${id}/activar`),
    onSuccess: invalidar,
  })

  const cambiarRol = useMutation({
    mutationFn: ({ id, es_superadmin }) => client.patch(`/api/v1/admin/usuarios/${id}/rol`, { es_superadmin }),
    onSuccess: invalidar,
  })

  function handleCrear(event) {
    event.preventDefault()
    const datos = Object.fromEntries(new FormData(event.target).entries())
    crear.mutate(datos)
  }

  function handleEditar(event, id) {
    event.preventDefault()
    const datos = Object.fromEntries(new FormData(event.target).entries())
    editar.mutate({ id, ...datos })
  }

  if (isLoading) return <p className="font-body text-texto p-4">Cargando usuarios...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar usuarios.</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl text-texto">Usuarios</h2>
        <button
          onClick={() => setCreando(!creando)}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110"
        >
          + Añadir usuario
        </button>
      </div>

      {creando && (
        <form onSubmit={handleCrear} className="bg-borde/10 border border-borde/30 rounded-lg p-4 mb-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input name="name" placeholder="Nombre" required className="font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5" />
            <input name="email" type="email" placeholder="Email" required className="font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5" />
            <input name="password" type="text" placeholder="Contraseña inicial" required className="font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5" />
          </div>
          <p className="font-body text-xs text-borde mt-2">
            Se creará ya activado (sin pasar por email de verificación).
          </p>
          <div className="flex gap-2 mt-3">
            <button type="submit" disabled={crear.isPending} className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 disabled:opacity-50">
              Crear
            </button>
            <button type="button" onClick={() => setCreando(false)} className="font-body text-sm text-borde">Cancelar</button>
          </div>
        </form>
      )}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Nombre</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Email</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Ligas</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Activado</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Admin</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {usuarios.map((u) => (
              <>
                <tr key={u.id} className="border-b border-borde/10 last:border-0">
                  <td className="font-body text-sm text-texto px-4 py-2">{u.nombre}</td>
                  <td className="font-body text-sm text-texto px-4 py-2">{u.email}</td>
                  <td className="font-body text-sm text-texto px-4 py-2">{u.ligas?.join(', ') || '—'}</td>
                  <td className="px-4 py-2">
                    {u.activado ? (
                      <span className="font-body text-xs text-acento">✓ Sí</span>
                    ) : (
                      <button onClick={() => activar.mutate(u.id)} className="font-body text-xs text-premio hover:underline">
                        Activar ahora
                      </button>
                    )}
                  </td>
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={u.es_superadmin}
                      onChange={(e) => cambiarRol.mutate({ id: u.id, es_superadmin: e.target.checked })}
                    />
                  </td>
                  <td className="px-4 py-2 text-right">
                    <button
                      onClick={() => setEditandoId(editandoId === u.id ? null : u.id)}
                      className="font-body text-xs text-acento hover:underline"
                    >
                      Editar
                    </button>
                  </td>
                </tr>
                {editandoId === u.id && (
                  <tr key={`${u.id}-edit`}>
                    <td colSpan={6} className="px-4 pb-4">
                      <form onSubmit={(e) => handleEditar(e, u.id)} className="flex gap-2 items-end bg-borde/10 rounded p-3">
                        <div>
                          <label className="font-body text-xs text-borde block mb-1">Nombre</label>
                          <input name="name" defaultValue={u.nombre} className="font-body bg-fondo text-texto rounded border border-borde/40 px-2 py-1" />
                        </div>
                        <div>
                          <label className="font-body text-xs text-borde block mb-1">Email</label>
                          <input name="email" defaultValue={u.email} className="font-body bg-fondo text-texto rounded border border-borde/40 px-2 py-1" />
                        </div>
                        <button type="submit" className="font-body text-xs font-semibold bg-acento text-fondo rounded px-3 py-1.5">
                          Guardar
                        </button>
                      </form>
                    </td>
                  </tr>
                )}
              </>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default AdminUsersPage