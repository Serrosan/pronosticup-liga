import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import { useTheme } from '../context/ThemeContext'
import { useToast } from '../context/ToastContext'

const TOTAL_JORNADAS = 38
const TIPOS = {
  gol: '⚽ Gol', tarjeta_amarilla: '🟨 Amarilla', tarjeta_roja: '🟥 Roja', sustitucion: '🔄 Sustitución',
}

function AdminEventosPartidoPage() {
  const { tema } = useTheme()
  const toast = useToast()
  const colorFondo = tema === 'oscuro' ? '#0E1B2B' : '#FFFFFF'
  const colorTexto = tema === 'oscuro' ? '#ECE7DB' : '#111827'

  const [jornada, setJornada] = useState(1)
  const [idPartido, setIdPartido] = useState('')
  const [texto, setTexto] = useState('')
  const [eventos, setEventos] = useState(null)
  const [mensaje, setMensaje] = useState(null)

  const { data: partidos } = useQuery({
    queryKey: ['admin', 'calendario', jornada],
    queryFn: async () => (await client.get(`/api/v1/admin/calendario?jornada=${jornada}`)).data.data,
  })

  const interpretar = useMutation({
    mutationFn: () => client.post('/api/v1/admin/eventos-partido/interpretar', { id_partido: idPartido, texto }),
    onSuccess: (respuesta) => {
      setEventos(respuesta.data.data.map((e) => ({ ...e, vinculadoOriginal: !!e.id_jugador })))
    },
  })

  const guardar = useMutation({
    mutationFn: () => client.post('/api/v1/admin/eventos-partido/guardar', { id_partido: idPartido, eventos }),
    onSuccess: (respuesta) => {
      setMensaje({ tipo: 'exito', texto: respuesta.data.message })
      setEventos(null)
      setTexto('')
    },
    onError: (err) => setMensaje({ tipo: 'error', texto: err.response?.data?.message ?? 'Error al guardar.' }),
  })

  const recalcular = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/jornadas/${jornada}/recalcular-eventos`),
    onSuccess: (respuesta) => toast.exito(respuesta.data.message),
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo recalcular.'),
  })

  function actualizarEvento(index, campo, valor) {
    setEventos((prev) => prev.map((e, i) => (i === index ? { ...e, [campo]: valor } : e)))
  }

  return (
    <div>
      <h2 className="font-display text-xl text-texto mb-4">Eventos de partido (goles/tarjetas/sustituciones)</h2>

      <div className="bg-premio/10 border border-premio/30 rounded-lg p-4 mb-4 max-w-xl flex items-center justify-between gap-3 flex-wrap">
        <div>
          <p className="font-body text-sm font-semibold text-premio">Recalcular puntos de goleadores</p>
          <p className="font-body text-xs text-borde mt-0.5">
            Ejecútalo cuando termines de cargar los eventos de esta jornada — puedes repetirlo tantas veces como añadas eventos nuevos.
          </p>
        </div>
        <button
          onClick={() => recalcular.mutate()}
          disabled={recalcular.isPending}
          className="font-body text-sm font-semibold bg-premio text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50 shrink-0"
        >
          {recalcular.isPending ? 'Recalculando...' : `Recalcular jornada ${jornada}`}
        </button>
      </div>

      <div className="flex flex-col gap-3 mb-4 max-w-xl">
        <div className="flex gap-3">
          <select
            value={jornada}
            onChange={(e) => { setJornada(Number(e.target.value)); setIdPartido('') }}
            style={{ backgroundColor: colorFondo, color: colorTexto }}
            className="font-body text-sm rounded border border-borde/40 px-2 py-2"
          >
            {Array.from({ length: TOTAL_JORNADAS }, (_, i) => i + 1).map((j) => (
              <option key={j} value={j} style={{ backgroundColor: colorFondo, color: colorTexto }}>
                Jornada {j}
              </option>
            ))}
          </select>

          <select
            value={idPartido}
            onChange={(e) => setIdPartido(e.target.value)}
            style={{ backgroundColor: colorFondo, color: colorTexto }}
            className="font-body text-sm rounded border border-borde/40 px-2 py-2 flex-1"
          >
            <option value="" style={{ backgroundColor: colorFondo, color: colorTexto }}>Elige un partido...</option>
            {partidos?.map((p) => (
              <option key={p.id} value={p.id} style={{ backgroundColor: colorFondo, color: colorTexto }}>
                {p.equipo_local} {p.goles_casa ?? '-'}-{p.goles_fuera ?? '-'} {p.equipo_visitante}
              </option>
            ))}
          </select>
        </div>

        <textarea
          placeholder="Pega aquí el texto completo de la página de LaLiga (sección de comentarios)"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          rows={8}
          className="font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2"
        />
        <button
          onClick={() => interpretar.mutate()}
          disabled={interpretar.isPending || !idPartido || !texto}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50 self-start"
        >
          {interpretar.isPending ? 'Interpretando...' : 'Interpretar texto'}
        </button>
      </div>

      {mensaje && (
        <p className={`font-body text-sm mb-4 px-3 py-2 rounded ${mensaje.tipo === 'exito' ? 'bg-acento/10 text-acento' : 'bg-red-500/10 text-red-500'}`}>
          {mensaje.texto}
        </p>
      )}

      {eventos && (
        <div className="bg-fondo border border-borde/30 rounded-lg overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="border-b border-borde/20">
                <th className="font-body text-xs text-borde px-3 py-2">Min</th>
                <th className="font-body text-xs text-borde px-3 py-2">Tipo</th>
                <th className="font-body text-xs text-borde px-3 py-2">Texto detectado</th>
                <th className="font-body text-xs text-borde px-3 py-2">¿Vinculado?</th>
              </tr>
            </thead>
            <tbody>
              {eventos.map((evento, i) => (
                <tr key={i} className="border-b border-borde/10 last:border-0">
                  <td className="font-marcador text-xs text-texto px-3 py-2">{evento.minuto}'</td>
                  <td className="font-body text-xs text-texto px-3 py-2">{TIPOS[evento.tipo_evento]}</td>
                  <td className="font-body text-xs text-texto px-3 py-2">
                    {evento.jugador_texto}
                    {evento.jugador_relacionado_texto && <span className="text-borde"> / {evento.jugador_relacionado_texto}</span>}
                  </td>
                  <td className="px-3 py-2">
                    {evento.vinculadoOriginal ? (
                      <span className="font-body text-xs text-acento">✓</span>
                    ) : (
                      <input
                        type="number"
                        placeholder="ID jugado"
                        defaultValue={evento.id_jugador || ''}
                        onChange={(e) => actualizarEvento(i, 'id_jugador', Number(e.target.value))}
                        className="font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-2 py-1 w-24"
                      />
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="p-3">
            <button
              onClick={() => guardar.mutate()}
              disabled={guardar.isPending}
              className="font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50"
            >
              {guardar.isPending ? 'Guardando...' : `Guardar ${eventos.length} eventos`}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default AdminEventosPartidoPage