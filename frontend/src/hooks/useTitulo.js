import { useEffect } from 'react'

function useTitulo(titulo) {
  useEffect(() => {
    document.title = titulo ? `${titulo} · PronostiCup Liga` : 'PronostiCup Liga'
  }, [titulo])
}

export default useTitulo