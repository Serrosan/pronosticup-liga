import { useState } from 'react'
import { useParams, useNavigate, useSearchParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import SelectorJornada from '../components/SelectorJornada'
import PestanasJornada from '../components/PestanasJornada'
import SkeletonLista from '../components/SkeletonLista'
import EstadoVacio from '../components/EstadoVacio'
import useTitulo from '../hooks/useTitulo'
import { TIPOS, fondoSuave, estaResuelto, tipoDePronostico } from '../utils/tiposAcierto'
import ReproductorAudio from '../components/ReproductorAudio'

// "La jornada en tu liga": qué ha puesto cada uno. Regla única, la misma en
// las tres pestañas: se ve lo que ya no se puede cambiar. Lo decide el
// backend (LaJornadaService); aquí solo se pinta.

const JORNADAS = Array.from({ length: 38 }, (_, i) => i + 1)

const SIGNOS = [
  { clave: 'Local', corto: '1', color: 'var(--color-acento)' },
  { clave: 'Empate', corto: 'X', color: 'var(--color-borde)' },
  { clave: 'Visitante', corto: '2', color: 'var(--color-premio)' },
]
const CORTO = { Local: '1', Empate: 'X', Visitante: '2' }

const RAREZA = { Comun: 'Común', PocoComun: 'Poco común', Rara: 'Rara', Legendaria: 'Legendaria' }

function Avatar({ url, nombre, tamano = 'w-6 h-6' }) {
  if (url) return <img src={url} alt={nombre} className={`${tamano} rounded-full object-cover shrink-0`} />
  return (
    <span className={`${tamano} rounded-full bg-acento/15 flex items-center justify-center text-[10px] font-semibold shrink-0 text-acento`}>
      {nombre?.[0]?.toUpperCase() ?? '?'}
    </span>
  )
}

function Escudo({ url, alt }) {
  if (!url) return <span className="w-5 h-5 rounded-full bg-borde/15 flex items-center justify-center text-xs shrink-0">⚽</span>
  return <img src={url} alt={alt} className="w-5 h-5 object-contain shrink-0" />
}

function Candado({ children }) {
  return (
    <div className="flex items-start gap-2 bg-borde/5 border border-borde/10 rounded-lg px-3 py-2.5">
      <span className="text-sm leading-none mt-0.5">🔒</span>
      <p className="font-body text-xs text-borde">{children}</p>
    </div>
  )
}

function horaCorta(iso) {
  if (!iso) return 'Sin hora'
  const fecha = new Date(iso)
  const dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb']
  return `${dias[fecha.getDay()]} ${fecha.getHours().toString().padStart(2, '0')}:${fecha.getMinutes().toString().padStart(2, '0')}`
}

// ---------------------------------------------------------------- Pronósticos

function BarraReparto({ reparto }) {
  const total = SIGNOS.reduce((suma, s) => suma + (reparto[s.clave] ?? 0), 0)
  if (total === 0) return null

  return (
    <div>
      <div className="flex h-2.5 rounded-full overflow-hidden bg-borde/10" role="img" aria-label={SIGNOS.map((s) => `${s.corto}: ${reparto[s.clave]}`).join(', ')}>
        {SIGNOS.map((s) =>
          reparto[s.clave] > 0 ? (
            <div key={s.clave} style={{ width: `${(reparto[s.clave] / total) * 100}%`, backgroundColor: s.color }} />
          ) : null
        )}
      </div>
      <div className="flex justify-between mt-1.5">
        {SIGNOS.map((s) => (
          <span key={s.clave} className="font-body text-[11px] text-borde tabular-nums">
            <span className="font-marcador font-bold" style={{ color: s.color }}>{s.corto}</span>
            {' · '}
            {reparto[s.clave]} ({Math.round((reparto[s.clave] / total) * 100)}%)
          </span>
        ))}
      </div>
    </div>
  )
}

function ListaPronosticos({ partido }) {
  const resuelto = estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado)

  return (
    <div className="mt-3 bg-borde/5 border border-borde/10 rounded-lg divide-y divide-borde/10">
      {partido.pronosticos.map((p) => {
        const tipo = resuelto ? TIPOS[tipoDePronostico(p.pronostico, partido.goles_casa, partido.goles_fuera)] : null
        return (
          <div key={p.id_usuario} className={`flex items-center gap-2.5 px-3 py-2 ${p.soy_yo ? 'bg-acento/5' : ''}`}>
            <Avatar url={p.avatar_url} nombre={p.usuario} />
            <p className={`font-body text-xs text-texto flex-1 truncate ${p.soy_yo ? 'font-semibold' : ''}`}>
              {p.soy_yo ? 'Tú' : p.usuario}
            </p>
            <span
              title={tipo?.leyenda}
              className="font-marcador text-xs font-bold rounded px-2 py-0.5 tabular-nums flex items-center gap-1.5"
              style={tipo ? { backgroundColor: fondoSuave(tipo.color), color: tipo.color } : { color: 'var(--color-texto)' }}
            >
              {tipo && <span className="font-body">{tipo.icono}</span>}
              {p.pronostico}
            </span>
            <span className="font-marcador text-xs font-bold tabular-nums w-8 text-right" style={{ color: p.puntos > 0 ? tipo?.color : 'var(--color-borde)' }}>
              {p.puntos === null || p.puntos === undefined ? '' : p.puntos > 0 ? `+${p.puntos}` : '0'}
            </span>
          </div>
        )
      })}
    </div>
  )
}

