import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider } from './context/ThemeContext'
import App from './App.jsx'
import './index.css'

if (window.location.search.includes('debug=1')) {
  const script = document.createElement('script')
  script.src = 'https://cdn.jsdelivr.net/npm/eruda'
  script.onload = () => window.eruda && window.eruda.init()
  document.body.appendChild(script)
}

const queryClient = new QueryClient()

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <App />
      </QueryClientProvider>
    </ThemeProvider>
  </StrictMode>,
)