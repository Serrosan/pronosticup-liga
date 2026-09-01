import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useTheme } from '../context/ThemeContext'

function Avatar({ url, nombre, tamano = 'w-8 h-8' }) {
  if (url) return <img src={url} alt={nombre} className={`${tamano} rounded-full object-cover shrink-0`} />
  return (
    <div className={`${tamano} rounded-full bg-acento/15 flex items-center justify-center shrink-0`}>
      <span className="font-display text-xs text-acento">{nombre?.[0]?.toUpperCase()}</span>
    </div>
  )
}

function UserMenu() {
  const [abierto, setAbierto] = useState(false)
  const [copiado, setCopiado] = useState(false)
  const { usuario, logout } = useAuth()
  const { tema, alternarTema } = useTheme()
  const queryClient = useQueryClient()

  const { data: ligas } = useQuery({
    queryKey: ['mis-ligas'],
    queryFn: async () => (await client.get('/api/v1/ligas')).data.data,
    enabled: abierto,
  })

  const cambiarLiga = useMutation({
    mutationFn: (ligaId) => client.patch('/api/v1/liga-activa', { liga_id: ligaId }),
    onSuccess: () => {
      queryClient.invalidateQueries()
      window.location.href = '/dashboard'
    },
  })

  function copiarCodigo() {
    navigator.clipboard.writeText(usuario.liga_activa.codigo_acceso)
    setCopiado(true)
    setTimeout(() => setCopiado(false), 2000)
  }

  return (
    <div className="relative">
      <button onClick={() => setAbierto(!abierto)} className="flex items-center gap-2 hover:opacity-80 transition">
        <Avatar url={usuario?.avatar_url} nombre={usuario?.nombre} />
        <span className="font-body text-sm text-texto whitespace-nowrap hidden sm:inline">{usuario?.nombre}</span>
        <span className="text-borde text-xs">▾</span>
      </button>

      {abierto && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setAbierto(false)} />
          <div className="absolute right-0 mt-2 w-72 bg-fondo border border-borde/30 rounded-lg shadow-lg z-40 overflow-hidden">
            <div className="px-4 py-3.5 flex items-center gap-3 bg-borde/5">
              <Avatar url={usuario?.avatar_url} nombre={usuario?.nombre} tamano="w-11 h-11" />
              <div className="min-w-0">
                <p className="font-body text-sm font-semibold text-texto truncate">{usuario?.nombre}</p>
                <p className="font-body text-xs text-borde truncate">{usuario?.liga_activa?.nombre ?? 'Sin liga'}</p>
              </div>
            </div>

            {usuario?.liga_activa?.codigo_acceso && (
              <div className="px-4 py-3 border-t border-borde/10 flex items-center justify-between gap-2">
                <div className="min-w-0">
                  <p className="font-body text-[9px] uppercase tracking-widest text-borde">Código de invitación</p>
                  <p className="font-marcador text-sm text-texto tracking-widest">{usuario.liga_activa.codigo_acceso}</p>
                </div>
                <button
                  onClick={copiarCodigo}
                  className="font-body text-xs font-semibold text-acento hover:underline shrink-0"
                >
                  {copiado ? '✓ Copiado' : 'Copiar'}
                </button>
              </div>
            )}

            {ligas?.length > 1 && (
              <div className="px-4 py-3 border-t border-borde/10">
                <p className="font-body text-[9px] uppercase tracking-widest text-borde mb-1.5">Cambiar de liga</p>
                {ligas.map((liga) => (
                  <button
                    key={liga.id}
                    onClick={() => { setAbierto(false); cambiarLiga.mutate(liga.id) }}
                    className={`w-full text-left font-body text-sm px-2 py-1.5 rounded hover:bg-acento/10 ${
                      liga.id === usuario?.liga_activa?.id ? 'text-acento font-semibold' : 'text-texto'
                    }`}
                  >
                    {liga.nombre} {liga.id === usuario?.liga_activa?.id && '✓'}
                  </button>
                ))}
              </div>
            )}

            <div className="border-t border-borde/10">
              <button
                onClick={alternarTema}
                className="w-full flex items-center justify-between font-body text-sm text-texto px-4 py-2.5 hover:bg-borde/10"
              >
                <span>Apariencia</span>
                <span className="flex items-center gap-1.5 text-borde text-xs">
                  {tema === 'oscuro' ? '🌙 Oscuro' : '☀️ Claro'}
                </span>
              </button>
              <Link to="/perfil" onClick={() => setAbierto(false)} className="block font-body text-sm text-texto px-4 py-2.5 hover:bg-borde/10">
                Mi perfil
              </Link>
              <Link to="/privacidad" onClick={() => setAbierto(false)} className="block font-body text-sm text-texto px-4 py-2.5 hover:bg-borde/10">
                Privacidad
              </Link>
              <button onClick={logout} className="w-full text-left font-body text-sm text-red-500 px-4 py-2.5 hover:bg-borde/10">
                Cerrar sesión
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}

export default UserMenu