function TarjetaPartido({ partido, miembros }) {
  const [abierto, setAbierto] = useState(false)
  const resuelto = estaResuelto(partido.goles_casa, partido.goles_fuera, partido.estado)
  const enJuego = partido.estado === 'En juego'

  let centro = horaCorta(partido.horario)
  if (resuelto || enJuego) centro = `${partido.goles_casa ?? 0}-${partido.goles_fuera ?? 0}`
  if (partido.estado === 'Aplazado') centro = 'Aplazado'

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg px-3 sm:px-4 py-3">
      <Link to={`/partidos/${partido.id}`} className="grid grid-cols-[1fr_auto_1fr] items-center gap-x-2 hover:opacity-80 transition">
        <span className="flex items-center gap-1.5 justify-end min-w-0">
          <span className="font-body text-sm text-texto truncate">{partido.local.nombre}</span>
          <Escudo url={partido.local.escudo_url} alt={partido.local.nombre} />
        </span>
        <span className={`font-marcador text-center tabular-nums whitespace-nowrap ${resuelto || enJuego ? 'text-lg font-bold text-texto' : 'text-xs text-borde'}`}>
          {centro}
          {enJuego && <span className="block font-body text-[9px] font-semibold text-red-500 uppercase tracking-widest">En juego</span>}
        </span>
        <span className="flex items-center gap-1.5 min-w-0">
          <Escudo url={partido.visitante.escudo_url} alt={partido.visitante.nombre} />
          <span className="font-body text-sm text-texto truncate">{partido.visitante.nombre}</span>
        </span>
      </Link>

      <div className="mt-3">
        {!partido.visible ? (
          <Candado>
            Se verá cuando empiece el partido · {partido.total_pronosticos} de {miembros} ya han pronosticado
            {partido.mi_pronostico && <> · tú: <span className="font-marcador font-bold text-texto">{partido.mi_pronostico}</span></>}
          </Candado>
        ) : partido.pronosticos.length === 0 ? (
          <p className="font-body text-xs text-borde text-center">Nadie pronosticó este partido.</p>
        ) : (
          <>
            <BarraReparto reparto={partido.reparto} />

            {(partido.mas_repetido || partido.contra_todos.length > 0) && (
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                {partido.mas_repetido && (
                  <p className="font-body text-[11px] text-borde">
                    Lo más repetido: <span className="font-marcador font-bold text-texto">{partido.mas_repetido.marcador}</span> ({partido.mas_repetido.veces})
                  </p>
                )}
                {partido.contra_todos.map((c) => (
                  <p key={c.signo} className="font-body text-[11px] text-borde">
                    🐺 Contra todos: <span className="font-semibold text-texto">{c.soy_yo ? 'tú' : c.usuario}</span> ({CORTO[c.signo]})
                  </p>
                ))}
              </div>
            )}

            <button
              type="button"
              onClick={() => setAbierto(!abierto)}
              aria-expanded={abierto}
              className="mt-2 font-body text-xs text-acento hover:underline flex items-center gap-1"
            >
              <span className={`inline-block transition-transform ${abierto ? 'rotate-90' : ''}`}>›</span>
              {abierto ? 'Ocultar' : `Ver los ${partido.pronosticos.length} pronósticos`}
            </button>

            {abierto && <ListaPronosticos partido={partido} />}
          </>
        )}
      </div>
    </div>
  )
}

