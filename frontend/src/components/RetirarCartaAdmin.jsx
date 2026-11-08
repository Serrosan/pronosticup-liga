import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from './SelectTema'
import { useToast } from '../context/ToastContext'

// Admin → Tipos de carta: retirar una carta del juego y, si se quiere, dar una
// tirada a cada uno por cada carta que tuviera en la mano.
export default function RetirarCartaAdmin({ tipos }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [abierto, setAbierto] = useState(false)
  const [idTipo, setIdTipo] = useState('')
  const [compensar, setCompensar] = useState(true)
  const [rareza, setRareza] = useState('')

  const { data: previa, isFetching } = useQuery({
    queryKey: ['admin', 'retirada-previa', idTipo],
    queryFn: async () => (await client.get(`/api/v1/admin/tipos-carta/${idTipo}/retirada`)).data.data,
    enabled: !!idTipo,
  })

  const retirar = useMutation({
    mutationFn: () => client.post(`/api/v1/admin/tipos-carta/${idTipo}/retirar`, { compensar, rareza: rareza || null }),
    onSuccess: (r) => {
      toast.exito(r.data.message)
      setIdTipo('')
      queryClient.invalidateQueries()
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo retirar.'),
  })

  const activas = (tipos ?? []).filter((t) => t.activa)

  return (
    <div className="mb-5 bg-borde/5 border border-borde/20 rounded-lg p-4">
      <button onClick={() => setAbierto(!abierto)} className="font-body text-sm font-semibold text-texto flex items-center gap-2">
        🗑️ Retirar una carta del juego {abierto ? '▲' : '▼'}
      </button>
      {abierto && (
        <div className="mt-3 flex flex-col gap-3 max-w-lg">
          <p className="font-body text-[11px] text-borde">
            Deja de salir en repartos y se quita de las manos. Si la desactivas desde la tabla de abajo, también se quita, pero sin compensar.
          </p>
          <SelectTema
            value={idTipo}
            onChange={(e) => setIdTipo(e.target.value)}
            options={[{ value: '', label: 'Elige la carta...' }, ...activas.map((t) => ({ value: String(t.id), label: `${t.nombre} (${t.categoria_nombre} · ${t.rareza})` }))]}
            className="w-full bg-fondo"
          />

          {idTipo && (isFetching || !previa ? (
            <p className="font-body text-xs text-borde">Mirando quién la tiene…</p>
          ) : (
            <>
              <div className="font-body text-xs text-texto">
                {previa.en_mano === 0 ? 'Nadie la tiene en la mano ahora mismo.' : (
                  <>
                    <p className="mb-1">La tienen en la mano <strong>{previa.en_mano}</strong> vez/veces:</p>
                    <ul className="text-borde">
                      {previa.jugadores.map((j, i) => <li key={i}>• {j.nombre} ({j.liga}): {j.cartas}</li>)}
                    </ul>
                  </>
                )}
              </div>

              {previa.en_mano > 0 && (
                <label className="flex items-start gap-2 font-body text-xs text-texto cursor-pointer">
                  <input type="checkbox" checked={compensar} onChange={(e) => setCompensar(e.target.checked)} className="mt-0.5 accent-acento" />
                  <span>Dar una tirada (sobre al azar) por cada carta retirada, con aviso a cada uno</span>
                </label>
              )}
              {previa.en_mano > 0 && compensar && (
                <SelectTema
                  value={rareza}
                  onChange={(e) => setRareza(e.target.value)}
                  options={[{ value: '', label: 'Rareza según los porcentajes de la liga' }, { value: 'Comun', label: 'Siempre Común' }]}
                  className="w-full bg-fondo"
                />
              )}

              <button
                onClick={() => window.confirm(`¿Retirar «${previa.carta}» del juego?${previa.en_mano ? ` Se quitará de ${previa.en_mano} mano(s)${compensar ? ' y se dará una tirada por cada una' : ' sin compensación'}.` : ''} No se puede deshacer.`) && retirar.mutate()}
                disabled={retirar.isPending}
                className="self-start font-body text-sm font-semibold text-white bg-red-500 rounded px-4 py-2 hover:brightness-110 disabled:opacity-50"
              >
                {retirar.isPending ? 'Retirando…' : '🗑️ Retirar del juego'}
              </button>
            </>
          ))}
        </div>
      )}
    </div>
  )
}
