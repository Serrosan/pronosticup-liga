// Service worker de PronostiCup.
//
// No guarda nada en caché: la app siempre se carga de la red, así nunca se
// queda nadie con una versión vieja después de un despliegue. Sirve para que
// la app se pueda instalar y para mostrar los avisos al móvil (push).

self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (evento) => {
  evento.waitUntil(self.clients.claim())
})

// Llega un aviso del servidor: se enseña como una notificación del sistema,
// con el icono de la app, vibración y, si trae etiqueta, sustituyendo al
// anterior del mismo tipo en vez de apilarse.
self.addEventListener('push', (evento) => {
  let datos = {}
  try {
    datos = evento.data ? evento.data.json() : {}
  } catch {
    datos = { cuerpo: evento.data ? evento.data.text() : '' }
  }

  const opciones = {
    body: datos.cuerpo || '',
    icon: '/icons/icon-192.png',
    badge: '/icons/badge-96.png',
    vibrate: [120, 60, 120],
    lang: 'es',
    data: { url: datos.url || '/dashboard' },
  }
  if (datos.tag) {
    opciones.tag = datos.tag
    opciones.renotify = true
  }

  const tareas = [self.registration.showNotification(datos.titulo || 'PronostiCup', opciones)]

  // El numerito sobre el icono de la app, en los móviles que lo admiten.
  if (typeof datos.pendientes === 'number' && 'setAppBadge' in self.navigator) {
    tareas.push(datos.pendientes > 0 ? self.navigator.setAppBadge(datos.pendientes) : self.navigator.clearAppBadge())
  }

  evento.waitUntil(Promise.all(tareas).catch(() => {}))
})

// Tocar el aviso: abre la app en la pantalla que toca (o la trae al frente si ya estaba abierta).
self.addEventListener('notificationclick', (evento) => {
  evento.notification.close()
  const destino = new URL(evento.notification.data?.url || '/dashboard', self.location.origin).href

  evento.waitUntil((async () => {
    const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const ventana of ventanas) {
      if (new URL(ventana.url).origin === self.location.origin) {
        await ventana.focus()
        if ('navigate' in ventana) await ventana.navigate(destino).catch(() => {})
        return
      }
    }
    await self.clients.openWindow(destino)
  })())
})