function PestanaPronosticos({ datos }) {
  if (datos.partidos.length === 0) {
    return <EstadoVacio icono="📅" titulo="Sin partidos" texto="Esta jornada aún no tiene partidos cargados." />
  }

  return (
    <div className="flex flex-col gap-3">
      {datos.partidos.map((p) => <TarjetaPartido key={p.id} partido={p} miembros={datos.miembros} />)}
    </div>
  )
}

// ---------------------------------------------------------------- Goleadores

function PestanaGoleadores({ datos }) {
  const g = datos.goleadores

  if (!g.visible) {
    return (
      <Candado>
        Se verán cuando empiece la jornada · {g.usuarios_con_seleccion} de {datos.miembros} ya han elegido
        {g.mis_elegidos > 0 && <> · tú llevas {g.mis_elegidos} de 5</>}
      </Candado>
    )
  }

  if (g.jugadores.length === 0) {
    return <EstadoVacio icono="🥅" titulo="Nadie eligió goleadores" texto="En esta jornada nadie de la liga eligió goleadores." />
  }

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10">
      {g.jugadores.map((j) => {
        const loTengo = j.elegido_por.some((u) => u.soy_yo)
        return (
          <div key={j.id_jugador} className={`px-3 sm:px-4 py-3 ${loTengo ? 'bg-acento/5' : ''}`}>
            <div className="flex items-center gap-3">
              <Link to={`/jugadores/${j.id_jugador}`} className="shrink-0">
                <Avatar url={j.foto_url} nombre={j.nombre} tamano="w-9 h-9" />
              </Link>
              <div className="min-w-0 flex-1">
                <Link to={`/jugadores/${j.id_jugador}`} className="font-body text-sm text-texto font-medium truncate hover:underline block">
                  {j.nombre}
                </Link>
                <p className="font-body text-[11px] text-borde">
                  Elegido por {j.elegido_por.length}
                </p>
              </div>
              {j.goles > 0 ? (
                <span className="font-marcador text-xs font-bold text-premio bg-premio/10 rounded-full px-2.5 py-1 shrink-0">
                  ⚽ {j.goles} gol{j.goles > 1 ? 'es' : ''}
                </span>
              ) : (
                <span className="font-body text-[11px] text-borde shrink-0">sin gol</span>
              )}
            </div>
            <div className="flex flex-wrap gap-1.5 mt-2 pl-12">
              {j.elegido_por.map((u) => (
                <span
                  key={u.id_usuario}
                  className={`flex items-center gap-1.5 rounded-full pl-0.5 pr-2 py-0.5 border ${u.soy_yo ? 'border-acento/40 bg-acento/10' : 'border-borde/20'}`}
                >
                  <Avatar url={u.avatar_url} nombre={u.usuario} tamano="w-5 h-5" />
                  <span className={`font-body text-[11px] ${u.soy_yo ? 'text-acento font-semibold' : 'text-texto'}`}>{u.soy_yo ? 'Tú' : u.usuario}</span>
                </span>
              ))}
            </div>
          </div>
        )
      })}
    </div>
  )
}

// ---------------------------------------------------------------- Cartas

function ResultadoCarta({ carta }) {
  if (carta.estado === 'pendiente') {
    return <span className="font-body text-[11px] text-borde whitespace-nowrap">⏳ Por resolver</span>
  }
  if (carta.estado === 'sin_efecto') {
    return <span className="font-body text-[11px] text-borde whitespace-nowrap">✕ Sin efecto</span>
  }
  if (carta.puntos !== 0) {
    return (
      <span className={`font-marcador text-xs font-bold whitespace-nowrap ${carta.puntos > 0 ? 'text-premio' : 'text-red-500'}`}>
        {carta.puntos > 0 ? `+${carta.puntos}` : carta.puntos}
      </span>
    )
  }
  return <span className="font-body text-[11px] text-premio font-semibold whitespace-nowrap">✓ Cumplida</span>
}

