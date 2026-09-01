import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from './SelectTema'
import CampoImagenSubida from './CampoImagenSubida'
import ConfirmModal from './ConfirmModal'
import { useToast } from '../context/ToastContext'

const POR_PAGINA = 25

function valorParaCampo(field, valor) {
  if (field.type === 'date' && valor) return String(valor).slice(0, 10)
  return valor ?? ''
}

function AdminResourceTable({ resource, title, columns, fields }) {
  const [editando, setEditando] = useState(null)
  const [busqueda, setBusqueda] = useState('')
  const [pagina, setPagina] = useState(1)
  const [errorGuardado, setErrorGuardado] = useState(null)
  const [aEliminar, setAEliminar] = useState(null)
  const queryClient = useQueryClient()
  const toast = useToast()

  const { data: items, isLoading, error } = useQuery({
    queryKey: ['admin', resource],
    queryFn: async () => {
      const respuesta = await client.get(`/api/v1/admin/${resource}`)
      return respuesta.data.data
    },
  })

  const guardar = useMutation({
    mutationFn: (datos) =>
      datos.id
        ? client.put(`/api/v1/admin/${resource}/${datos.id}`, datos)
        : client.post(`/api/v1/admin/${resource}`, datos),
    onSuccess: () => {
      toast.exito('Guardado correctamente.')
      queryClient.invalidateQueries({ queryKey: ['admin', resource] })
      setEditando(null)
      setErrorGuardado(null)
    },
    onError: (err) => {
      if (err.response?.status === 422) {
        const mensajes = Object.values(err.response.data.errors ?? {}).flat()
        setErrorGuardado(mensajes.join(' · ') || 'Revisa los campos, hay algún dato no válido.')
      } else {
        setErrorGuardado(err.response?.data?.message ?? 'Error al guardar.')
      }
    },
  })

  const eliminar = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/admin/${resource}/${id}`),
    onSuccess: () => {
      toast.exito('Eliminado correctamente.')
      queryClient.invalidateQueries({ queryKey: ['admin', resource] })
    },
  })

  function handleSubmit(event) {
    event.preventDefault()
    setErrorGuardado(null)
    const datos = Object.fromEntries(new FormData(event.target).entries())
    if (editando?.id) datos.id = editando.id
    guardar.mutate(datos)
  }

  function handleBusqueda(valor) {
    setBusqueda(valor)
    setPagina(1)
  }

  function abrirEdicion(item) {
    setErrorGuardado(null)
    setEditando(item)
  }

  function actualizarCampoImagen(campo, url) {
    setEditando((prev) => ({ ...prev, [campo]: url }))
  }

  function confirmarEliminar() {
    eliminar.mutate(aEliminar.id)
    setAEliminar(null)
  }

  if (isLoading) return <p className="font-body text-texto p-4">Cargando {title}...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar {title}.</p>

  const normalizar = (texto) => String(texto ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

  const itemsFiltrados = items.filter((item) => {
    if (!busqueda) return true
    const termino = normalizar(busqueda)
    return columns.some((col) => normalizar(item[col.key]).includes(termino)) || String(item.id).includes(busqueda)
  })

  const totalPaginas = Math.max(1, Math.ceil(itemsFiltrados.length / POR_PAGINA))
  const itemsPagina = itemsFiltrados.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  return (
    <div>
      <div className="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <h2 className="font-display text-xl text-texto">{title}</h2>
        <div className="flex items-center gap-2">
          <input
            type="text"
            placeholder="Buscar..."
            value={busqueda}
            onChange={(e) => handleBusqueda(e.target.value)}
            className="font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-3 py-1.5 w-48"
          />
          <button
            onClick={() => abrirEdicion({})}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 whitespace-nowrap"
          >
            + Añadir
          </button>
        </div>
      </div>

      {editando && (
        <form onSubmit={handleSubmit} className="bg-borde/10 border border-borde/30 rounded-lg p-4 mb-4">
          <p className="font-body text-xs text-borde mb-3">Edición rápida — para el resto de campos, usa "Editar detallado" en la fila.</p>

          {errorGuardado && (
            <p className="font-body text-xs text-red-500 bg-red-500/10 rounded px-3 py-2 mb-3">{errorGuardado}</p>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {fields.map((field) => (
              <div key={field.name}>
                <label className="font-body text-xs text-borde block mb-1">{field.label}</label>
                {field.type === 'select' ? (
                  <SelectTema
                    name={field.name}
                    defaultValue={valorParaCampo(field, editando[field.name])}
                    options={field.options}
                    className="w-full bg-fondo"
                  />
                ) : field.type === 'color' ? (
                  <input
                    type="color"
                    name={field.name}
                    defaultValue={editando[field.name] || '#FFB238'}
                    className="w-10 h-9 rounded border border-borde/40 shrink-0"
                  />
                ) : field.type === 'imagen' ? (
                  <>
                    <input type="hidden" name={field.name} value={editando[field.name] ?? ''} />
                    <CampoImagenSubida
                      label=""
                      valor={editando[field.name]}
                      onChange={(url) => actualizarCampoImagen(field.name, url)}
                      carpeta={field.carpeta ?? 'equipos'}
                    />
                  </>
                ) : (
                  <input
                    name={field.name}
                    type={field.type ?? 'text'}
                    defaultValue={valorParaCampo(field, editando[field.name])}
                    className="w-full font-body bg-fondo text-texto rounded border border-borde/40 px-3 py-1.5 focus:outline-none focus:border-acento"
                  />
                )}
              </div>
            ))}
          </div>
          <div className="flex gap-2 mt-3">
            <button
              type="submit"
              disabled={guardar.isPending}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-3 py-1.5 hover:brightness-110 disabled:opacity-50"
            >
              {guardar.isPending ? 'Guardando...' : 'Guardar'}
            </button>
            <button type="button" onClick={() => { setEditando(null); setErrorGuardado(null) }} className="font-body text-sm text-borde hover:text-texto">
              Cancelar
            </button>
          </div>
        </form>
      )}

      <p className="font-body text-xs text-borde mb-2">{itemsFiltrados.length} de {items.length}</p>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-borde/30">
              <th className="font-body text-xs text-borde uppercase px-4 py-2 w-16">ID</th>
              {columns.map((col) => (
                <th key={col.key} className="font-body text-xs text-borde uppercase px-4 py-2">{col.label}</th>
              ))}
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {itemsPagina.map((item) => (
              <tr key={item.id} className="border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <td className="font-marcador text-xs text-borde px-4 py-2">{item.id}</td>
                {columns.map((col) => (
                  <td key={col.key} className="font-body text-sm text-texto px-4 py-2">{item[col.key]}</td>
                ))}
                <td className="px-4 py-2 text-right whitespace-nowrap">
                  <Link to={`/admin/${resource}/detalle/${item.id}`} className="font-body text-xs text-premio hover:underline mr-3">
                    Editar detallado
                  </Link>
                  <button onClick={() => abrirEdicion(item)} className="font-body text-xs text-acento hover:underline mr-3">
                    Edición rápida
                  </button>
                  <button
                    onClick={() => setAEliminar(item)}
                    className="font-body text-xs text-red-500 hover:underline"
                  >
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
            {itemsFiltrados.length === 0 && (
              <tr>
                <td colSpan={columns.length + 2} className="font-body text-sm text-borde text-center px-4 py-6">
                  Sin resultados para "{busqueda}"
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {totalPaginas > 1 && (
        <div className="flex items-center justify-center gap-3 mt-4">
          <button
            onClick={() => setPagina((p) => Math.max(1, p - 1))}
            disabled={pagina === 1}
            className="font-body text-sm text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento"
          >
            ← Anterior
          </button>
          <span className="font-body text-sm text-borde">Página {pagina} de {totalPaginas}</span>
          <button
            onClick={() => setPagina((p) => Math.min(totalPaginas, p + 1))}
            disabled={pagina === totalPaginas}
            className="font-body text-sm text-texto disabled:opacity-30 disabled:cursor-not-allowed hover:text-acento"
          >
            Siguiente →
          </button>
        </div>
      )}

      <ConfirmModal
        abierto={!!aEliminar}
        titulo="¿Eliminar este registro?"
        mensaje="Esta acción no se puede deshacer."
        onConfirmar={confirmarEliminar}
        onCancelar={() => setAEliminar(null)}
      />
    </div>
  )
}

export default AdminResourceTable