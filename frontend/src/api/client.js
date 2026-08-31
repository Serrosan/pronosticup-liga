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
    const esRutaAuth = error.config?.url?.includes('/login') || error.config?.url?.includes('/register')

    if (error.response?.status === 401 && !esRutaAuth && !avisandoSesionCaducada) {
      avisandoSesionCaducada = true
      window.dispatchEvent(new CustomEvent('sesion-caducada'))
    }

    return Promise.reject(error)
  }
)

export default client