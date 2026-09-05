import { useState, useEffect } from 'react'

function BotonVolverArriba() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    function alHacerScroll() {
      setVisible(window.scrollY > 400)
    }
    window.addEventListener('scroll', alHacerScroll)
    return () => window.removeEventListener('scroll', alHacerScroll)
  }, [])

  if (!visible) return null

  return (
    <button
      onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
      className="fixed bottom-6 right-6 w-11 h-11 rounded-full bg-acento text-fondo shadow-lg flex items-center justify-center hover:brightness-110 transition z-40"
      title="Volver arriba"
    >
      ↑
    </button>
  )
}

export default BotonVolverArriba