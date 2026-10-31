// Avisos al móvil (Web Push): qué admite este dispositivo y cómo activarlos.

export function esIOS() {
  const ua = navigator.userAgent || ''
  return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

export function esAppInstalada() {
  return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true
}

export function admiteAvisos() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

// El service worker se registra al cargar la app en producción; en local (o si
// aún no estaba) se registra aquí, al ir a activar los avisos.
export async function registroServiceWorker() {
  const existente = await navigator.serviceWorker.getRegistration('/')
  if (!existente) await navigator.serviceWorker.register('/sw.js')
  // Si el service worker no llega a arrancar, mejor un error claro que un botón colgado.
  return conLimite(navigator.serviceWorker.ready, 15000, 'service-worker')
}

function conLimite(promesa, ms, fase) {
  return Promise.race([
    promesa,
    new Promise((_, rechazar) => setTimeout(() => {
      const error = new Error('El service worker no ha respondido a tiempo')
      error.name = 'TimeoutError'
      error.fase = fase
      rechazar(error)
    }, ms)),
  ])
}

export async function suscripcionActual() {
  const registro = await registroServiceWorker()
  return registro.pushManager.getSubscription()
}

function claveABytes(base64url) {
  const relleno = '='.repeat((4 - (base64url.length % 4)) % 4)
  const base64 = (base64url + relleno).replace(/-/g, '+').replace(/_/g, '/')
  return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0))
}

/** Pide permiso (solo tras un toque del usuario) y suscribe este dispositivo. */
export async function suscribir(clavePublica) {
  const permiso = await Notification.requestPermission()
  if (permiso !== 'granted') return { permiso, suscripcion: null }

  let registro
  try {
    registro = await registroServiceWorker()
  } catch (error) {
    error.fase ??= 'service-worker'
    throw error
  }

  const opciones = { userVisibleOnly: true, applicationServerKey: claveABytes(clavePublica) }
  try {
    return { permiso, suscripcion: await registro.pushManager.subscribe(opciones) }
  } catch (primerError) {
    // Una activación vieja a medias (o con otra clave) bloquea la nueva: se quita y se reintenta una vez.
    try {
      const vieja = await registro.pushManager.getSubscription()
      if (vieja) {
        await vieja.unsubscribe()
        return { permiso, suscripcion: await registro.pushManager.subscribe(opciones) }
      }
    } catch {
      // si el reintento también falla, se informa del error original
    }
    primerError.fase = 'suscribir'
    throw primerError
  }
}

const AJUSTES_ANDROID = 'Ajustes del móvil → Aplicaciones → Chrome → Notificaciones → actívalas (y lo mismo con «PronostiCup» si sale en la lista). En Chrome, también: menú ⋮ → Configuración → Configuración de sitios → Notificaciones → que pronosticup.es no esté bloqueado.'

/**
 * Qué ha pasado al intentar activar los avisos, en lenguaje llano, con qué hacer
 * y el detalle técnico (para que te lo puedan pegar si sale algo raro).
 * @returns {{motivo: string, queHacer: string, detalle: string}}
 */
export function explicarErrorAvisos(error, { ios = false } = {}) {
  const nombre = error?.name || 'Error'
  const mensaje = error?.response?.data?.message || error?.message || ''
  const detalle = [error?.fase && `[${error.fase}]`, error?.response ? `HTTP ${error.response.status}` : nombre, mensaje].filter(Boolean).join(' ')
  const texto = mensaje.toLowerCase()

  if (error?.fase === 'servidor' || error?.response) {
    return {
      motivo: 'El móvil se ha activado bien, pero el servidor no ha podido guardarlo.',
      queHacer: error?.response?.status === 401 || error?.response?.status === 419
        ? 'Cierra la app, vuelve a abrirla (y a entrar si te lo pide) y prueba otra vez.'
        : 'Inténtalo de nuevo en un rato. Si sigue igual, pásale el detalle al admin.',
      detalle,
    }
  }
  if (!navigator.onLine) {
    return { motivo: 'No hay conexión a internet.', queHacer: 'Conéctate y vuelve a intentarlo.', detalle }
  }
  if (nombre === 'TimeoutError' || error?.fase === 'service-worker') {
    return {
      motivo: 'La app no ha terminado de prepararse en este móvil.',
      queHacer: 'Cierra la app del todo (quítala de las apps recientes), vuelve a abrirla y prueba otra vez.',
      detalle,
    }
  }
  if (nombre === 'NotAllowedError' || texto.includes('permission')) {
    return {
      motivo: 'El móvil no deja que esta app reciba notificaciones.',
      queHacer: ios ? 'Ajustes del iPhone → Notificaciones → PronostiCup → Permitir notificaciones.' : AJUSTES_ANDROID,
      detalle,
    }
  }
  if (nombre === 'AbortError' || texto.includes('push service') || texto.includes('registration failed')) {
    return {
      motivo: 'El servicio de avisos del propio móvil (el de Google) no ha respondido.',
      queHacer: 'Ajustes → Aplicaciones → «Servicios de Google Play»: ahorro de batería en «Sin restricciones» y datos en segundo plano permitidos. Comprueba que Chrome y los Servicios de Google Play están actualizados en Play Store, reinicia el móvil y prueba otra vez. (En móviles sin servicios de Google, como algunos Huawei, no se pueden recibir.)',
      detalle,
    }
  }
  if (nombre === 'InvalidStateError') {
    return {
      motivo: 'Había una activación antigua a medias en este móvil.',
      queHacer: 'En Chrome: menú ⋮ → Configuración → Configuración de sitios → Todos los sitios → pronosticup.es → Borrar datos. Vuelve a entrar y prueba otra vez.',
      detalle,
    }
  }
  if (nombre === 'NotSupportedError') {
    return {
      motivo: 'Este navegador no admite avisos.',
      queHacer: 'Abre pronosticup.es con Chrome (no desde WhatsApp ni el navegador del fabricante), instala la app desde ahí y actívalos.',
      detalle,
    }
  }
  return {
    motivo: 'El móvil no ha dejado activar los avisos.',
    queHacer: `Revisa los permisos de notificaciones. ${ios ? '' : AJUSTES_ANDROID} Si sigue igual, pásale el detalle al admin.`,
    detalle,
  }
}