function FraseCarta({ carta }) {
  const quien = <span className={`font-semibold ${carta.soy_yo ? 'text-acento' : 'text-texto'}`}>{carta.quien}</span>
  const nombre = <span className="font-semibold text-premio">{carta.nombre}</span>

  if (carta.es_falta && carta.para_si_mismo) {
    return <>{quien} se protegió con {nombre}</>
  }
  if (carta.es_falta) {
    const objetivo = <span className={`font-semibold ${carta.objetivo_soy_yo ? 'text-red-500' : 'text-texto'}`}>{carta.objetivo_soy_yo ? 'ti' : carta.objetivo}</span>
    return <>{quien} le sacó {nombre} a {objetivo}</>
  }
  return <>{quien} jugó {nombre} · <span className="text-borde">{carta.sobre}</span></>
}

function PestanaCartas({ datos }) {
  const c = datos.cartas

  if (!c.visible) {
    return (
      <Candado>
        Se verán cuando empiece la jornada · {c.total === 0 ? 'de momento nadie ha jugado cartas para ella' : `${c.total} carta${c.total > 1 ? 's' : ''} jugada${c.total > 1 ? 's' : ''} para ella`}
      </Candado>
    )
  }

  if (c.lista.length === 0) {
    return <EstadoVacio icono="🃏" titulo="Ni una carta" texto="Nadie jugó cartas para esta jornada." />
  }

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10">
      {c.lista.map((carta) => (
        <div key={carta.id} className="flex items-start gap-3 px-3 sm:px-4 py-3">
          <span className="text-lg leading-none mt-0.5 shrink-0" aria-hidden="true">
            {carta.es_falta ? (carta.para_si_mismo ? '🛡️' : '⚠️') : '🃏'}
          </span>
          <div className="min-w-0 flex-1">
            <p className="font-body text-sm text-texto leading-snug">
              <FraseCarta carta={carta} />
            </p>
            {carta.mensaje && <p className="font-body text-xs text-borde italic mt-1">«{carta.mensaje}»</p>}
            {carta.audio_url && <div className="mt-1.5 max-w-xs"><ReproductorAudio src={carta.audio_url} /></div>}
            {carta.rareza && <p className="font-body text-[10px] uppercase tracking-widest text-borde mt-1">{RAREZA[carta.rareza] ?? carta.rareza}</p>}
          </div>
          <div className="shrink-0 pt-0.5">
            <ResultadoCarta carta={carta} />
          </div>
        </div>
      ))}
    </div>
  )
}

// ---------------------------------------------------------------- Resumen

const MEDALLAS = ['🥇', '🥈', '🥉']
const ORDINAL = (n) => `${n}º`

function Nombre({ p }) {
  return (
    <span className={`font-semibold ${p.soy_yo ? 'text-acento' : 'text-texto'}`}>
      {p.usuario}
      {p.soy_yo && <span className="font-body text-[10px] font-normal text-acento"> (tú)</span>}
    </span>
  )
}

function Nombres({ lista }) {
  return lista.map((p, i) => (
    <span key={p.id_usuario}>
      {i > 0 && (i === lista.length - 1 ? ' y ' : ', ')}
      <Nombre p={p} />
    </span>
  ))
}

function Tarjeta({ titulo, children }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
      <p className="font-body text-[10px] uppercase tracking-widest text-borde px-4 pt-3 pb-2">{titulo}</p>
      {children}
    </div>
  )
}

function Destacado({ icono, children }) {
  return (
    <div className="flex items-start gap-3 px-4 py-2.5">
      <span className="text-base leading-none mt-0.5 shrink-0" aria-hidden="true">{icono}</span>
      <p className="font-body text-sm text-texto leading-snug">{children}</p>
    </div>
  )
}

