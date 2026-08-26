import { createContext, useContext, useEffect, useState } from 'react'

const ThemeContext = createContext(null)

export function ThemeProvider({ children }) {
  const [tema, setTema] = useState(() => {
    const guardado = localStorage.getItem('tema')
    if (guardado) return guardado
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro'
  })

  useEffect(() => {
    document.documentElement.classList.toggle('dark', tema === 'oscuro')
    localStorage.setItem('tema', tema)
  }, [tema])

  function alternarTema() {
    setTema((actual) => (actual === 'oscuro' ? 'claro' : 'oscuro'))
  }

  return (
    <ThemeContext.Provider value={{ tema, alternarTema }}>
      {children}
    </ThemeContext.Provider>
  )
}

export function useTheme() {
  return useContext(ThemeContext)
}