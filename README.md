# PronostiCup Liga

App de pronósticos de LaLiga para jugar entre amigos: pronósticos de cada partido,
goleadores, quinielas, clasificación por liga y, en las ligas con extras, un juego de
**cartas** (Jugadas y Faltas) que cambia los puntos de cada jornada.

En producción en **https://www.pronosticup.es** con dos ligas reales:

| Liga | Tipo | Cartas |
|---|---|---|
| BasiLiga (id 5) | `ConExtras` | Sí, desde la jornada 8 |
| Liga Familia | Normal | No |

Repositorio **privado** (y así debe seguir).

---

## Documentación

| Documento | Para qué |
|---|---|
| [Chuletas](docs/chuletas.md) | **Empieza aquí.** Comandos y consultas rápidas para comprobar cosas, en local, en el servidor y en la base de datos |
| [Operación del admin](docs/03-operacion-admin.md) | El cierre de jornada paso a paso y el resto de tareas del panel de administración |
| [Reglas del juego](docs/02-reglas-del-juego.md) | Cómo se puntúa, cuándo se abre una jornada, cartas y faltas: las reglas tal como están programadas |
| [Arquitectura](docs/01-arquitectura.md) | Cómo está montada la app: piezas, servicios clave, tareas programadas, avisos |
| [Producción](docs/04-produccion.md) | Servidor, Coolify, despliegues, volúmenes, copias, seguridad y qué hacer ante un aviso de seguridad |
| [Desarrollo](docs/05-desarrollo.md) | Entorno local, tests, cómo se aplican los cambios y cómo actualizar dependencias |
| [Decisiones](docs/decisiones.md) | Por qué las cosas son como son. Antes de "arreglar" algo raro, mirar aquí |
| [Pendientes](docs/pendientes.md) | Ideas y tareas apuntadas para más adelante |

---

## Stack en una línea

Laravel 13 (PHP 8.5) como API REST + React 19 / Vite 8 / TanStack Query / Tailwind 4 como
SPA, autenticación con Sanctum (cookies de sesión), MySQL, desplegado con Docker en un VPS
de Contabo gestionado con Coolify.

Datos externos: **football-data.org** (calendario, resultados, horarios) y **LaLiga.com**
(alineaciones, estadísticas y eventos de cada partido, con un scraper propio).

## Arrancar en local (resumen)

```bash
cd ~/proyectos/pronosticup-liga
./vendor/bin/sail up -d                      # backend (Laravel + MySQL)
(cd frontend && docker compose up -d)        # frontend (Vite)
./vendor/bin/sail artisan test --compact     # tests
```

Frontend en http://localhost:5173 · API en http://localhost. Detalles en
[Desarrollo](docs/05-desarrollo.md).
