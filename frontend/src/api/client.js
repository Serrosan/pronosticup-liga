import axios from 'axios'
import { leerVerComo } from '../utils/verComo'

const client = axios.create({
  baseURL: import.meta.env.PROD ? '' : 'http://localhost',
  withCredentials: true,
  withXSRFToken: true,
})

// "Ver como un usuario" (solo superadmin, solo lectura): cada petición
// lleva a quién se está mirando. El backend decide si vale (VerComoUsuario).
client.interceptors.request.use((config) => {
  const verComo = leerVerComo()
  if (verComo) config.headers['X-Ver-Como'] = verComo.id
  return config
})

let avisandoSesionCaducada = false

client.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    // Token de seguridad caducado (app abierta mucho rato): se pide uno nuevo y se
    // repite la petición UNA vez, sin que el jugador note nada.
    if (error.response?.status === 419 && error.config && !error.config._reintentadoCsrf) {
      error.config._reintentadoCsrf = true
      return client.get('/sanctum/csrf-cookie').then(() => client(error.config))
    }

    const url = error.config?.url ?? ''
    const esRutaExcluida = url.includes('/login') || url.includes('/register') || url.includes('/me')

    if (error.response?.status === 401 && !esRutaExcluida && !avisandoSesionCaducada) {
      avisandoSesionCaducada = true
      window.dispatchEvent(new CustomEvent('sesion-caducada'))
    }

    return Promise.reject(error)
  }
)

export default client