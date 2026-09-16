import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'
import { useToast } from '../context/ToastContext'

const RAREZAS = ['Comun', 'PocoComun', 'Rara', 'Legendaria']
const ETIQUETA_RAREZA = { Comun: 'Común', PocoComun: 'Poco común', Rara: 'Rara', Legendaria: 'Legendaria' }

function FilaCategoria({ categoria, onCambiar }) {
  const suma = RAREZAS.reduce((acc, r) => acc + Number(categoria.rarezas[r] || 0), 0)
  const sumaValida = suma === 100

  return (
    <div className="bg-borde/5 border border-borde/20 rounded-lg p-4 mb-3">
      <div className="flex items-center justify-between mb-3">
        <p className="font-display text-base text-texto">{categoria.nombre}</p>
        <div className="flex items-center gap-2">
          <label className="font-body text-xs text-borde">Reparto semanal:</label>
          <input
            type="number"
            min="0"
            max="10"
            value={categoria.cantidad_reparto_semanal}
            onChange={(e) => onCambiar('cantidad_reparto_semanal', Number(e.target.value))}
            className="w-16 font-body text-sm bg-fondo text-texto rounded border border-borde/40 px-2 py-1 text-center"
          />
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {RAREZAS.map((rareza) => (
          <div key={rareza}>
            <label className="font-body text-[10px] text-borde block mb-1">{ETIQUETA_RAREZA[rareza]}</label>
            <div className="flex items-center gap-1">
              <input
                type="number"
                min="0"
                max="100"
                value={categoria.rarezas[rareza]}
                onChange={(e) => onCambiar('rareza', Number(e.target.value), rareza)}
                className="w-full font-body text-sm bg-fondo text-texto rounded border border-borde/40 px-2 py-1 text-center"
              />
              <span className="font-body text-xs text-borde">%</span>
            </div>
          </div>
        ))}
      </div>

      <p className={`font-body text-xs mt-2 ${sumaValida ? 'text-acento' : 'text-red-500 font-semibold'}`}>
        Suma total: {suma}% {sumaValida ? '✓' : '— debe ser exactamente 100%'}
      </p>
    </div>
  )
}

function AdminConfiguracionCartasPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [idLigaSeleccionada, setIdLigaSeleccionada] = useState('')
  const [topeMano, setTopeMano] = useState(8)
  const [categorias, setCategorias] = useState([])

  const { data: ligas } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => (await client.get('/api/v1/admin/ligas')).data.data,
  })

  const { data: configuracion } = useQuery({
    queryKey: ['admin', 'configuracion-cartas', idLigaSeleccionada],
    queryFn: async () => (await client.get(`/api/v1/admin/ligas/${idLigaSeleccionada}/configuracion-cartas`)).data.data,
    enabled: !!idLigaSeleccionada,
  })

  useEffect(() => {
    if (configuracion) {
      setTopeMano(configuracion.liga.tope_mano_cartas)
      setCategorias(configuracion.categorias)
    }
  }, [configuracion])

  const guardar = useMutation({
    mutationFn: () => client.put(`/api/v1/admin/ligas/${idLigaSeleccionada}/configuracion-cartas`, {
      tope_mano_cartas: topeMano,
      categorias: categorias.map((c) => ({
        id_categoria: c.id_categoria,
        cantidad_reparto_semanal: c.cantidad_reparto_semanal,
        rarezas: c.rarezas,
      })),
    }),
    onSuccess: () => {
      toast.exito('Configuración guardada.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'configuracion-cartas', idLigaSeleccionada] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo guardar.'),
  })

  function cambiarCategoria(idCategoria, campo, valor, rareza = null) {
    setCategorias((prev) => prev.map((c) => {
      if (c.id_categoria !== idCategoria) return c

      if (campo === 'rareza') {
        return { ...c, rarezas: { ...c.rarezas, [rareza]: valor } }
      }
      return { ...c, [campo]: valor }
    }))
  }

  const hayAlgunaSumaInvalida = categorias.some((c) => RAREZAS.reduce((acc, r) => acc + Number(c.rarezas[r] || 0), 0) !== 100)

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Configuración de Cartas por liga</h2>

      <div className="mb-5 max-w-xs">
        <label className="font-body text-xs text-borde block mb-1">Liga</label>
        <SelectTema
          value={idLigaSeleccionada}
          onChange={(e) => setIdLigaSeleccionada(e.target.value)}
          options={[{ value: '', label: 'Elige una liga...' }, ...(ligas ?? []).map((l) => ({ value: String(l.id), label: `${l.nombre} (${l.tipo})` }))]}
          className="w-full bg-fondo"
        />
      </div>

      {idLigaSeleccionada && !configuracion && (
        <p className="font-body text-sm text-borde">Cargando configuración...</p>
      )}

      {configuracion && (
        <div className="bg-fondo border border-borde/30 rounded-lg p-5">
          <div className="mb-5 max-w-xs">
            <label className="font-body text-xs text-borde block mb-1">Tope de cartas en mano</label>
            <input
              type="number"
              min="1"
              max="50"
              value={topeMano}
              onChange={(e) => setTopeMano(Number(e.target.value))}
              className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2"
            />
          </div>

          <p className="font-body text-xs uppercase tracking-widest text-borde mb-3">Reparto y probabilidades por categoría</p>

          {categorias.length === 0 ? (
            <p className="font-body text-sm text-borde">No hay categorías de carta activas todavía — créalas primero en "Categorías de Carta".</p>
          ) : (
            categorias.map((cat) => (
              <FilaCategoria
                key={cat.id_categoria}
                categoria={cat}
                onCambiar={(campo, valor, rareza) => cambiarCategoria(cat.id_categoria, campo, valor, rareza)}
              />
            ))
          )}

          <button
            onClick={() => guardar.mutate()}
            disabled={guardar.isPending || hayAlgunaSumaInvalida || categorias.length === 0}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50 mt-2"
          >
            {guardar.isPending ? 'Guardando...' : 'Guardar configuración'}
          </button>
        </div>
      )}
    </div>
  )
}

export default AdminConfiguracionCartasPage
