# Decisiones

Por qué las cosas son como son. Antes de "arreglar" algo que parece raro, buscarlo aquí.
Las más recientes, arriba.

| Fecha | Decisión | Por qué |
|---|---|---|
| 2026-10-27 | **"Cerrar todo"** por liga en Cierre de jornada, solo con todo en verde; los botones sueltos siguen | Ahorrar clics sin saltarse ningún aviso: si algo no está limpio, se va paso a paso |
| 2026-10-27 | Aviso del **resultado de cada partido** solo al móvil y **apagado** de partida | Unos 10 avisos por jornada llenarían la campana; quien lo quiera, lo activa |
| 2026-10-27 | **Versión nueva**: `/version.json` público (solo la fecha del build) y franja con "Actualizar"; nunca se fuerza la recarga | Práctica habitual, no enseña nada aprovechable; quien está a mitad de un pronóstico termina antes |
| 2026-10-26 | Web unificada en **www** (`pronosticup.es` redirige desde Coolify; `APP_URL` con www) | Una sola dirección para cookies, avisos y enlaces |
| 2026-10-26 | Errores de la API con **su código y su mensaje** (403, 413, 419, 503); los 500 siguen sin detalles; un 419 se reintenta solo una vez | Antes un `abort(403)` llegaba como 500 genérico |
| 2026-10-26 | **Cerrar sesión en los demás dispositivos** (pide contraseña) y cambiar la contraseña hace lo mismo; quita también sus avisos al móvil | Para un móvil perdido o una sesión olvidada |
| 2026-10-26 | SSH **sigue con contraseña**, no con clave | Si se formatea el PC o se pierde la clave, no quedarse fuera. Lo protegen fail2ban y la contraseña fuerte |
| 2026-10-26 | El scraper **comprueba que el enlace elegido es del partido correcto** antes de importar | Caso Celta–Betis: "real" emparejó con el enlace del Clásico |
| 2026-10-21 | El correo de puntos sale al **calcular goleadores**, una sola vez, con el desglose completo | Al cerrar aún faltan goleadores y cartas de eventos |
| 2026-10-21 | PHP desde el **espejo `ppa.setup-php.com`** y construcción bloqueada si PHP < 8.5.12 | El día del parche, Launchpad daba 503; el espejo usa las mismas firmas |
| 2026-10-21 | Filtro de multipart en nginx, solo `index.php` ejecutable y `upload_progress` desactivado | Medidas provisionales del fallo de PHP. Se quedan como protección extra |
| 2026-10-19 | Sesión de **2 h** sin actividad + casilla **"Mantener la sesión iniciada" 30 días**, renovables en cada visita y marcada por defecto | Que un dispositivo olvidado se cierre, sin obligar a meter la contraseña cada día en el móvil propio |
| 2026-10-19 | En ligas con cartas, la jornada N+1 **no se abre hasta acabar la N** | Que nadie rellene una jornada antes de ver las Faltas que le han jugado |
| 2026-10-19 | Faltas iguales contra la misma persona **no se suman** (manda la más exigente) y **no se informa** al atacante | Evitar abusos y que no se pueda deducir qué ha jugado cada uno |
| 2026-10-19 | Las Faltas cuentan para la jornada **siguiente a la que está en juego** | Regla de juego confirmada; al terminar una jornada, "salta" a la siguiente |
| 2026-10-17 | Fallo clamoroso usa el Top 3 de goleadores **de la temporada**, empates en el 3.º incluidos | Antes miraba todas las temporadas |
| 2026-10-17 | Silbato y Amigo invisible exigen mensaje; el de Silbato es privado | Sin mensaje la carta no tiene sentido |
| 2026-10-17 | Avisos al móvil: todo lo de la campana, con preferencias por categoría; sin "no molestar" de momento; sin casos especiales para dispositivos antiguos | Que lleguen como una app nativa |
| 2026-10-13 | No se cierra una jornada sin **todos** sus partidos jugados | Nada de cerrar con 9 y recalcular después |
| 2026-10-13 | Traer producción a local con una **descarga desde el admin** (anonimizada), no con scripts por SSH | Seguridad |
| 2026-10-05 | Las cartas **no son retroactivas** (BasiLiga desde la jornada 8) | Las jornadas anteriores están bien cerradas y repartidas |
| 2026-10-02 | Escudo: al chocar con una Falta **se pierden las dos cartas** | Antes el atacante conservaba la suya y podía repetir sin coste |
| 2026-10-02 | Límite de **2 Jugadas** y **1 Falta** por jornada | Equilibrio |
| 2026-10-02 | Con la mano llena no se descarta nada automáticamente: no deja jugar hasta descartar | La decisión es del jugador |
| — | El historial de puntos **nunca se falsea** (Pleno Garantizado guarda el tipo real + `nota_carta`) | Que siempre se pueda explicar un punto |
| — | El **color nunca es la única señal** en la interfaz | Accesibilidad (cuesta distinguir colores) |
| — | Goles en propia puerta: cuentan para el marcador, **no** para el goleador | Regla del juego |
| — | Faltas de requisito (Casa, Visita, Gafas) con **prevalidación**: solo bloquea cuando ya es imposible cumplir | Ni bloqueo ingenuo ni castigo a posteriori |
| — | Cartas que se gastan solo para "ver información" (tipo Espejo): **no** | Toda carta debe tener una consecuencia real |
| — | Descartadas: robar puntos o Veto ("liaría la clasificación"), bloquear la edición, Presión mediática | — |
| — | El repo es **privado** | — |
