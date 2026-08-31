import { Component } from 'react'

class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { tieneError: false }
  }

  static getDerivedStateFromError() {
    return { tieneError: true }
  }

  componentDidCatch(error, info) {
    console.error('Error capturado por ErrorBoundary:', error, info)
  }

  render() {
    if (this.state.tieneError) {
      return (
        <div className="min-h-screen flex items-center justify-center px-4">
          <div className="text-center max-w-sm">
            <p className="text-5xl mb-3">⚠️</p>
            <h1 className="font-display text-xl text-texto mb-2">Algo se ha torcido</h1>
            <p className="font-body text-sm text-borde mb-6">
              Ha ocurrido un error inesperado. Prueba a recargar la página.
            </p>
            <button
              onClick={() => window.location.reload()}
              className="bg-acento text-fondo font-body font-semibold text-sm rounded px-5 py-2.5 hover:brightness-110"
            >
              Recargar
            </button>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}

export default ErrorBoundary