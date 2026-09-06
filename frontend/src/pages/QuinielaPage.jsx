import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useToast } from '../context/ToastContext'
import useTitulo from '../hooks/useTitulo'
import { DndContext, closestCenter, PointerSensor, TouchSensor, useSensor, useSensors } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'

const INFO_TIPO = {
  completa: { titulo: 'Quiniela completa', sub: 'Toda la temporada, de la jornada 1 a la 38', icono: '🏆' },
  primera_mitad: { titulo: 'Primera mitad', sub: 'Jornadas 1 a 18', icono: '🌗' },
  segunda_mitad: { titulo: 'Segunda mitad', sub: 'Jornada 19 hasta el final', icono: '🌕' },
}

function FilaEquipo({ equipo, indice, total, mostrarControles, mover, puntos }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: equipo.id })

  const estilo = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  const esTop3 = indice < 3

  return (
    <div
      ref={setNodeRef}
      style={estilo}
      className="flex items-center gap-3 px-4 py-2.5 border-b border-borde/10 last:border-0 odd:bg-borde/5 bg-fondo"
    >
      {mostrarControles && (
        <span {...attributes} {...listeners} className="text-borde hover:text-texto cursor-grab active:cursor-grabbing shrink-0 touch-none text-lg">
          ⠿
        </span>
      )}
      <span
        className={`font-marcador text-sm font-bold w-7 h-7 rounded-full flex items-center justify-center shrink-0 ${
          esTop3 ? 'bg-premio/15 text-premio' : 'text-borde'
        }`}
      >
        {indice + 1}
      </span>
      {equipo.escudo_url && <img src={equipo.escudo_url} alt={equipo.nombre} className="w-7 h-7 object-contain shrink-0" />}
      <p className="font-body text-sm text-texto flex-1 truncate">{equipo.nombre}</p>

      {puntos !== undefined ? (
        <span className="font-marcador text-sm font-bold text-acento shrink-0">+{puntos}</span>
      ) : mostrarControles ? (
        <div className="flex gap-1 shrink-0">
          <button onClick={() => mover(indice, -1)} disabled={indice === 0} className="text-borde hover:text-texto disabled:opacity-20 px-1">↑</button>
          <button onClick={() => mover(indice, 1)} disabled={indice === total - 1} className="text-borde hover:text-texto disabled:opacity-20 px-1">↓</button>
        </div>
      ) : null}
    </div>
  )
}

