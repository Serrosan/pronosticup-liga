// Chrome (Android/ordenador) avisa con "beforeinstallprompt" cuando la app se
// puede instalar. Se guarda ese aviso para poder ofrecer "Instalar app" desde
// el perfil. No se bloquea el aviso propio de Chrome.

let evento = null
const oyentes = new Set()
const avisar = () => oyentes.forEach((f) => f())

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (e) => {
    evento = e
    avisar()
  })
  window.addEventListener('appinstalled', () => {
    evento = null
    avisar()
  })
}

export function puedeInstalar() {
  return !!evento
}

export async function instalar() {
  if (!evento) return false
  evento.prompt()
  const { outcome } = await evento.userChoice
  evento = null
  avisar()
  return outcome === 'accepted'
}

export function alCambiarInstalable(f) {
  oyentes.add(f)
  return () => oyentes.delete(f)
}
