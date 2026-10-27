# Pendientes

Ideas y tareas apuntadas. Cuando algo se haga, se quita de aquí y, si era una decisión,
se apunta en [Decisiones](decisiones.md).

## Pronto

- [ ] **Partido estrella** (automático): un derbi de la lista (editable en el admin) o, si no
  hay, el partido entre los dos mejor clasificados de LaLiga. Se fija al abrir la jornada,
  dobla los puntos base del pronóstico y lleva ⭐.
- [ ] **Presentación**: una página para enseñar la app a quien no la conoce.
- [ ] **Pantalla de celebración** al cerrar la jornada. Pensada primero para el móvil y
  respetando "reducir movimiento"; tiene que quedar realmente bien.

## Cuando apetezca

<!-- Panel de Coolify: hecho el 26/10 (dominio con HTTPS y puertos cerrados, ver 04-produccion.md#cortafuegos). -->
- [ ] **Rediseño de Mis pronósticos** (la sección entera; el orden de jornadas ya está arreglado).
- [ ] **Logros**: rediseño completo (25 logros, secretos, premios con carta, distintivo
  final). Diseño a medias; falta decidir si los 25 incluyen los secretos y qué candidatos
  entran.
- [ ] **Silbato**: pasarlo a audio o quitarlo, porque cualquier Falta ya permite mensaje.
- [ ] **Copa eliminatoria**: una ronda por jornada con los puntos normales, cruces según la
  clasificación; en Liga Familia, con pases directos si son impares.
- [ ] **Salón de la fama** para cuando haya varias temporadas. Mirar antes qué récords ya
  salen en las estadísticas de la liga.
- [ ] **Forja de cartas** (a proponer al grupo): 3 comunes → 1 poco común, 3 poco comunes →
  1 rara, 4 raras → 1 legendaria; misma categoría, solo cartas en la mano, resultado al
  azar, una por jornada. Falta decidir si la Expulsión la bloquea.
- [ ] **Imágenes para compartir** con una librería moderna y vista previa al pegar un
  enlace (Open Graph desde el servidor). Gordo.
- [ ] **Accesos directos** del icono de la app (manifest `shortcuts`).
- [ ] **Terminal de Coolify** ("Terminal access was rejected"): mirar el ajuste de acceso a
  terminal del servidor o los puertos 6001/6002 en `ufw`.
- [ ] **Vigilante externo** de que la tarea de cada minuto sigue viva (tipo
  healthchecks.io). Posible, no urgente.
<!-- www / sin www: hecho el 26/10 (pronosticup.es redirige a www desde Coolify; APP_URL con www). -->
- [ ] **PHPUnit 13**: salto de versión grande, aparte.
- [ ] **Wrapped** de fin de temporada: imagen para compartir de verdad, no con
  html2canvas.

## Enero (mercado de invierno)

- [ ] Repasar el **comparador de plantillas**.
- [ ] Revisar los 48 identificadores de football-data asignados automáticamente y subir el
  listón de parecido de nombres (hoy 55 %).
- [ ] Enlazar a mano: Rodri (id 508), Arana (349), Peque (552), Yeremay (372).
- [ ] Meter traspasos y bajas de `comparar-plantillas` en la cola de cambios de plantilla.

## Ideas sueltas

- Cartas que usen datos de la ficha del jugador (p. ej. +5 si marca un zurdo).
- "Marcaje personal": bloquear 1–2 goleadores concretos del rival. Necesitaría la tabla
  `carta_jugadores`.
- Mostrar más datos del scraper: estadísticas en el detalle del partido, curiosidades,
  ficha de jugador.
