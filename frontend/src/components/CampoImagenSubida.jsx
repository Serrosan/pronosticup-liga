import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import client from '../api/client'

function CampoImagenSubida({ label, valor, onChange, carpeta }) {
  const [error, setError] = useState(null)

  const subir = useMutation({
    mutationFn: (archivo) => {
      const formData = new FormData()
      formData.append('imagen', archivo)
      formData.append('carpeta', carpeta)
      return client.post('/api/v1/admin/subir-imagen', formData)
    },
    onSuccess: (respuesta) => { onChange(respuesta.data.url); setError(null) },
    onError: (err) => setError(err.response?.data?.message ?? 'Error al subir la imagen.'),
  })

  function handleFile(event) {
    const archivo = event.target.files[0]
    if (archivo) subir.mutate(archivo)
  }

  return (
    <div>
      <label className="font-body text-xs text-borde block mb-1">{label}</label>
      <div className="flex items-center gap-3">
        {valor && <img src={valor} alt="" className="w-14 h-14 rounded object-contain border border-borde/30 shrink-0" />}
        <label className="font-body text-xs font-semibold bg-borde/20 text-texto rounded px-3 py-2 hover:bg-borde/30 cursor-pointer">
          {subir.isPending ? 'Subiendo...' : 'Elegir archivo'}
          <input type="file" accept="image/png,image/jpeg,image/webp" onChange={handleFile} className="hidden" disabled={subir.isPending} />
        </label>
      </div>
      {error && <p className="font-body text-xs text-red-500 mt-1">{error}</p>}
    </div>
  )
}

export default CampoImagenSubida