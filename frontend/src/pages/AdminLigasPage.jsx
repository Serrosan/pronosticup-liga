import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { Link } from 'react-router-dom'
import SelectTema from '../components/SelectTema'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'

function AdminLigasPage() {
  const { usuario } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [mostrarFormulario, setMostrarFormulario] = useState(false)
  const [nombre, setNombre] = useState('')
  const [tipo, setTipo] = useState('Normal')
  const [idCreador, setIdCreador] = useState('')

  const { data: ligas, isLoading, error } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/admin/ligas')
      return respuesta.data.data
    },
  })

  const { data: usuarios } = useQuery({
    queryKey: ['admin', 'usuarios'],
    queryFn: async () => (await client.get('/api/v1/admin/usuarios')).data.data,
    enabled: mostrarFormulario,
  })

  const crear = useMutation({
    mutationFn: (datos) => client.post('/api/v1/admin/ligas', datos),
    onSuccess: () => {
      toast.exito('Liga creada correctamente.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'ligas'] })
      setMostrarFormulario(false)
      setNombre('')
      setTipo('Normal')
      setIdCreador('')
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'Error al crear la liga.'),
  })

  const eliminar = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/admin/ligas/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ligas'] }),
  })

  function abrirFormulario() {
    setIdCreador(usuario?.id ?? '')
    setMostrarFormulario(true)
  }

  function handleSubmit(event) {
    event.preventDefault()
    crear.mutate({ nombre, tipo, id_usuario_creador: idCreador })
  }

  if (isLoading) return <p className="font-body text-texto p-4">Cargando ligas...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar ligas.</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h2 className="font-display text-xl text-texto">Ligas</h2>
        {!mostrarFormulario && (
          <button
            onClick={abrirFormulario}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110"
          >
            + Crear liga
          </button>
        )}
      </div>

      {mostrarFormulario && (
        <form onSubmit={handleSubmit} className="bg-borde/10 border border-borde/30 rounded-lg p-4 mb-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label className="font-body text-xs text-borde block mb-1">Nombre de la liga</label>
              <input
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                placeholder="Ej. Liga de los Amigos"
                className="w-full font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5 focus:outline-none focus:border-acento"
              />
            </div>

            <div>
              <label className="font-body text-xs text-borde block mb-1">Tipo</label>
              <SelectTema
                value={tipo}
                onChange={(e) => setTipo(e.target.value)}
                options={[
                  { value: 'Normal', label: 'Normal' },
                  { value: 'ConExtras', label: 'Con extras (cartas)' },
                ]}
                className="w-full bg-fondo"
              />
            </div>

            <div>
              <label className="font-body text-xs text-borde block mb-1">Creador / Admin</label>
              <SelectTema
                value={idCreador}
                onChange={(e) => setIdCreador(e.target.value)}
                options={usuarios?.map((u) => ({ value: u.id, label: u.name ?? u.email })) ?? []}
                className="w-full bg-fondo"
              />
            </div>
          </div>

          <div className="flex gap-2 mt-3">
            <button
              type="submit"
              disabled={crear.isPending || !nombre || !idCreador}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-50"
            >
              {crear.isPending ? 'Creando...' : 'Crear liga'}
            </button>
            <button
              type="button"
              onClick={() => setMostrarFormulario(false)}
              className="font-body text-sm text-borde hover:text-texto"
            >
              Cancelar
            </button>
          </div>
        </form>
      )}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Nombre</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Código</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Tipo</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Miembros</th>
              <th className="font-body text-xs text-borde uppercase px-4 py-2">Creador</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {ligas.map((liga) => (
              <tr key={liga.id} className="border-b border-borde/10 last:border-0">
                <td className="font-body text-sm text-texto px-4 py-2">{liga.nombre}</td>
                <td className="font-marcador text-sm text-acento px-4 py-2 tracking-widest">{liga.codigo_acceso}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.tipo}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.total_miembros}</td>
                <td className="font-body text-sm text-texto px-4 py-2">{liga.creador}</td>
                <td className="px-4 py-2 text-right whitespace-nowrap">
                  <Link to={`/admin/ligas/detalle/${liga.id}`} className="font-body text-xs text-premio hover:underline mr-3">
                    Editar detallado
                  </Link>
                  <button
                    onClick={() => { if (confirm('¿Eliminar esta liga?')) eliminar.mutate(liga.id) }}
                    className="font-body text-xs text-red-500 hover:underline"
                  >
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default AdminLigasPage
