import { createContext, useContext, useState } from 'react'
import client from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(null)

  async function login(email, password) {
    await client.get('/sanctum/csrf-cookie')
    const respuesta = await client.post('/api/v1/login', { email, password })
    setUsuario(respuesta.data.data)
  }

  async function logout() {
    await client.post('/api/v1/logout')
    setUsuario(null)
  }

  return (
    <AuthContext.Provider value={{ usuario, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}