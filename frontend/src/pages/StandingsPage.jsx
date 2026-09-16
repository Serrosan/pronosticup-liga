import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import TicketHeader from '../components/TicketHeader'
import EstadoVacio from '../components/EstadoVacio'
import SkeletonLista from '../components/SkeletonLista'
import CompartirClasificacion from '../components/CompartirClasificacion'
import useTitulo from '../hooks/useTitulo'

const MEDALLAS = ['🥇', '🥈', '🥉']

function Avatar({ url, nombre }) {
  if (url) return <img src={url} alt={nombre} className="w-10 h-10 rounded-full object-cover shrink-0" />
  return (
    <div className="w-10 h-10 rounded-full bg-acento/15 flex items-center justify-center shrink-0">
      <span className="font-display text-sm text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

function FlechaTendencia({ tendencia }) {
  if (!tendencia || tendencia === 'igual') {
    return <span className="text-borde text-xs w-4 text-center shrink-0">–</span>
  }
  if (tendencia === 'sube') {
    return <span className="text-acento text-xs w-4 text-center shrink-0" title="Ha subido puesto(s)">▲</span>
  }
  return <span className="text-red-500 text-xs w-4 text-center shrink-0" title="Ha bajado puesto(s)">▼</span>
}

function Chip({ color, children, titulo }) {
  return (
    <span
      title={titulo}
      className="font-marcador text-[11px] font-bold rounded-full px-2 py-0.5 whitespace-nowrap"
      style={{ backgroundColor: `${color}1F`, color }}
    >
      {children}
    </span>
  )
}

function SelectorJornada({ jornadaSeleccionada, onCambiar }) {
  const { data: jornadas } = useQuery({
    queryKey: ['jornadas-cerradas'],
    queryFn: async () => (await client.get('/api/v1/jornadas-cerradas')).data.data,
  })

  if (!jornadas || jornadas.length === 0) return null

  return (
    <div className="flex gap-2 flex-wrap mb-4">
      <button
        onClick={() => onCambiar(null)}
        className={`font-body text-sm px-3 py-1.5 rounded-full transition ${
          jornadaSeleccionada === null ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
        }`}
      >
        Total
      </button>
      {jornadas.map((j) => (
        <button
          key={j}
          onClick={() => onCambiar(j)}
          className={`font-body text-sm px-3 py-1.5 rounded-full transition ${
            jornadaSeleccionada === j ? 'bg-acento text-fondo font-semibold' : 'text-texto border border-borde/40 hover:bg-borde/10'
          }`}
        >
          J{j}
        </button>
      ))}
    </div>
  )
}

function EditarBannerModal({ liga, onCerrar, onGuardado }) {
  const toast = useToast()
  const [lema, setLema] = useState(liga.lema ?? '')
  const [archivoLogo, setArchivoLogo] = useState(null)
  const [previewLogo, setPreviewLogo] = useState(liga.logo_url)

  const guardar = useMutation({
    mutationFn: () => {
      const formData = new FormData()
      formData.append('lema', lema)
      if (archivoLogo) formData.append('logo', archivoLogo)
      return client.post('/api/v1/liga-activa/personalizar', formData)
    },
    onSuccess: () => {
      toast.exito('Liga actualizada.')
      onGuardado()
      onCerrar()
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo guardar.'),
  })

  function handleArchivo(event) {
    const archivo = event.target.files[0]
    if (archivo) {
      setArchivoLogo(archivo)
      setPreviewLogo(URL.createObjectURL(archivo))
    }
  }

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div className="bg-fondo border-2 border-acento/40 rounded-lg p-6 max-w-sm w-full">
        <h2 className="font-display text-lg text-texto mb-4 text-center">Personalizar {liga.nombre}</h2>

        <div className="flex flex-col items-center gap-3 mb-4">
          <label className="relative cursor-pointer group">
            <div className="w-24 h-24 rounded-full border-2 border-acento/40 bg-borde/10 overflow-hidden flex items-center justify-center">
              {previewLogo ? (
                <img src={previewLogo} alt="Logo de la liga" className="w-full h-full object-cover" />
              ) : (
                <span className="text-3xl">🏆</span>
              )}
            </div>
            <span className="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center font-body text-[10px] text-white">
              Cambiar
            </span>
            <input type="file" accept="image/*" className="hidden" onChange={handleArchivo} />
          </label>
        </div>

        <label className="font-body text-xs text-borde block mb-1">Lema de la liga</label>
        <input
          value={lema}
          onChange={(e) => setLema(e.target.value)}
          placeholder="Ej. Donde los pronósticos se convierten en leyenda"
          maxLength={255}
          className="w-full font-body text-sm bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 mb-4 focus:outline-none focus:border-acento"
        />

        <div className="flex gap-3 justify-center">
          <button onClick={onCerrar} className="font-body text-sm text-borde hover:text-texto px-4 py-2">
            Cancelar
          </button>
          <button
            onClick={() => guardar.mutate()}
            disabled={guardar.isPending}
            className="font-body text-sm font-semibold bg-acento text-fondo rounded px-5 py-2 hover:brightness-110 disabled:opacity-50"
          >
            {guardar.isPending ? 'Guardando...' : 'Guardar'}
          </button>
        </div>
      </div>
    </div>
  )
}

function BannerLiga({ liga, esAdmin, onEditar }) {
  return (
    <div className="relative bg-fondo border border-borde/30 rounded-lg overflow-hidden mb-4">
      <div className="h-20 bg-gradient-to-r from-acento/25 via-premio/10 to-transparent" />
      <div className="px-5 pb-4 -mt-10 flex items-end gap-4 flex-wrap">
        <div className="w-20 h-20 rounded-full border-4 border-fondo bg-borde/10 overflow-hidden shrink-0 flex items-center justify-center shadow-lg">
          {liga.logo_url ? (
            <img src={liga.logo_url} alt={liga.nombre} className="w-full h-full object-cover" />
          ) : (
            <span className="text-3xl">🏆</span>
          )}
        </div>
        <div className="min-w-0 flex-1 pb-1">
          <p className="font-display text-lg text-texto truncate">{liga.nombre}</p>
          {liga.lema && <p className="font-body text-sm text-borde italic truncate">"{liga.lema}"</p>}
        </div>
        {esAdmin && (
          <button
            onClick={onEditar}
            className="font-body text-xs text-borde hover:text-texto border border-borde/30 rounded-full px-3 py-1.5 mb-1 shrink-0"
          >
            ✏️ Editar
          </button>
        )}
      </div>
    </div>
  )
}

function FilaClasificacion({ fila, index, usuario, puntosLider }) {
  const diferenciaLider = puntosLider - fila.puntos_totales

  return (
    <Link
      to={`/clasificacion/usuarios/${fila.id_usuario}`}
      state={{ nombre: fila.usuario, avatar_url: fila.avatar_url }}
      className={`flex items-start gap-3 px-4 py-3 border-b border-borde/10 last:border-0 odd:bg-borde/5 hover:bg-acento/5 transition ${
        fila.id_usuario === usuario?.id ? 'bg-acento/5' : ''
      }`}
    >
      <div className="flex items-center gap-2 shrink-0 pt-0.5">
        <FlechaTendencia tendencia={fila.tendencia} />
        <span className="w-7 font-marcador text-sm text-borde text-center">
          {MEDALLAS[index] ?? index + 1}
        </span>
      </div>

      <Avatar url={fila.avatar_url} nombre={fila.usuario} />

      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <p className="font-body text-base font-medium text-texto truncate">{fila.usuario}</p>
          <span className="font-marcador text-lg font-bold text-texto shrink-0">{fila.puntos_totales}</span>
        </div>
        <p className="font-body text-[10px] text-borde mb-1.5">
          {index === 0 ? '🏆 Líder' : `−${diferenciaLider} pts del líder`}
        </p>

        <div className="flex flex-wrap gap-1.5">
          <Chip color="var(--color-acento)" titulo="Aciertos">✓ {fila.aciertos}</Chip>
          <Chip color="#EF4444" titulo="Fallos">✗ {fila.fallos}</Chip>
          <Chip color="var(--color-premio)" titulo="Resultados exactos">🎯 {fila.exactos}</Chip>
          {fila.porcentaje_exito !== null && (
            <Chip color="#0ea5e9" titulo="Porcentaje de acierto">{fila.porcentaje_exito}%</Chip>
          )}
          {fila.racha_actual >= 2 && (
            <Chip color="#F59E0B" titulo="Racha de aciertos seguidos">🔥 {fila.racha_actual}</Chip>
          )}
          {fila.puntos_goleadores > 0 && (
            <Chip color="#a855f7" titulo="Puntos de goleadores">🥅 {fila.puntos_goleadores}</Chip>
          )}
        </div>
      </div>
    </Link>
  )
}

function StandingsPage() {
  const { usuario, refrescar } = useAuth()
  const [jornadaSeleccionada, setJornadaSeleccionada] = useState(null)
  const [editandoBanner, setEditandoBanner] = useState(false)

  useTitulo('Clasificación')

  const { data: clasificacion, isLoading, error } = useQuery({
    queryKey: ['clasificacion', jornadaSeleccionada],
    queryFn: async () => {
      const respuesta = await client.get('/api/v1/clasificacion', {
        params: jornadaSeleccionada ? { hasta_jornada: jornadaSeleccionada } : {},
      })
      return respuesta.data.data
    },
  })

  if (isLoading) return <div className="max-w-4xl mx-auto px-4 py-8"><SkeletonLista /></div>
  if (error) return <p className="font-body text-red-500 p-4">{error.response?.data?.message ?? 'Error al cargar.'}</p>

  const puntosLider = clasificacion[0]?.puntos_totales ?? 0
  const indiceMio = clasificacion.findIndex((f) => f.id_usuario === usuario?.id)
  const filaMia = indiceMio >= 0 ? { ...clasificacion[indiceMio], posicion: indiceMio + 1 } : null
  const esAdminDeLaLiga = usuario?.liga_activa?.rol === 'Admin'

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {usuario?.liga_activa && (
        <BannerLiga liga={usuario.liga_activa} esAdmin={esAdminDeLaLiga} onEditar={() => setEditandoBanner(true)} />
      )}

      {editandoBanner && (
        <EditarBannerModal
          liga={usuario.liga_activa}
          onCerrar={() => setEditandoBanner(false)}
          onGuardado={() => refrescar()}
        />
      )}

      <SelectorJornada jornadaSeleccionada={jornadaSeleccionada} onCambiar={setJornadaSeleccionada} />

      {jornadaSeleccionada !== null && (
        <div className="mb-4 bg-premio/10 border border-premio/30 rounded-lg px-4 py-2.5 text-center">
          <p className="font-body text-sm text-premio font-semibold">
            📅 Puntos conseguidos en la Jornada {jornadaSeleccionada}
          </p>
        </div>
      )}

      {jornadaSeleccionada === null && filaMia && (
        <div className="flex justify-end mb-3">
          <CompartirClasificacion fila={filaMia} ligaNombre={usuario?.liga_activa?.nombre ?? 'PronostiCup Liga'} totalParticipantes={clasificacion.length} />
        </div>
      )}

      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo={jornadaSeleccionada ? `Clasificación tras la Jornada ${jornadaSeleccionada}` : 'Clasificación de la liga'} />

        {clasificacion.length === 0 ? (
          <EstadoVacio icono="🏆" titulo="Aún no hay puntos" texto="En cuanto se cierre la primera jornada, aparecerá aquí la clasificación." />
        ) : (
          <div>
            {clasificacion.map((fila, index) => (
              <FilaClasificacion key={fila.id_usuario} fila={fila} index={index} usuario={usuario} puntosLider={puntosLider} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default StandingsPage
