import { useState, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'

const COLOR_ACCION = {
  creado: 'text-acento',
  actualizado: 'text-premio',
  eliminado: 'text-red-500',
}

function tiempoRelativo(fechaISO) {
  const minutos = Math.round((Date.now() - new Date(fechaISO)) / 60000)
  if (minutos < 1) return 'ahora mismo'
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.round(minutos / 60)
  if (horas < 24) return `hace ${horas}h`
  return `hace ${Math.round(horas / 24)}d`
}

function formatearValor(valor) {
  if (valor === null || valor === undefined) return '—'
  if (typeof valor === 'boolean') return valor ? 'sí' : 'no'
  return String(valor)
}

function ListaCambios({ cambios }) {
  if (!cambios) return null

  // Compatibilidad con registros antiguos, guardados antes de este cambio, que
  // solo tenían el valor nuevo (sin "antes") — se muestran igual, sin flecha.
  return (
    <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5">
      {Object.entries(cambios).map(([campo, valor]) => {
        const esFormatoNuevo = valor && typeof valor === 'object' && 'despues' in valor

        return (
          <p key={campo} className="font-body text-[11px] text-borde">
            <span className="font-semibold">{campo}:</span>{' '}
            {esFormatoNuevo ? (
              <>
                <span className="line-through opacity-60">{formatearValor(valor.antes)}</span>
                {' → '}
                <span className="text-texto">{formatearValor(valor.despues)}</span>
              </>
            ) : (
              formatearValor(valor)
            )}
          </p>
        )
      })}
    </div>
  )
}

function AdminRegistroActividadPage() {
  const [filtroModelo, setFiltroModelo] = useState('')
  const [filtroAccion, setFiltroAccion] = useState('')

  const { data: registros, isLoading } = useQuery({
    queryKey: ['admin', 'registro-actividad'],
    queryFn: async () => (await client.get('/api/v1/admin/registro-actividad')).data.data,
  })

  const modelosDisponibles = useMemo(() => {
    if (!registros) return []
    return [...new Set(registros.map((r) => r.modelo))].sort()
  }, [registros])

  const registrosFiltrados = useMemo(() => {
    if (!registros) return []
    return registros.filter((r) => {
      if (filtroModelo && r.modelo !== filtroModelo) return false
      if (filtroAccion && r.accion !== filtroAccion) return false
      return true
    })
  }, [registros, filtroModelo, filtroAccion])

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <h2 className="font-display text-xl text-texto">Registro de actividad</h2>
        <div className="flex items-center gap-2 flex-wrap">
          <SelectTema
            value={filtroModelo}
            onChange={(e) => setFiltroModelo(e.target.value)}
            options={[{ value: '', label: 'Todos los modelos' }, ...modelosDisponibles.map((m) => ({ value: m, label: m }))]}
            className="bg-fondo text-sm min-w-[160px]"
          />
          <SelectTema
            value={filtroAccion}
            onChange={(e) => setFiltroAccion(e.target.value)}
            options={[
              { value: '', label: 'Todas las acciones' },
              { value: 'creado', label: 'Creado' },
              { value: 'actualizado', label: 'Actualizado' },
              { value: 'eliminado', label: 'Eliminado' },
            ]}
            className="bg-fondo text-sm min-w-[150px]"
          />
        </div>
      </div>

      <p className="font-body text-xs text-borde mb-2">{registrosFiltrados.length} de {registros.length}</p>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        {registrosFiltrados.length === 0 ? (
          <p className="font-body text-sm text-borde text-center py-8">Sin actividad que coincida con el filtro.</p>
        ) : (
          registrosFiltrados.map((r) => (
            <div key={r.id} className="px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5">
              <div className="flex items-center justify-between gap-2">
                <p className="font-body text-sm text-texto">
                  <span className="font-semibold">{r.usuario}</span>{' '}
                  <span className={COLOR_ACCION[r.accion]}>{r.accion}</span>{' '}
                  {r.modelo} #{r.id_registro}
                </p>
                <span className="font-body text-xs text-borde shrink-0">{tiempoRelativo(r.creado_en)}</span>
              </div>
              <ListaCambios cambios={r.cambios} />
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default AdminRegistroActividadPage