import { useQuery } from '@tanstack/react-query'
import client from '../api/client'

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

function AdminRegistroActividadPage() {
  const { data: registros, isLoading } = useQuery({
    queryKey: ['admin', 'registro-actividad'],
    queryFn: async () => (await client.get('/api/v1/admin/registro-actividad')).data.data,
  })

  if (isLoading) return <p className="font-body text-texto p-4">Cargando...</p>

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Registro de actividad</h2>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        {registros.length === 0 ? (
          <p className="font-body text-sm text-borde text-center py-8">Sin actividad registrada todavía.</p>
        ) : (
          registros.map((r) => (
            <div key={r.id} className="px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5">
              <div className="flex items-center justify-between gap-2">
                <p className="font-body text-sm text-texto">
                  <span className="font-semibold">{r.usuario}</span>{' '}
                  <span className={COLOR_ACCION[r.accion]}>{r.accion}</span>{' '}
                  {r.modelo} #{r.id_registro}
                </p>
                <span className="font-body text-xs text-borde shrink-0">{tiempoRelativo(r.creado_en)}</span>
              </div>
              {r.cambios && (
                <p className="font-body text-[11px] text-borde mt-1">
                  {Object.keys(r.cambios).join(', ')}
                </p>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default AdminRegistroActividadPage