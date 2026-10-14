// Service worker de PronostiCup, a propósito MÍNIMO.
//
// No guarda nada en caché: la app siempre se carga de la red, así nunca se
// queda nadie con una versión vieja después de un despliegue. Existe para que
// la app se pueda instalar y para tener ya el sitio donde, más adelante,
// llegarán las notificaciones push.

self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (evento) => {
  evento.waitUntil(self.clients.claim())
})
