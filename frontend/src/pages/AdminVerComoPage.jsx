import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../api/client'
import { empezarVerComo } from '../utils/verComo'

// Elegir a quién mirar "como él". Lo que se ve después son las pantallas de
// siempre, con sus datos, en solo lectura.
function AdminVerComoPage() {
  const [filtro, setFiltro] = useState('')

  const { data: usuarios, isLoading, error } = useQuery({
    queryKey: ['admin-ver-como-usuarios'],
    queryFn: async () => (await client.get('/api/v1/admin/ver-como/usuarios')).data.data,
  })

  const texto = filtro.trim().toLowerCase()
  const visibles = (usuarios ?? []).filter(
    (u) => !texto || u.nombre?.toLowerCase().includes(texto) || u.email?.toLowerCase().includes(texto)
  )

  return (
    <div className="max-w-3xl">
      <h1 className="font-display text-2xl text-texto mb-1">Ver como un usuario</h1>
      <p className="font-body text-sm text-borde mb-4">
        Entras en la app viendo exactamente lo que ve esa persona: su portada, su jornada, sus pronósticos, su mano de cartas…
        Es <strong className="text-texto">solo lectura</strong>: cualquier intento de guardar algo se rechaza. Para volver, pulsa
        «Salir» en la franja roja de abajo.
      </p>

      <input
        value={filtro}
        onChange={(e) => setFiltro(e.target.value)}
        placeholder="Buscar por nombre o email"
        className="w-full font-body text-sm bg-fondo text-texto rounded border border-borde/40 px-3 py-2 mb-4 focus:outline-none focus:border-acento"
      />

      {isLoading && <p className="font-body text-sm text-borde">Cargando…</p>}
      {error && <p className="font-body text-sm text-red-500">{error.response?.data?.message ?? 'No se pudo cargar.'}</p>}

      <div className="bg-fondo border border-borde/30 rounded-lg divide-y divide-borde/10">
        {visibles.map((u) => (
          <div key={u.id} className="flex items-center gap-3 px-4 py-2.5">
            {u.avatar_url ? (
              <img src={u.avatar_url} alt="" className="w-8 h-8 rounded-full object-cover shrink-0" />
            ) : (
              <span className="w-8 h-8 rounded-full bg-acento/15 flex items-center justify-center text-xs font-semibold text-acento shrink-0">
                {u.nombre?.[0]?.toUpperCase()}
              </span>
            )}
            <div className="min-w-0 flex-1">
              <p className="font-body text-sm text-texto truncate">
                {u.nombre}
                {u.es_superadmin && <span className="ml-2 font-body text-[10px] text-borde border border-borde/30 rounded-full px-1.5 py-0.5">admin</span>}
              </p>
              <p className="font-body text-[11px] text-borde truncate">
                {u.email}
                {u.liga_activa ? ` · ${u.liga_activa}` : ' · sin liga activa'}
              </p>
            </div>
            <button
              type="button"
              onClick={() => empezarVerComo(u)}
              className="font-body text-xs font-semibold text-acento border border-acento/40 rounded-full px-3 py-1.5 hover:bg-acento/10 shrink-0"
            >
              👁 Ver como
            </button>
          </div>
        ))}
        {!isLoading && visibles.length === 0 && (
          <p className="font-body text-sm text-borde px-4 py-3">Nadie coincide con «{filtro}».</p>
        )}
      </div>
    </div>
  )
}

export default AdminVerComoPage
