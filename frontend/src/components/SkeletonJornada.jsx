import Skeleton from './Skeleton'

function SkeletonCardPartido() {
  return (
    <div className="bg-fondo border border-borde/30 rounded-lg p-4">
      <div className="flex items-center justify-between mb-3">
        <Skeleton className="w-20 h-5 rounded-full" />
        <Skeleton className="w-12 h-4" />
      </div>
      <div className="flex items-center justify-between gap-2 mb-1">
        <Skeleton className="w-9 h-9 rounded-full" />
        <Skeleton className="flex-1 h-4" />
        <Skeleton className="w-10 h-6" />
        <Skeleton className="flex-1 h-4" />
        <Skeleton className="w-9 h-9 rounded-full" />
      </div>
    </div>
  )
}

function SkeletonJornada() {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl mx-auto">
      {Array.from({ length: 4 }, (_, i) => <SkeletonCardPartido key={i} />)}
    </div>
  )
}

export default SkeletonJornada