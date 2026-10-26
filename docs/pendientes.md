# Pendientes

Ideas y tareas apuntadas. Cuando algo se haga, se quita de aquí y, si era una decisión,
se apunta en [Decisiones](decisiones.md).

## Pronto

- [ ] **Actualizaciones de dependencias** (preparadas el 22/10):
  - PHP de Sail local a 8.5.12;
  - Laravel 13.38 y paquetes menores;
  - Vite 8.3.5, que trae arreglos de seguridad del servidor de desarrollo;
  - React 19.3;
  - Node local 22.23.4.
  
  Ver [Desarrollo → Actualizar dependencias](05-desarrollo.md#actualizar-dependencias).
- [ ] **Errores de la API**: cualquier excepción sale como 500 genérico. Un token CSRF
  caducado debería ser 419 y no 500. No es de seguridad, solo queda feo.

## Cuando apetezca

- [ ] **Rediseño de Mis pronósticos** (la sección entera; el orden de jornadas ya está arreglado).
- [ ] **Logros**: rediseño completo (25 logros, secretos, premios con carta, distintivo
  final). Diseño a medias; falta decidir si los 25 incluyen los secretos y qué candidatos
  entran.
- [ ] **Silbato**: pasarlo a audio o quitarlo, porque cualquier Falta ya permite mensaje.
- [ ] **Cerrar sesión en todos los dispositivos**, y que cambiar la contraseña cierre las
  demás sesiones.
- [ ] **Aviso opcional por partido** con el resultado y lo que pusiste.
- [ ] **Terminal de Coolify** ("Terminal access was rejected"): mirar el ajuste de acceso a
  terminal del servidor o los puertos 6001/6002 en `ufw`.
- [ ] **Vigilante externo** de que la tarea de cada minuto sigue viva (tipo
  healthchecks.io). Posible, no urgente.
- [ ] **www / sin www**: unificar en uno. No urgente; si se hace, quedarse con el más usado
  (www).
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
