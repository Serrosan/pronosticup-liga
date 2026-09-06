import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'
import { useToast } from '../context/ToastContext'

const CAMPOS = [
  { name: 'puntos_signo', label: 'Puntos por acertar el signo (1X2)' },
  { name: 'puntos_diferencia', label: 'Puntos por acertar signo + diferencia' },
  { name: 'puntos_exacto', label: 'Puntos por resultado exacto' },
  { name: 'bonus_pleno_7', label: 'Bonus por 70% de aciertos de signo en la jornada' },
  { name: 'bonus_pleno_8', label: 'Bonus por 80% de aciertos de signo' },
  { name: 'bonus_pleno_9', label: 'Bonus por 90% de aciertos de signo' },
  { name: 'bonus_pleno_10', label: 'Bonus por pleno (100%) de aciertos de signo' },
  { name: 'puntos_gol_goleador', label: 'Puntos por gol de tus 5 goleadores elegidos' },
]

function AdminConfiguracionPuntosPage() {
  const [ligaSeleccionada, setLigaSeleccionada] = useState('')
  const [form, setForm] = useState(null)
  const toast = useToast()
  const queryClient = useQueryClient()

  const { data: ligas } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => (await client.get('/api/v1/admin/ligas')).data.data,
  })

  const { data: configData, isLoading } = useQuery({
    queryKey: ['admin', 'configuracion-puntos', ligaSeleccionada || 'global'],
    queryFn: async () => {
      const url = ligaSeleccionada
        ? `/api/v1/admin/configuracion-puntos/liga/${ligaSeleccionada}`
        : '/api/v1/admin/configuracion-puntos/global'
      return (await client.get(url)).data
    },
  })

  useEffect(() => {
    if (configData?.data) setForm(configData.data)
  }, [configData])

  const guardar = useMutation({
    mutationFn: (datos) => {
      const url = ligaSeleccionada
        ? `/api/v1/admin/configuracion-puntos/liga/${ligaSeleccionada}`
        : '/api/v1/admin/configuracion-puntos/global'
      return client.put(url, datos)
    },
    onSuccess: () => {
      toast.exito('Configuración guardada correctamente.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'configuracion-puntos'] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'Error al guardar.'),
  })

  const restaurar = useMutation({
    mutationFn: () => client.delete(`/api/v1/admin/configuracion-puntos/liga/${ligaSeleccionada}`),
    onSuccess: () => {
      toast.exito('Restaurada la configuración global para esta liga.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'configuracion-puntos'] })
    },
  })

  function actualizar(campo, valor) {
    setForm((prev) => ({ ...prev, [campo]: valor }))
  }

  function handleSubmit(event) {
    event.preventDefault()
    const datos = {}
    CAMPOS.forEach((c) => { datos[c.name] = Number(form[c.name]) })
    guardar.mutate(datos)
  }

  const esPersonalizado = ligaSeleccionada && configData?.personalizado

  return (
    <div className="max-w-2xl">
      <h2 className="font-display text-xl text-texto mb-4">Configuración de puntos</h2>

      <div className="mb-4">
        <label className="font-body text-xs text-borde block mb-1">Ámbito</label>
        <SelectTema
          value={ligaSeleccionada}
          onChange={(e) => setLigaSeleccionada(e.target.value)}
          options={[
            { value: '', label: 'Configuración global (valor por defecto de todas las ligas)' },
            ...(ligas?.map((l) => ({ value: l.id, label: l.nombre })) ?? []),
          ]}
          className="w-full bg-borde/10"
        />
        <p className="font-body text-xs text-borde mt-1">
          {ligaSeleccionada
            ? esPersonalizado
              ? 'Esta liga tiene valores propios, distintos de la configuración global.'
              : 'Esta liga usa la configuración global — al guardar, se creará una configuración propia solo para ella.'
            : 'Estos son los valores por defecto que usa cualquier liga sin configuración propia.'}
        </p>
      </div>

      {isLoading || !form ? (
        <p className="font-body text-texto p-4">Cargando...</p>
      ) : (
        <form onSubmit={handleSubmit} className="bg-fondo border border-borde/30 rounded-lg p-6 flex flex-col gap-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {CAMPOS.map((campo) => (
              <div key={campo.name}>
                <label className="font-body text-xs text-borde block mb-1">{campo.label}</label>
                <input
                  type="number"
                  min="0"
                  value={form[campo.name] ?? ''}
                  onChange={(e) => actualizar(campo.name, e.target.value)}
                  className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-1.5 focus:outline-none focus:border-acento"
                />
              </div>
            ))}
          </div>

          <div className="flex gap-2 items-center">
            <button
              type="submit"
              disabled={guardar.isPending}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50"
            >
              {guardar.isPending ? 'Guardando...' : 'Guardar'}
            </button>

            {esPersonalizado && (
              <button
                type="button"
                onClick={() => { if (confirm('¿Quitar la configuración propia de esta liga y volver a usar la global?')) restaurar.mutate() }}
                className="font-body text-sm text-red-500 hover:underline"
              >
                Restaurar valores globales
              </button>
            )}
          </div>
        </form>
      )}
    </div>
  )
}

export default AdminConfiguracionPuntosPage