function PestanaResumen({ datos }) {
  const r = datos.resumen
  const destacados = []

  r.exactos_unicos.forEach((e) =>
    destacados.push(
      <Destacado key={`exacto-${e.id_usuario}-${e.partido}`} icono="🎯">
        <Nombre p={e} /> clavó el <span className="font-marcador font-bold">{e.marcador}</span> del {e.partido} y nadie más lo vio
        <span className="text-borde"> (pronosticaron {e.pronosticaron})</span>
      </Destacado>
    )
  )
  r.contra_todos.forEach((c) =>
    destacados.push(
      <Destacado key={`contra-${c.id_usuario}-${c.partido}`} icono="🐺">
        <Nombre p={c} /> dijo <span className="font-marcador font-bold">{CORTO[c.signo]}</span> en el {c.partido} contra {c.contra} más… y acertó
      </Destacado>
    )
  )
  if (r.mas_exactos) {
    destacados.push(
      <Destacado key="mas-exactos" icono="★">
        <Nombres lista={r.mas_exactos.quienes} /> {r.mas_exactos.quienes.length > 1 ? 'hicieron' : 'hizo'} {r.mas_exactos.exactos} resultados exactos
      </Destacado>
    )
  }
  if (r.mejor_carta) {
    destacados.push(
      <Destacado key="carta" icono="🃏">
        <Nombre p={r.mejor_carta} /> sacó <span className="font-marcador font-bold text-premio">+{r.mejor_carta.puntos}</span> con{' '}
        <span className="font-semibold text-premio">{r.mejor_carta.carta}</span>
        {r.mejor_carta.partido && <span className="text-borde"> · {r.mejor_carta.partido}</span>}
      </Destacado>
    )
  }
  if (r.goleador) {
    destacados.push(
      <Destacado key="goleador" icono="⚽">
        <Link to={`/jugadores/${r.goleador.id_jugador}`} className="font-semibold text-texto hover:underline">{r.goleador.nombre}</Link> marcó{' '}
        {r.goleador.goles} gol{r.goleador.goles > 1 ? 'es' : ''} y lo tenía{r.goleador.quienes.length > 1 ? 'n' : ''} <Nombres lista={r.goleador.quienes} />
      </Destacado>
    )
  }

  const movimientos = [r.lider, r.sube, r.baja].some(Boolean)

  return (
    <div className="flex flex-col gap-3">
      <Tarjeta titulo="Los mejores de la jornada">
        <div className="divide-y divide-borde/10">
          {r.podio.map((p) => (
            <div key={p.id_usuario} className={`flex items-center gap-3 px-4 py-2.5 ${p.soy_yo ? 'bg-acento/5' : ''}`}>
              <span className="w-6 text-center text-lg leading-none" aria-label={`${p.posicion}º`}>{MEDALLAS[p.posicion - 1]}</span>
              <Avatar url={p.avatar_url} nombre={p.usuario} tamano="w-8 h-8" />
              <p className="font-body text-sm flex-1 min-w-0 truncate"><Nombre p={p} /></p>
              <span className="font-marcador text-base font-bold text-acento tabular-nums">{p.puntos}<span className="font-body text-[10px] font-normal text-borde"> pt</span></span>
            </div>
          ))}
        </div>
        <div className="px-4 py-2.5 border-t border-borde/10 flex flex-wrap justify-between gap-2">
          {r.media !== null && <p className="font-body text-xs text-borde">Media de la liga: <span className="font-marcador text-texto">{r.media}</span> pt</p>}
          {r.farolillo.length > 0 && (
            <p className="font-body text-xs text-borde">
              🏮 Farolillo rojo: <Nombres lista={r.farolillo} /> ({r.farolillo[0].puntos} pt)
            </p>
          )}
        </div>
      </Tarjeta>

      {movimientos && (
        <Tarjeta titulo="La clasificación">
          {r.lider && (
            <Destacado icono="👑">
              <Nombre p={r.lider} /> {r.lider.nuevo ? 'es el nuevo líder' : 'sigue líder'}
            </Destacado>
          )}
          {r.sube && (
            <Destacado icono="📈">
              <Nombre p={r.sube} /> sube {r.sube.puestos} puesto{r.sube.puestos > 1 ? 's' : ''} <span className="text-borde">(ahora {ORDINAL(r.sube.puesto)})</span>
            </Destacado>
          )}
          {r.baja && (
            <Destacado icono="📉">
              <Nombre p={r.baja} /> baja {r.baja.puestos} puesto{r.baja.puestos > 1 ? 's' : ''} <span className="text-borde">(ahora {ORDINAL(r.baja.puesto)})</span>
            </Destacado>
          )}
        </Tarjeta>
      )}

      <Tarjeta titulo="Lo más comentado">
        {destacados.length > 0 ? (
          <div className="divide-y divide-borde/10 pb-1">{destacados}</div>
        ) : (
          <p className="font-body text-sm text-borde px-4 pb-3">Jornada tranquila: nada fuera de lo normal.</p>
        )}
      </Tarjeta>
    </div>
  )
}

