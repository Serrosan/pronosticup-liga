import { createContext, useContext, useEffect, useState } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(null)
  const [cargando, setCargando] = useState(true)

  useEffect(() => {
    client.get('/api/v1/me')
      .then((respuesta) => setUsuario(respuesta.data.data))
      .catch(() => setUsuario(null))
      .finally(() => setCargando(false))
  }, [])

  async function login(email, password) {
    await client.get('/sanctum/csrf-cookie')
    const respuesta = await client.post('/api/v1/login', { email, password })
    setUsuario(respuesta.data.data)
  }

  async function logout() {
    await client.post('/api/v1/logout')
    setUsuario(null)
  }

  async function refrescar() {
    const respuesta = await client.get('/api/v1/me')
    setUsuario(respuesta.data.data)
  }

  return (
    <AuthContext.Provider value={{ usuario, login, logout, cargando, refrescar }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}