function QuinielaPage() {
  const { tipo } = useParams()
  const toast = useToast()
  const [orden, setOrden] = useState([])

  const info = INFO_TIPO[tipo] ?? { titulo: 'Quiniela', sub: '', icono: '⚽' }
  useTitulo(info.titulo)

  const sensores = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 150, tolerance: 5 } })
  )

  const { data: equipos } = useQuery({
    queryKey: ['equipos-lista'],
    queryFn: async () => (await client.get('/api/v1/admin/equipos')).data.data,
  })

  const { data: quiniela, isLoading } = useQuery({
    queryKey: ['quiniela', tipo],
    queryFn: async () => (await client.get(`/api/v1/quinielas/${tipo}`)).data.data,
  })

  useEffect(() => {
    if (!equipos) return

    if (quiniela?.mis_predicciones?.length > 0) {
      const ordenado = [...quiniela.mis_predicciones].sort((a, b) => a.posicion_predicha - b.posicion_predicha)
      setOrden(ordenado.map((p) => ({ id: p.id_equipo, nombre: p.nombre, escudo_url: p.escudo_url })))
    } else if (orden.length === 0) {
      setOrden(equipos.map((e) => ({ id: e.id, nombre: e.nombre_corto ?? e.nombre, escudo_url: e.escudo_url })))
    }
  }, [equipos, quiniela])

  const guardar = useMutation({
    mutationFn: () => client.post(`/api/v1/quinielas/${tipo}`, {
      predicciones: orden.map((eq, indice) => ({ id_equipo: eq.id, posicion_predicha: indice + 1 })),
    }),
    onSuccess: () => toast.exito('Predicción guardada correctamente.'),
    onError: (err) => toast.error(err.response?.data?.message ?? 'Error al guardar.'),
  })

  function mover(indice, direccion) {
    const nuevo = [...orden]
    const destino = indice + direccion
    if (destino < 0 || destino >= nuevo.length) return
    ;[nuevo[indice], nuevo[destino]] = [nuevo[destino], nuevo[indice]]
    setOrden(nuevo)
  }

  function alTerminarArrastre(evento) {
    const { active, over } = evento
    if (!over || active.id === over.id) return

    setOrden((prev) => {
      const indiceOrigen = prev.findIndex((e) => e.id === active.id)
      const indiceDestino = prev.findIndex((e) => e.id === over.id)
      return arrayMove(prev, indiceOrigen, indiceDestino)
    })
  }

  if (isLoading || !equipos) return <p className="font-body text-texto p-4">Cargando...</p>

  const puedeEditar = quiniela?.abierta && !quiniela?.resuelta

  return (
    <div className="max-w-lg mx-auto px-4 py-8">
      <Link
        to="/dashboard"
        className="inline-flex items-center gap-1.5 font-body text-sm text-texto border border-borde/30 rounded-full px-3 py-1.5 mb-4 hover:bg-borde/10 hover:border-borde/50 transition"
      >
        ← Volver
      </Link>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
        <div className="bg-premio/10 border-b border-premio/20 px-5 py-4 flex items-center gap-3">
          <span className="text-3xl shrink-0">{info.icono}</span>
          <div className="min-w-0">
            <h1 className="font-display text-xl text-texto leading-tight">{info.titulo}</h1>
            <p className="font-body text-xs text-borde">{info.sub}</p>
          </div>
        </div>

        <div className="px-5 py-3">
          {quiniela?.resuelta ? (
            <p className="font-body text-sm text-acento font-semibold">
              ✓ Resuelta — conseguiste <span className="font-marcador">{quiniela.puntos_totales}</span> puntos
            </p>
          ) : quiniela?.abierta ? (
            <p className="font-body text-sm text-borde">
              Arrastra los equipos (o usa las flechas) para ordenarlos según creas que quedará la clasificación.
            </p>
          ) : (
            <p className="font-body text-sm text-red-500">
              Esta quiniela no está abierta para predicciones ahora mismo.
            </p>
          )}
        </div>
      </div>

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        {puedeEditar ? (
          <DndContext sensors={sensores} collisionDetection={closestCenter} onDragEnd={alTerminarArrastre}>
            <SortableContext items={orden.map((e) => e.id)} strategy={verticalListSortingStrategy}>
              {orden.map((equipo, indice) => (
                <FilaEquipo
                  key={equipo.id}
                  equipo={equipo}
                  indice={indice}
                  total={orden.length}
                  mostrarControles
                  mover={mover}
                />
              ))}
            </SortableContext>
          </DndContext>
        ) : (
          orden.map((equipo, indice) => {
            const prediccionResuelta = quiniela?.mis_predicciones?.find((p) => p.id_equipo === equipo.id)
            const esTop3 = indice < 3
            return (
              <div key={equipo.id} className="flex items-center gap-3 px-4 py-2.5 border-b border-borde/10 last:border-0 odd:bg-borde/5">
                <span className={`font-marcador text-sm font-bold w-7 h-7 rounded-full flex items-center justify-center shrink-0 ${
                  esTop3 ? 'bg-premio/15 text-premio' : 'text-borde'
                }`}>
                  {indice + 1}
                </span>
                {equipo.escudo_url && <img src={equipo.escudo_url} alt={equipo.nombre} className="w-7 h-7 object-contain shrink-0" />}
                <p className="font-body text-sm text-texto flex-1 truncate">{equipo.nombre}</p>
                {quiniela?.resuelta && (
                  <span className="font-marcador text-sm font-bold text-acento shrink-0">
                    +{prediccionResuelta?.puntos_obtenidos ?? 0}
                  </span>
                )}
              </div>
            )
          })
        )}
      </div>

      {puedeEditar && (
        <button
          onClick={() => guardar.mutate()}
          disabled={guardar.isPending}
          className="w-full mt-4 font-body text-sm font-semibold bg-acento text-fondo rounded py-2.5 hover:brightness-110 disabled:opacity-50"
        >
          {guardar.isPending ? 'Guardando...' : 'Guardar predicción'}
        </button>
      )}
    </div>
  )
}

export default QuinielaPage
