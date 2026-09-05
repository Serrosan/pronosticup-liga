import { useState, useEffect, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'
import CampoImagenSubida from '../components/CampoImagenSubida'
import FichajesJugador from '../components/FichajesJugador'
import { CAMPOS_ADMIN } from '../config/camposAdmin'
import { useToast } from '../context/ToastContext'
import useAvisoSalir from '../hooks/useAvisoSalir'

function CampoTexto({ campo, valor, onChange, modificado }) {
  return (
    <div>
      <label className="font-body text-xs text-borde block mb-1">{campo.label}</label>
      <input
        type={campo.type === 'number' ? 'number' : campo.type === 'date' ? 'date' : 'text'}
        value={valor ?? ''}
        onChange={(e) => onChange(e.target.value)}
        className={`w-full font-body bg-borde/10 text-texto rounded border px-3 py-1.5 focus:outline-none focus:border-acento transition-colors ${
          modificado ? 'border-premio' : 'border-borde/40'
        }`}
      />
    </div>
  )
}

function CampoImagen({ campo, valor, onChange, onGuardarInmediato }) {
  return (
    <CampoImagenSubida
      label={campo.label}
      valor={valor}
      onChange={(url) => { onChange(url); onGuardarInmediato(campo.name, url) }}
      carpeta={campo.carpeta ?? 'trofeos'}
    />
  )
}

function CampoColor({ campo, valor, onChange, modificado }) {
  return (
    <div>
      <label className="font-body text-xs text-borde block mb-1">{campo.label}</label>
      <div className="flex items-center gap-2">
        <input type="color" value={valor || '#000000'} onChange={(e) => onChange(e.target.value)} className="w-10 h-9 rounded border border-borde/40 shrink-0" />
        <input
          value={valor ?? ''}
          onChange={(e) => onChange(e.target.value)}
          className={`flex-1 font-body bg-borde/10 text-texto rounded border px-3 py-1.5 transition-colors ${modificado ? 'border-premio' : 'border-borde/40'}`}
        />
      </div>
    </div>
  )
}

function CampoSelect({ campo, valor, onChange }) {
  const { data: opcionesRelacionadas } = useQuery({
    queryKey: ['admin', campo.optionsFrom?.resource],
    queryFn: async () => (await client.get(`/api/v1/admin/${campo.optionsFrom.resource}`)).data.data,
    enabled: !!campo.optionsFrom,
  })
  const options = campo.optionsFrom
    ? (opcionesRelacionadas ?? []).map((o) => ({ value: o.id, label: o[campo.optionsFrom.labelKey] }))
    : campo.options
  return (
    <div>
      <label className="font-body text-xs text-borde block mb-1">{campo.label}</label>
      <SelectTema value={valor ?? ''} onChange={(e) => onChange(e.target.value)} options={options} className="w-full bg-borde/10" />
    </div>
  )
}

function AdminResourceDetailPage() {
  const { resource, id } = useParams()
  const navigate = useNavigate()
  const [form, setForm] = useState(null)
  const [camposModificados, setCamposModificados] = useState(new Set())
  const toast = useToast()
  const formRef = useRef(null)
  const config = CAMPOS_ADMIN[resource]

  const { data: item, isLoading } = useQuery({
    queryKey: ['admin', resource, id],
    queryFn: async () => (await client.get(`/api/v1/admin/${resource}/${id}`)).data.data,
    enabled: !!config,
  })

  useEffect(() => {
    if (item) {
      setForm(item)
      setCamposModificados(new Set())
    }
  }, [item])

  const huboCambios = camposModificados.size > 0
  useAvisoSalir(huboCambios)

  const guardar = useMutation({
    mutationFn: (datos) => client.put(`/api/v1/admin/${resource}/${id}`, datos),
    onSuccess: (respuesta) => {
      toast.exito(`${config.titulo[0].toUpperCase()}${config.titulo.slice(1)} actualizado correctamente.`)
      setForm(respuesta.data.data)
      setCamposModificados(new Set())
    },
    onError: (err) => {
      toast.error(err.response?.data?.message ?? 'Error al guardar.')
      setTimeout(() => {
        const primerCampoInvalido = formRef.current?.querySelector(':invalid, .border-red-500')
        primerCampoInvalido?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }, 100)
    },
  })

  function actualizar(campo, valor) {
    setForm((prev) => ({ ...prev, [campo]: valor }))
    setCamposModificados((prev) => new Set(prev).add(campo))
  }

  function guardarInmediato(campo, valor) {
    guardar.mutate({ ...form, [campo]: valor })
  }

  function handleSubmit(event) {
    event.preventDefault()
    guardar.mutate(form)
  }

  function volver() {
    if (huboCambios && !confirm('Tienes cambios sin guardar. ¿Seguro que quieres salir sin guardarlos?')) {
      return
    }
    navigate(config.volverA)
  }

  if (!config) return <p className="font-body text-red-500 p-4">No hay configuración de edición detallada para "{resource}" todavía.</p>
  if (isLoading || !form) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div className="max-w-2xl">
      <div className="flex items-center gap-3 mb-4">
        <button onClick={volver} className="font-body text-sm text-acento hover:underline">← Volver</button>
        <h2 className="font-display text-xl text-texto">Editar {config.titulo} #{id}</h2>
        {resource === 'jugadores' && form.estado === 'De baja' && (
          <span className="font-body text-xs font-semibold bg-red-500/15 text-red-500 rounded-full px-2.5 py-1">De baja</span>
        )}
        {huboCambios && (
          <span className="font-body text-xs text-premio">● cambios sin guardar</span>
        )}
      </div>

      <form ref={formRef} onSubmit={handleSubmit} className="bg-fondo border border-borde/30 rounded-lg p-6 flex flex-col gap-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {config.campos.map((campo) => {
            const valor = campo.type === 'date' ? form[campo.name]?.slice(0, 10) : form[campo.name]
            const modificado = camposModificados.has(campo.name)
            const props = { key: campo.name, campo, valor, modificado, onChange: (v) => actualizar(campo.name, v) }
            if (campo.type === 'imagen') return <CampoImagen {...props} onGuardarInmediato={guardarInmediato} />
            if (campo.type === 'color') return <CampoColor {...props} />
            if (campo.type === 'select') return <CampoSelect {...props} />
            return <CampoTexto {...props} />
          })}
        </div>
        <button
          type="submit"
          disabled={guardar.isPending || !huboCambios}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded py-2.5 hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed self-start px-6"
        >
          {guardar.isPending ? 'Guardando...' : huboCambios ? 'Guardar cambios' : 'Sin cambios'}
        </button>
      </form>

      {resource === 'jugadores' && (
        <FichajesJugador jugadorId={id} dadoDeBaja={form.estado === 'De baja'} />
      )}
    </div>
  )
}

export default AdminResourceDetailPage