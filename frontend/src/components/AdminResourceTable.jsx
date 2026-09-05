import { useState, useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router-dom'
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

function IconoCopiar() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="9" y="9" width="13" height="13" rx="2" />
      <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
    </svg>
  )
}

function IconoEditarDetallado() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
      <path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
    </svg>
  )
}

function IconoEdicionRapida() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
    </svg>
  )
}

function IconoEliminar() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M3 6h18" />
      <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
    </svg>
  )
}

function BotonAccion({ icono, onClick, titulo, color = 'text-borde hover:text-texto' }) {
  return (
    <button onClick={onClick} title={titulo} className={`p-1.5 rounded transition ${color} hover:bg-borde/10`}>
      {icono}
    </button>
  )
}

function AdminResourceTable({ resource, title, columns, fields, irADetalleTrasCrear = false, filtros = [] }) {
  const claveFiltroGuardado = `filtro-admin-${resource}`
  const [editando, setEditando] = useState(null)
  const [busqueda, setBusqueda] = useState('')
  const [pagina, setPagina] = useState(1)
  const [errorGuardado, setErrorGuardado] = useState(null)
  const [aEliminar, setAEliminar] = useState(null)
  const [valoresFiltro, setValoresFiltro] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(claveFiltroGuardado)) ?? {}
    } catch {
      return {}
    }
  })
  const [orden, setOrden] = useState({ campo: null, direccion: 'asc' })
  const inputBusquedaRef = useRef(null)
  const queryClient = useQueryClient()
  const toast = useToast()
  const navigate = useNavigate()

  useEffect(() => {
    function alPulsarTecla(evento) {
      const dentroDeCampo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
      if (evento.key === '/' && !dentroDeCampo) {
        evento.preventDefault()
        inputBusquedaRef.current?.focus()
      }
    }
    window.addEventListener('keydown', alPulsarTecla)
    return () => window.removeEventListener('keydown', alPulsarTecla)
  }, [])

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
    onSuccess: (respuesta, datosEnviados) => {
      toast.exito('Guardado correctamente.')
      queryClient.invalidateQueries({ queryKey: ['admin', resource] })
      setEditando(null)
      setErrorGuardado(null)

      const fueCreacion = !datosEnviados.id
      if (fueCreacion && irADetalleTrasCrear) {
        navigate(`/admin/${resource}/detalle/${respuesta.data.data.id}`)
      }
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

  function cambiarSelect(campo, valor) {
    setValoresFiltro((prev) => {
      const nuevo = { ...prev, [campo]: valor }
      localStorage.setItem(claveFiltroGuardado, JSON.stringify(nuevo))
      return nuevo
    })
    setPagina(1)
  }

  function cambiarOrden(campo) {
    setOrden((prev) => {
      if (prev.campo !== campo) return { campo, direccion: 'asc' }
      if (prev.direccion === 'asc') return { campo, direccion: 'desc' }
      return { campo: null, direccion: 'asc' }
    })
    setPagina(1)
  }

  function copiarFila(item) {
    const texto = columns.map((col) => `${col.label}: ${item[col.key] ?? '—'}`).join('\n')
    navigator.clipboard.writeText(texto)
    toast.exito('Fila copiada al portapapeles.')
  }

  if (isLoading) return <p className="font-body text-texto p-4">Cargando {title}...</p>
  if (error) return <p className="font-body text-red-500 p-4">Error al cargar {title}.</p>

  const normalizar = (texto) => String(texto ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

  let itemsFiltrados = items
    .filter((item) => filtros.every((f) => {
      const valorActivo = valoresFiltro[f.campo]
      if (!valorActivo) return true
      return normalizar(item[f.campo]) === normalizar(valorActivo)
    }))
    .filter((item) => {
      if (!busqueda) return true
      const termino = normalizar(busqueda)
      return columns.some((col) => normalizar(item[col.key]).includes(termino)) || String(item.id).includes(busqueda)
    })

  if (orden.campo) {
    itemsFiltrados = [...itemsFiltrados].sort((a, b) => {
      const valorA = a[orden.campo]
      const valorB = b[orden.campo]

      if (valorA == null && valorB == null) return 0
      if (valorA == null) return 1
      if (valorB == null) return -1

      const sonNumeros = typeof valorA === 'number' && typeof valorB === 'number'
      const comparacion = sonNumeros
        ? valorA - valorB
        : normalizar(valorA).localeCompare(normalizar(valorB))

      return orden.direccion === 'asc' ? comparacion : -comparacion
    })
  }

  const totalPaginas = Math.max(1, Math.ceil(itemsFiltrados.length / POR_PAGINA))
  const itemsPagina = itemsFiltrados.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  return (
    <div>
      <div className="flex items-center justify-between mb-3 gap-3 flex-wrap">
        <h2 className="font-display text-xl text-texto">{title}</h2>
        <div className="flex items-center gap-2">
          <input
            ref={inputBusquedaRef}
            type="text"
            placeholder="Buscar... (pulsa /)"
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

      {filtros.length > 0 && (
        <div className="flex flex-wrap items-center gap-3 mb-4 bg-borde/5 border border-borde/20 rounded-lg px-3 py-2.5">
          {filtros.map((f) => {
            const opcionesDinamicas = f.opciones ?? [...new Set(items.map((i) => i[f.campo]).filter(Boolean))].sort()
            return (
              <div key={f.campo} className="flex items-center gap-2">
                <span className="font-body text-xs text-borde whitespace-nowrap">{f.label}</span>
                <SelectTema
                  value={valoresFiltro[f.campo] ?? ''}
                  onChange={(e) => cambiarSelect(f.campo, e.target.value)}
                  options={[{ value: '', label: 'Todos' }, ...opcionesDinamicas.map((op) => ({ value: op, label: op }))]}
                  className="bg-fondo text-sm min-w-[130px]"
                />
              </div>
            )
          })}
        </div>
      )}

      {editando && (
        <form onSubmit={handleSubmit} className="bg-borde/10 border border-borde/30 rounded-lg p-4 mb-4">
          <p className="font-body text-xs text-borde mb-3">
            {editando.id ? 'Edición rápida — para el resto de campos, usa "Editar detallado" en la fila.' : irADetalleTrasCrear ? 'Solo lo esencial — tras guardar irás directo a la ficha completa para rellenar el resto y ficharlo.' : 'Edición rápida.'}
          </p>

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
              <th
                onClick={() => cambiarOrden('id')}
                className="font-body text-xs text-borde uppercase px-4 py-2 w-16 cursor-pointer select-none hover:text-texto whitespace-nowrap"
              >
                ID {orden.campo === 'id' && (orden.direccion === 'asc' ? '↑' : '↓')}
              </th>
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={() => cambiarOrden(col.key)}
                  className="font-body text-xs text-borde uppercase px-4 py-2 cursor-pointer select-none hover:text-texto whitespace-nowrap"
                >
                  {col.label} {orden.campo === col.key && (orden.direccion === 'asc' ? '↑' : '↓')}
                </th>
              ))}
              <th className="px-4 py-2 w-32" />
            </tr>
          </thead>
          <tbody>
            {itemsPagina.map((item) => (
              <tr key={item.id} className="border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <td className="font-marcador text-xs text-borde px-4 py-2">{item.id}</td>
                {columns.map((col) => (
                  <td key={col.key} className="font-body text-sm text-texto px-4 py-2">{item[col.key]}</td>
                ))}
                <td className="px-2 py-2">
                  <div className="flex items-center justify-end gap-0.5">
                    <BotonAccion icono={<IconoCopiar />} onClick={() => copiarFila(item)} titulo="Copiar fila" />
                    <Link to={`/admin/${resource}/detalle/${item.id}`} title="Editar detallado" className="p-1.5 rounded transition text-premio hover:text-premio hover:bg-borde/10">
                      <IconoEditarDetallado />
                    </Link>
                    <BotonAccion icono={<IconoEdicionRapida />} onClick={() => abrirEdicion(item)} titulo="Edición rápida" color="text-acento hover:text-acento" />
                    <BotonAccion icono={<IconoEliminar />} onClick={() => setAEliminar(item)} titulo="Eliminar" color="text-red-500 hover:text-red-500" />
                  </div>
                </td>
              </tr>
            ))}
            {itemsFiltrados.length === 0 && (
              <tr>
                <td colSpan={columns.length + 2} className="font-body text-sm text-borde text-center px-4 py-6">
                  Sin resultados{busqueda && ` para "${busqueda}"`}
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