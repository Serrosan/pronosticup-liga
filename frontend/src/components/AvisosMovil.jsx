import { useEffect, useState } from 'react'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import TicketHeader from './TicketHeader'
import { admiteAvisos, esAppInstalada, esIOS, suscribir, suscripcionActual } from '../utils/push'
import { alCambiarInstalable, instalar, puedeInstalar } from '../utils/instalarApp'

/**
 * Perfil → Avisos en el móvil. Según el dispositivo enseña el interruptor, los
 * pasos para añadir la app en iPhone, o que este navegador no los admite.
 * Nunca pide permiso sin que la persona toque "Activar".
 */

function Paso({ n, children }) {
  return (
    <li className="flex gap-3">
      <span className="font-marcador text-xs font-bold w-5 h-5 rounded-full border border-borde/40 text-texto flex items-center justify-center shrink-0">{n}</span>
      <span className="font-body text-sm text-texto">{children}</span>
    </li>
  )
}

function InstruccionesIOS() {
  return (
    <div className="flex flex-col gap-3">
      <p className="font-body text-sm text-texto">
        En iPhone y iPad los avisos solo llegan si añades PronostiCup a la pantalla de inicio (hace falta iOS 16.4 o posterior):
      </p>
      <ol className="flex flex-col gap-2">
        <Paso n={1}>Abre <strong>pronosticup.es</strong> en <strong>Safari</strong>.</Paso>
        <Paso n={2}>Toca el botón <strong>Compartir</strong> (el cuadrado con una flecha hacia arriba, abajo en el centro).</Paso>
        <Paso n={3}>Baja y toca <strong>«Añadir a pantalla de inicio»</strong> → <strong>Añadir</strong>.</Paso>
        <Paso n={4}>Cierra Safari y abre <strong>PronostiCup desde el icono</strong> nuevo de tu pantalla de inicio.</Paso>
        <Paso n={5}>Vuelve aquí (Perfil → Avisos en el móvil), toca <strong>Activar</strong> y luego <strong>Permitir</strong>.</Paso>
      </ol>
    </div>
  )
}

function InstruccionesDenegado({ ios }) {
  return (
    <div className="flex flex-col gap-2">
      <p className="font-body text-sm text-texto">⚠️ Dijiste «No permitir» y el navegador ya no deja volver a preguntar. Para activarlos:</p>
      {ios ? (
        <p className="font-body text-sm text-texto">Ajustes del iPhone → <strong>Notificaciones</strong> → <strong>PronostiCup</strong> → activa <strong>Permitir notificaciones</strong>. Luego vuelve aquí.</p>
      ) : (
        <p className="font-body text-sm text-texto">Mantén pulsado el icono de PronostiCup (o toca el candado junto a la dirección en Chrome) → <strong>Notificaciones</strong> → <strong>Permitir</strong>. Luego recarga esta página.</p>
      )}
    </div>
  )
}

function SugerenciaInstalar() {
  const [instalable, setInstalable] = useState(puedeInstalar())
  useEffect(() => alCambiarInstalable(() => setInstalable(puedeInstalar())), [])

  return (
    <div className="border border-borde/30 rounded-lg p-3 flex flex-col gap-2">
      <p className="font-body text-xs text-texto">
        💡 Instala la app para que los avisos lleguen como <strong>PronostiCup</strong>, con su icono, y no como «Chrome».
      </p>
      {instalable ? (
        <button type="button" onClick={() => instalar()} className="self-start font-body text-xs font-semibold border border-acento text-acento rounded px-3 py-1.5 hover:bg-acento/10">
          📲 Instalar app
        </button>
      ) : (
        <p className="font-body text-[11px] text-borde">En Chrome: menú <strong>⋮</strong> → <strong>Instalar app</strong> (o «Añadir a pantalla de inicio»).</p>
      )}
    </div>
  )
}

