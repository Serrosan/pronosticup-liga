import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient, useInfiniteQuery } from '@tanstack/react-query'
import client from '../api/client'

function tiempoRelativo(fechaISO) {
  const minutos = Math.round((Date.now() - new Date(fechaISO)) / 60000)
  if (minutos < 1) return 'ahora mismo'
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.round(minutos / 60)
  if (horas < 24) return `hace ${horas}h`
  return `hace ${Math.round(horas / 24)}d`
}

function CampanaNotificaciones() {
  const [abierto, setAbierto] = useState(false)
  const queryClient = useQueryClient()

  const { data: noLeidas } = useQuery({
    queryKey: ['notificaciones-no-leidas'],
    queryFn: async () => (await client.get('/api/v1/notificaciones/no-leidas')).data,
    refetchInterval: 15000,
  })

  const {
    data: paginas,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery({
    queryKey: ['notificaciones-lista'],
    queryFn: async ({ pageParam = 1 }) => {
      const respuesta = await client.get('/api/v1/notificaciones', { params: { pagina: pageParam } })
      return respuesta.data
    },
    getNextPageParam: (ultimaPagina) => (ultimaPagina.meta.hay_mas ? ultimaPagina.meta.pagina + 1 : undefined),
    initialPageParam: 1,
    enabled: abierto,
  })

  const lista = paginas?.pages.flatMap((p) => p.data) ?? null

  const marcarLeida = useMutation({
    mutationFn: (id) => client.post(`/api/v1/notificaciones/${id}/leer`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notificaciones-no-leidas'] })
      queryClient.invalidateQueries({ queryKey: ['notificaciones-lista'] })
    },
  })

  const marcarTodas = useMutation({
    mutationFn: () => client.post('/api/v1/notificaciones/leer-todas'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notificaciones-no-leidas'] })
      queryClient.invalidateQueries({ queryKey: ['notificaciones-lista'] })
    },
  })

  const eliminar = useMutation({
    mutationFn: (id) => client.delete(`/api/v1/notificaciones/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notificaciones-no-leidas'] })
      queryClient.invalidateQueries({ queryKey: ['notificaciones-lista'] })
    },
  })

  const borrarLeidas = useMutation({
    mutationFn: () => client.delete('/api/v1/notificaciones/leidas'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notificaciones-no-leidas'] })
      queryClient.invalidateQueries({ queryKey: ['notificaciones-lista'] })
    },
  })

  useEffect(() => {
    if (abierto && lista?.some((n) => !n.leida)) {
      const timeoutId = setTimeout(() => marcarTodas.mutate(), 1200)
      return () => clearTimeout(timeoutId)
    }
  }, [abierto, lista])

  const total = noLeidas?.total ?? 0
  const hayLeidas = lista?.some((n) => n.leida) ?? false

  return (
    <div className="relative">
      <button onClick={() => setAbierto(!abierto)} className="relative p-1.5 hover:opacity-80 transition" aria-label="Notificaciones">
        <span className="text-xl">🔔</span>
        {total > 0 && (
          <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1">
            {total > 9 ? '9+' : total}
          </span>
        )}
      </button>

      {abierto && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setAbierto(false)} />

          <div
            className="
              fixed left-1/2 top-20 -translate-x-1/2 w-[calc(100vw-2rem)] max-w-sm
              sm:absolute sm:left-auto sm:right-0 sm:top-full sm:translate-x-0 sm:mt-2 sm:w-80 sm:max-w-none
              bg-fondo border border-borde/30 rounded-lg shadow-lg z-40 overflow-hidden
            "
          >
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-borde/20">
              <p className="font-display text-sm text-texto">Notificaciones</p>
              <div className="flex items-center gap-3">
                {total > 0 && (
                  <button onClick={() => marcarTodas.mutate()} className="font-body text-[10px] text-acento hover:underline">
                    Marcar leídas
                  </button>
                )}
                {hayLeidas && (
                  <button onClick={() => borrarLeidas.mutate()} className="font-body text-[10px] text-borde hover:text-red-500">
                    Borrar leídas
                  </button>
                )}
              </div>
            </div>

            <div className="max-h-80 overflow-y-auto">
              {!lista ? (
                <p className="font-body text-xs text-borde text-center py-6">Cargando...</p>
              ) : lista.length === 0 ? (
                <p className="font-body text-xs text-borde text-center py-6">No tienes notificaciones todavía.</p>
              ) : (
                <>
                  {lista.map((n) => (
                    <div
                      key={n.id}
                      className={`group px-4 py-3 border-b border-borde/10 last:border-0 hover:bg-borde/5 ${!n.leida ? 'bg-acento/5' : ''}`}
                    >
                      <div className="flex items-start gap-2">
                        <div
                          onClick={() => !n.leida && marcarLeida.mutate(n.id)}
                          className="flex items-start gap-2 flex-1 min-w-0 cursor-pointer"
                        >
                          {!n.leida ? (
                            <span className="w-1.5 h-1.5 rounded-full bg-acento mt-1.5 shrink-0" />
                          ) : (
                            <span className="w-1.5 h-1.5 mt-1.5 shrink-0" />
                          )}
                          <div className="min-w-0">
                            <p className={`font-body text-xs ${n.leida ? 'text-borde' : 'font-semibold text-texto'}`}>
                              {n.titulo}
                            </p>
                            <p className={`font-body text-xs mt-0.5 ${n.leida ? 'text-borde/70' : 'text-borde'}`}>
                              {n.mensaje}
                            </p>
                            <p className="font-body text-[10px] text-borde/60 mt-1">{tiempoRelativo(n.creada_en)}</p>
                          </div>
                        </div>
                        <button
                          onClick={() => eliminar.mutate(n.id)}
                          className="font-body text-borde/40 hover:text-red-500 text-xs shrink-0 opacity-0 group-hover:opacity-100 transition"
                          title="Eliminar"
                        >
                          🗑️
                        </button>
                      </div>
                    </div>
                  ))}

                  {hasNextPage && (
                    <button
                      onClick={() => fetchNextPage()}
                      disabled={isFetchingNextPage}
                      className="w-full font-body text-xs text-acento hover:underline py-2.5 disabled:opacity-50"
                    >
                      {isFetchingNextPage ? 'Cargando...' : 'Cargar más'}
                    </button>
                  )}
                </>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  )
}

export default CampanaNotificaciones