import axios from 'axios'

const client = axios.create({
  baseURL: 'http://localhost',
  withCredentials: true,
  withXSRFToken: true,
})

let avisandoSesionCaducada = false

client.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
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