function AvisosMovil() {
  const { usuario } = useAuth()
  const toast = useToast()
  const [config, setConfig] = useState(null)
  const [estado, setEstado] = useState('cargando') // cargando | ios-instalar | no-admite | denegado | apagado | encendido
  const [ocupado, setOcupado] = useState(false)
  const ios = esIOS()
  const instalada = esAppInstalada()

  useEffect(() => {
    let cancelado = false

    async function comprobar() {
      try {
        const { data } = await client.get('/api/v1/push/config')
        if (cancelado) return
        setConfig(data.data)
        if (!data.data.configurado) return

        if (!admiteAvisos()) {
          setEstado(ios && !instalada ? 'ios-instalar' : 'no-admite')
          return
        }
        if (Notification.permission === 'denied') {
          setEstado('denegado')
          return
        }

        const suscripcion = await suscripcionActual()
        if (cancelado) return
        if (suscripcion) {
          // Por si el servidor la perdió: se vuelve a guardar sin molestar.
          client.post('/api/v1/push/suscripciones', suscripcion.toJSON()).catch(() => {})
        }
        setEstado(suscripcion ? 'encendido' : 'apagado')
      } catch {
        if (!cancelado) setEstado('no-admite')
      }
    }

    comprobar()
    return () => { cancelado = true }
  }, [ios, instalada])

  async function activar() {
    setOcupado(true)
    try {
      const { permiso, suscripcion } = await suscribir(config.clave_publica)
      if (permiso === 'denied') {
        setEstado('denegado')
        return
      }
      if (!suscripcion) {
        toast.error('No se activaron: no diste permiso.')
        return
      }
      await client.post('/api/v1/push/suscripciones', suscripcion.toJSON())
      setEstado('encendido')
      toast.exito('Avisos activados en este dispositivo.')
    } catch {
      toast.error('No se pudieron activar los avisos en este dispositivo.')
    } finally {
      setOcupado(false)
    }
  }

  async function desactivar() {
    setOcupado(true)
    try {
      const suscripcion = await suscripcionActual()
      if (suscripcion) {
        await client.delete('/api/v1/push/suscripciones', { data: { endpoint: suscripcion.endpoint } })
        await suscripcion.unsubscribe()
      }
      setEstado('apagado')
      toast.exito('Avisos desactivados en este dispositivo.')
    } catch {
      toast.error('No se pudieron desactivar los avisos.')
    } finally {
      setOcupado(false)
    }
  }

  async function probar() {
    setOcupado(true)
    try {
      const { data } = await client.post('/api/v1/push/prueba')
      toast.exito(data.message)
    } catch (err) {
      toast.error(err.response?.data?.message ?? 'No se pudo enviar la prueba.')
    } finally {
      setOcupado(false)
    }
  }

  // Sin claves en el servidor: los jugadores no ven nada; el superadmin, el motivo.
  if (config && !config.configurado) {
    if (!usuario?.es_superadmin) return null
    return (
      <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
        <TicketHeader titulo="Avisos en el móvil" />
        <p className="p-4 font-body text-xs text-borde">🛠️ Solo lo ves tú: faltan las claves VAPID en el servidor (php artisan push:claves).</p>
      </div>
    )
  }
  if (!config || estado === 'cargando') return null

  const encendido = estado === 'encendido'

  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
      <TicketHeader titulo="Avisos en el móvil" />
      <div className="p-4 flex flex-col gap-4">
        {estado === 'ios-instalar' && <InstruccionesIOS />}

        {estado === 'no-admite' && (
          <p className="font-body text-sm text-borde">Este navegador o dispositivo no admite avisos. La app funciona igual; puedes activarlos desde otro móvil u ordenador.</p>
        )}

        {estado === 'denegado' && <InstruccionesDenegado ios={ios} />}

        {(estado === 'apagado' || encendido) && (
          <>
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="font-body text-sm text-texto">Recibir avisos en este dispositivo</p>
                <p className="font-body text-[11px] text-borde">Te llegan como cualquier notificación del móvil, aunque no tengas la app abierta.</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={encendido}
                disabled={ocupado}
                onClick={encendido ? desactivar : activar}
                className={`font-body text-xs font-semibold rounded-full px-3 py-1.5 border shrink-0 disabled:opacity-50 ${
                  encendido ? 'bg-acento text-fondo border-acento' : 'text-texto border-borde/40 hover:bg-borde/10'
                }`}
              >
                {ocupado ? '...' : encendido ? '✓ Activados' : 'Activar'}
              </button>
            </div>

            {encendido && (
              <button
                type="button"
                onClick={probar}
                disabled={ocupado}
                className="self-start font-body text-xs text-acento hover:underline disabled:opacity-50"
              >
                🔔 Enviarme un aviso de prueba
              </button>
            )}

            {!instalada && !ios && <SugerenciaInstalar />}

            <p className="font-body text-[11px] text-borde">
              Se activa en cada dispositivo por separado. De momento solo llega la prueba; los avisos de la liga (cierre de jornada, Faltas, puntos…) vienen enseguida.
            </p>
          </>
        )}
      </div>
    </div>
  )
}

export default AvisosMovil
