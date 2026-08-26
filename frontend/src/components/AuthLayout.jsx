import { useTheme } from '../context/ThemeContext'

function AuthLayout({ children }) {
  const { tema, alternarTema } = useTheme()

  return (
    <div
      className="min-h-screen relative flex items-center justify-center px-4 py-12 overflow-hidden bg-cover bg-center"
      style={{
        backgroundImage:
          "linear-gradient(var(--overlay-color), var(--overlay-color)), url('https://images.unsplash.com/photo-1459865264687-595d652de67e?fm=jpg&q=80&w=2000&auto=format&fit=crop')",
                        '--overlay-color': tema === 'oscuro' ? 'rgba(14, 27, 43, 0.82)' : 'rgba(238, 242, 239, 0.70)',
      }}
    >
      <button
        onClick={alternarTema}
        className="absolute top-4 right-4 font-body text-xs text-borde hover:text-texto border border-borde/40 rounded px-2 py-1 z-10 bg-fondo/60 backdrop-blur-sm"
      >
        {tema === 'oscuro' ? '☀️ Claro' : '🌙 Oscuro'}
      </button>

      <div className="relative w-full max-w-md z-10">
        <div className="bg-borde/10 border border-borde/30 rounded-t-2xl px-6 pt-6 pb-5 text-center backdrop-blur-sm">
          <div className="flex items-center justify-center gap-2">
            <span className="font-display text-2xl text-texto tracking-wide">PronostiCup</span>
            <span className="font-body font-bold text-[10px] bg-acento text-fondo rounded px-1.5 py-0.5 tracking-widest">
              LIGA
            </span>
          </div>
          <p className="font-body text-xs text-borde mt-1">Jornada tras jornada, con tu grupo</p>
        </div>

        <div className="relative border-t-2 border-dashed border-borde/30">
          <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
          <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
        </div>

        <div className="bg-fondo border border-borde/30 border-t-0 rounded-b-2xl px-6 py-8 shadow-xl shadow-black/20 backdrop-blur-sm">
          {children}
        </div>
      </div>
    </div>
  )
}

export default AuthLayout