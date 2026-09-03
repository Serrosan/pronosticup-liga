function TicketHeader({ titulo, accion }) {
  return (
    <>
      <div className="bg-acento/10 dark:bg-acento/10 border-b border-acento/20 px-4 py-2.5 flex items-center justify-between">
        <span className="font-body text-[10px] uppercase tracking-widest text-fondo dark:text-acento font-bold dark:font-semibold px-2 py-0.5 rounded bg-acento dark:bg-transparent dark:px-0 dark:py-0">
          {titulo}
        </span>
        {accion}
      </div>
      <div className="relative border-t-2 border-dashed border-borde/30">
        <div className="absolute -left-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
        <div className="absolute -right-3 -top-3 w-6 h-6 rounded-full bg-fondo border border-borde/30" />
      </div>
    </>
  )
}

export default TicketHeader