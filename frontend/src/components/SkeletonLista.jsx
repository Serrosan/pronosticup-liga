import Skeleton from './Skeleton'

function SkeletonLista({ filas = 6 }) {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg overflow-hidden">
      {Array.from({ length: filas }, (_, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3 border-b border-borde/10 last:border-0">
          <Skeleton className="w-8 h-8 rounded-full" />
          <Skeleton className="flex-1 h-4" />
          <Skeleton className="w-10 h-4" />
        </div>
      ))}
    </div>
  )
}

export default SkeletonLista