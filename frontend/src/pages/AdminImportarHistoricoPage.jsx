import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import client from '../api/client'
import SelectTema from '../components/SelectTema'

function AdminImportarHistoricoPage() {
  const [idLiga, setIdLiga] = useState('')
  const [email, setEmail] = useState('')
  const [texto, setTexto] = useState('')
  const [resultado, setResultado] = useState(null)

  const { data: ligas } = useQuery({
    queryKey: ['admin', 'ligas'],
    queryFn: async () => (await client.get('/api/v1/admin/ligas')).data.data,
  })

  const importar = useMutation({
    mutationFn: () => client.post('/api/v1/admin/importar-historico', { id_liga: idLiga, email, texto }),
    onSuccess: (respuesta) => setResultado(respuesta.data),
    onError: (err) => setResultado({ message: err.response?.data?.message ?? 'Error al importar.', esError: true }),
  })

  return (
    <div className="max-w-2xl">
      <h2 className="font-display text-xl text-texto mb-4">Importar pronósticos históricos</h2>
      <p className="font-body text-sm text-borde mb-6">
        Copia desde tu Excel las columnas Jornada / Partido / Goles local / Goles visitante (con la cabecera incluida) y pégalas abajo.
      </p>

      <div className="bg-fondo border border-borde/30 rounded-lg p-5 flex flex-col gap-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="font-body text-xs text-borde block mb-1">Liga</label>
            <SelectTema
              value={idLiga}
              onChange={(e) => setIdLiga(e.target.value)}
              placeholder="Elige una liga..."
              options={ligas?.map((l) => ({ value: l.id, label: l.nombre })) ?? []}
              className="w-full"
            />
          </div>

          <div>
            <label className="font-body text-xs text-borde block mb-1">Email de la persona</label>
            <input
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="ej. serrosan@hotmail.com"
              className="w-full font-body bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2"
            />
          </div>
        </div>

        <div>
          <label className="font-body text-xs text-borde block mb-1">Tabla pegada desde Excel</label>
          <textarea
            value={texto}
            onChange={(e) => setTexto(e.target.value)}
            rows={14}
            placeholder={'Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tDeportivo Alavés - Getafe CF\t0\t1\n...'}
            className="w-full font-body text-xs bg-borde/10 text-texto rounded border border-borde/40 px-3 py-2 font-mono"
          />
        </div>

        <button
          onClick={() => importar.mutate()}
          disabled={importar.isPending || !idLiga || !email || !texto}
          className="font-body text-sm font-semibold bg-acento text-fondo rounded py-2.5 hover:brightness-110 disabled:opacity-50"
        >
          {importar.isPending ? 'Importando...' : 'Importar pronósticos'}
        </button>

        {resultado && (
          <div className={`rounded-lg p-4 ${resultado.esError ? 'bg-red-500/10' : 'bg-acento/10'}`}>
            <p className={`font-body text-sm font-semibold ${resultado.esError ? 'text-red-500' : 'text-acento'}`}>
              {resultado.message}
            </p>
            {resultado.partidos_no_encontrados?.length > 0 && (
              <div className="mt-2">
                <p className="font-body text-xs text-borde mb-1">Partidos no encontrados:</p>
                <ul className="font-body text-xs text-borde list-disc list-inside">
                  {resultado.partidos_no_encontrados.map((p, i) => <li key={i}>{p}</li>)}
                </ul>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

export default AdminImportarHistoricoPage