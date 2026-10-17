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
  return navigator.serviceWorker.ready
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

  const registro = await registroServiceWorker()
  const suscripcion = await registro.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: claveABytes(clavePublica),
  })
  return { permiso, suscripcion }
}
