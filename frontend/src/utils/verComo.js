// "Ver como otro usuario" (solo lectura, solo superadmin). Se guarda en
// sessionStorage: dura lo que la pestaña y no se cuela en otras pestañas.
// El cliente HTTP manda la cabecera X-Ver-Como y el backend
// (VerComoUsuario) atiende la petición como si fuera de esa persona.

const CLAVE = 'pronosticup.verComo'

export function leerVerComo() {
  try {
    const crudo = sessionStorage.getItem(CLAVE)
    return crudo ? JSON.parse(crudo) : null
  } catch {
    return null
  }
}

export function empezarVerComo(usuario) {
  try {
    sessionStorage.setItem(CLAVE, JSON.stringify({ id: usuario.id, nombre: usuario.nombre }))
  } catch {
    return
  }
  // Recarga completa: así no queda nada en caché de tu propia sesión.
  window.location.href = '/dashboard'
}

export function salirDeVerComo() {
  try {
    sessionStorage.removeItem(CLAVE)
  } catch {
    // nada que hacer
  }
  window.location.href = '/admin/ver-como'
}