// ---------------------------------------------------------------- Página

function LaJornadaPage() {
  const { jornada } = useParams()
  const numero = Number(jornada)
  const navigate = useNavigate()
  const { usuario } = useAuth()
  const [params, setParams] = useSearchParams()
  useTitulo(`Jornada ${numero} en tu liga`)

  const { data, isLoading, error } = useQuery({
    queryKey: ['la-jornada', numero],
    queryFn: async () => (await client.get(`/api/v1/jornadas/${numero}/la-jornada`)).data.data,
    placeholderData: (anterior) => anterior,
    refetchInterval: 60_000,
  })

  const pestanas = [
    ...(data?.resumen ? [{ clave: 'resumen', texto: 'Resumen' }] : []),
    { clave: 'pronosticos', texto: 'Pronósticos' },
    { clave: 'goleadores', texto: 'Goleadores' },
    ...(data?.con_cartas ? [{ clave: 'cartas', texto: 'Cartas', cuenta: data.cartas?.total || null }] : []),
  ]
  const pedida = params.get('ver')
  // Jornada cerrada: se abre por el Resumen. Si no, por los Pronósticos.
  const defecto = pestanas[0].clave
  const activa = pestanas.some((p) => p.clave === pedida) ? pedida : defecto

  function irA(n) {
    if (n >= 1 && n <= 38) navigate(`/jornadas/${n}/liga${activa !== defecto ? `?ver=${activa}` : ''}`)
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-4">
      <div className="flex justify-center mb-1">
        <SelectorJornada jornadas={JORNADAS} valor={numero} onCambiar={irA} grande />
      </div>
      <PestanasJornada jornada={numero} activa="liga" />
      <p className="font-body text-xs text-borde text-center mb-4">
        Lo que ha puesto cada uno{usuario?.liga_activa?.nombre ? ` en ${usuario.liga_activa.nombre}` : ''}
      </p>

      <div className="flex justify-center gap-4 mb-4 border-b border-borde/15" role="tablist">
        {pestanas.map((p) => (
          <button
            key={p.clave}
            type="button"
            role="tab"
            aria-selected={activa === p.clave}
            onClick={() => setParams(p.clave === defecto ? {} : { ver: p.clave }, { replace: true })}
            className={`font-body text-sm px-3 pb-1.5 transition whitespace-nowrap ${
              activa === p.clave ? 'text-acento border-b-2 border-acento font-semibold' : 'text-borde border-b-2 border-transparent hover:text-texto'
            }`}
          >
            {p.texto}
            {p.cuenta ? <span className="ml-1.5 font-marcador text-xs opacity-80">{p.cuenta}</span> : null}
          </button>
        ))}
      </div>

      {error && <p className="font-body text-sm text-red-500 text-center">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>}
      {isLoading && <SkeletonLista />}

      {data && activa === 'resumen' && data.resumen && <PestanaResumen datos={data} />}
      {data && activa === 'pronosticos' && <PestanaPronosticos datos={data} />}
      {data && activa === 'goleadores' && <PestanaGoleadores datos={data} />}
      {data && activa === 'cartas' && data.cartas && <PestanaCartas datos={data} />}

      <p className="font-body text-[11px] text-borde text-center mt-6">
        Cada cosa se enseña cuando ya no se puede cambiar: los pronósticos al empezar cada partido, y los goleadores y las cartas al empezar la jornada.
      </p>
    </div>
  )
}

export default LaJornadaPage
