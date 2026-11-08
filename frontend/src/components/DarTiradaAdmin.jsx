import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from './SelectTema'
import { useToast } from '../context/ToastContext'

const RAREZAS = [
  { value: '', label: 'Según los porcentajes de la liga' },
  { value: 'Comun', label: 'Siempre Común' },
  { value: 'PocoComun', label: 'Siempre Poco común' },
  { value: 'Rara', label: 'Siempre Rara' },
  { value: 'Legendaria', label: 'Siempre Legendaria' },
]

// Admin → Cartas de usuarios: dar a un jugador un sobre con una carta al azar.
export default function DarTiradaAdmin({ idLiga, idUsuario, tiposCarta }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [idCategoria, setIdCategoria] = useState('')
  const [rareza, setRareza] = useState('')

  const categorias = Object.values(
    (tiposCarta ?? []).filter((t) => t.activa).reduce((acc, t) => ({ ...acc, [t.id_categoria]: { value: String(t.id_categoria), label: t.categoria_nombre } }), {}),
  )

  const dar = useMutation({
    mutationFn: () => client.post('/api/v1/admin/cartas-usuario/tirada', {
      id_liga: idLiga, id_usuario: idUsuario, id_categoria: idCategoria || null, rareza: rareza || null,
    }),
    onSuccess: (r) => {
      toast.exito(r.data.message ?? 'Sobre entregado.')
      queryClient.invalidateQueries({ queryKey: ['admin', 'cartas-usuario', idLiga, idUsuario] })
    },
    onError: (err) => toast.error(err.response?.data?.message ?? 'No se pudo dar la tirada.'),
  })

  return (
    <div className="bg-borde/5 border border-borde/20 rounded-lg p-4 mb-6 max-w-lg">
      <p className="font-body text-sm font-semibold text-texto mb-1">🎲 Dar una tirada</p>
      <p className="font-body text-[11px] text-borde mb-3">Un sobre cerrado con una carta al azar, como en el reparto. Lo descubre al abrirlo.</p>
      <div className="flex flex-col sm:flex-row gap-2">
        <SelectTema
          value={idCategoria}
          onChange={(e) => setIdCategoria(e.target.value)}
          options={[{ value: '', label: 'Categoría al azar' }, ...categorias]}
          className="flex-1 bg-fondo"
        />
        <SelectTema value={rareza} onChange={(e) => setRareza(e.target.value)} options={RAREZAS} className="flex-1 bg-fondo" />
      </div>
      <button
        onClick={() => dar.mutate()}
        disabled={dar.isPending}
        className="mt-2 font-body text-sm font-semibold bg-acento text-fondo rounded px-4 py-2 hover:brightness-110 disabled:opacity-50"
      >
        {dar.isPending ? 'Dando...' : '🎲 Dar tirada'}
      </button>
    </div>
  )
}
