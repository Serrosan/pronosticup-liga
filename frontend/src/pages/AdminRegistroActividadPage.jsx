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

function TarjetaResumen({ valor, etiqueta, color }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg px-4 py-3 text-center flex-1">
      <p className="font-marcador text-2xl font-bold" style={{ color }}>{valor}</p>
      <p className="font-body text-[10px] uppercase tracking-widest text-borde mt-0.5">{etiqueta}</p>
    </div>
  )
}

function AlertaCartasAtascadas({ alertas }) {
  if (!alertas || alertas.length === 0) return null

  return (
    <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-4">
      <p className="font-body text-sm font-semibold text-red-500 mb-2">
        ⚠️ {alertas.length} carta(s) jugada(s) hace más de 1 semana sin resolverse
      </p>
      <p className="font-body text-xs text-borde mb-3">
        Suele significar que una jornada se quedó a medias sin cerrar. Revisa cada caso.
      </p>
      <div className="flex flex-col gap-1">
        {alertas.map((a) => (
          <p key={a.id} className="font-body text-xs text-texto">
            "{a.carta}" de <span className="font-semibold">{a.usuario}</span> — jornada {a.jornada}, jugada hace {a.dias_desde_jugada} días
          </p>
        ))}
      </div>
    </div>
  )
}

function AdminRegistroActividadPage() {
  const [filtroModelo, setFiltroModelo] = useState('')
  const [filtroAccion, setFiltroAccion] = useState('')
  const [filtroUsuario, setFiltroUsuario] = useState('')

  const { data: respuesta, isLoading } = useQuery({
    queryKey: ['admin', 'registro-actividad'],
    queryFn: async () => {
      const r = await client.get('/api/v1/admin/registro-actividad')
      return { registros: r.data.data, meta: r.data.meta }
    },
  })

  const registros = respuesta?.registros
  const resumen = respuesta?.meta?.resumen
  const alertas = respuesta?.meta?.alertas

  const modelosDisponibles = useMemo(() => {
    if (!registros) return []
    return [...new Set(registros.map((r) => r.modelo))].sort()
  }, [registros])

  const usuariosDisponibles = useMemo(() => {
    if (!registros) return []
    return [...new Set(registros.map((r) => r.usuario))].sort()
  }, [registros])

  const registrosFiltrados = useMemo(() => {
    if (!registros) return []
    return registros.filter((r) => {
      if (filtroModelo && r.modelo !== filtroModelo) return false
      if (filtroAccion && r.accion !== filtroAccion) return false
      if (filtroUsuario && r.usuario !== filtroUsuario) return false
      return true
    })
  }, [registros, filtroModelo, filtroAccion, filtroUsuario])

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Registro de actividad</h2>

      {resumen && (
        <div className="flex gap-3 mb-4">
          <TarjetaResumen valor={resumen.en_mano} etiqueta="Cartas en mano ahora" color="var(--color-acento)" />
          <TarjetaResumen valor={resumen.jugadas_sin_resolver} etiqueta="Jugadas sin resolver" color="var(--color-premio)" />
          <TarjetaResumen valor={resumen.resueltas_hoy} etiqueta="Resueltas hoy" color="#a855f7" />
        </div>
      )}

      <AlertaCartasAtascadas alertas={alertas} />

      <div className="flex items-center justify-between mb-4 gap-3 flex-wrap">
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
          <SelectTema
            value={filtroUsuario}
            onChange={(e) => setFiltroUsuario(e.target.value)}
            options={[{ value: '', label: 'Todos los usuarios' }, ...usuariosDisponibles.map((u) => ({ value: u, label: u }))]}
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
                  {r.detalle ? r.modelo : <>{r.modelo} #{r.id_registro}</>}
                </p>
                <span className="font-body text-xs text-borde shrink-0">{tiempoRelativo(r.creado_en)}</span>
              </div>
              {r.detalle && <p className="font-body text-[11px] text-borde mt-0.5">{r.detalle}</p>}
              <ListaCambios cambios={r.cambios} />
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default AdminRegistroActividadPage