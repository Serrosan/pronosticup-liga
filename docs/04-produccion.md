# Producción

Servidor, despliegues, copias y seguridad.

- [El servidor](#el-servidor)
- [Cómo se construye la imagen](#cómo-se-construye-la-imagen)
- [Desplegar](#desplegar)
- [Volver atrás](#volver-atrás)
- [Variables de entorno](#variables-de-entorno)
- [Base de datos y HeidiSQL](#base-de-datos-y-heidisql)
- [Copias de seguridad](#copias-de-seguridad)
- [Seguridad](#seguridad)
- [Actualizar PHP](#actualizar-php)
- [Ante un aviso de seguridad](#ante-un-aviso-de-seguridad)

---

## El servidor

| | |
|---|---|
| Proveedor | Contabo, Cloud VPS 4 (4 vCPU, 8 GB, 100 GB), mes a mes |
| Panel | **Coolify** (conectado a GitHub mediante su GitHub App) |
| Dominio | `www.pronosticup.es` (las cookies van con dominio `.pronosticup.es`) |
| Protección | Firewall `ufw` + Fail2ban |
| Correo | Brevo (SMTP), con DKIM |
| Acceso | SSH (contraseña; cambiarlo está aparcado a propósito) y panel de Coolify |

En el propio servidor **no hay PHP instalado**: todo va dentro de los contenedores.

## Cómo se construye la imagen

`Dockerfile` + `compose.production.yaml`.

**Fase 1, `node:22-alpine`**: compila el frontend (`npm ci && npm run build`). En la
imagen final no queda Node.

**Fase 2, `ubuntu:24.04`**:
- PHP 8.5 desde el repositorio de Ondřej Surý, **a través del espejo `ppa.setup-php.com`**
  (más estable que Launchpad en días de avalancha; las firmas son las de Ondřej).
- Ajustes de seguridad de PHP en `docker/php-seguridad.ini`.
- **Control de versión**: la construcción falla si PHP es menor que 8.5.12. Así Coolify
  nunca sustituye un contenedor bueno por uno vulnerable.
- `ARG PHP_ACTUALIZADO=fecha`: cambiar esa fecha obliga a reinstalar PHP sin caché en el
  siguiente despliegue.
- nginx (`docker/nginx.conf`) y supervisord (`docker/supervisord.conf`): php-fpm, nginx y
  el bucle del scheduler.

**Al arrancar** (`docker/start-production.sh`): espera a MySQL, `storage:link`, cachea
config, rutas y vistas, **`migrate --force`** y ajusta permisos. Las migraciones se
aplican solas en cada despliegue.

**Volúmenes persistentes**, definidos en `compose.production.yaml`; Coolify los muestra
en solo lectura:

| Volumen | Ruta | Qué guarda |
|---|---|---|
| `storage-app` | `storage/app/public` | Avatares, logos, imágenes de cartas, adjuntos del chat |
| `storage-scraper` | `storage/app/scraper` | Avisos del scraper |
| `storage-logs` | `storage/logs` | Registros de errores (uno por día) |

Todo lo demás del contenedor se rehace limpio en cada despliegue.

**Healthcheck**: `GET /up` cada 30 s.

## Desplegar

1. `git push` a `main`.
2. Coolify despliega solo… **casi siempre**. Si en **Deployments** el último no es el de
   tu commit, pulsa **Deploy**.
3. Espera a **Finished** y comprueba lo que hayas cambiado.

Mientras se construye, la versión anterior sigue sirviendo. Si la construcción falla, **no
pasa nada**: el contenedor que ya estaba sigue en marcha. Mira el log del despliegue en
Coolify.

**Sin caché** (Force rebuild / No cache): solo cuando quieras que reinstale todo (PHP
incluido). Ojo: si el repositorio de PHP está caído, una construcción sin caché fallará.

**No despliegues mientras cierras una jornada**: una cosa detrás de otra.

## Volver atrás

En Coolify → la app → **Deployments**, cada despliegue anterior tiene su imagen. Se puede
volver a una de ellas. Si el problema es una migración, volver atrás la imagen **no
deshace** la migración: mejor arreglar hacia delante.

## Variables de entorno

Viven en **Coolify** (nunca en el repo; `.env` no se sube). Las importantes:

| Variable | Notas |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | Nunca `true` en producción |
| `APP_URL` | `https://www.pronosticup.es` |
| `SESSION_LIFETIME=120`, `SESSION_DOMAIN` | 2 h de inactividad; dominio `.pronosticup.es` |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios desde los que la SPA usa cookies |
| `QUEUE_CONNECTION=database` | La cola se vacía cada minuto desde el scheduler |
| `MAIL_*` | Brevo |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Avisos al móvil. La privada, **solo** en Coolify y en KeePass. Si se pierde, hay que generar otras (`push:claves`) y todos tendrán que volver a activar los avisos |
| Clave de football-data.org | API de resultados |

## Base de datos y HeidiSQL

- MySQL gestionado por Coolify. Su puerto se publica **solo** en `127.0.0.1:3306:3306`.
  Nunca sin el `127.0.0.1`: quedaría abierto a internet.
- HeidiSQL entra por **túnel SSH usando `plink.exe`** de PuTTY, no `ssh.exe`.
- Para trabajar en local con datos reales: Admin → Sistema → **Copia para local**
  (anonimizada) y cargarla con HeidiSQL.

## Copias de seguridad

- Copias de MySQL programadas en **Coolify**.
- Copia del servidor completo de **Contabo**.
- Copias manuales cuando hay cambios gordos.

Una copia que nunca se ha restaurado es una copia que no sabes si funciona: de vez en
cuando, carga una en local.

## Seguridad

Lo que hay puesto y por qué:

| Medida | Dónde | Para qué |
|---|---|---|
| PHP ≥ 8.5.12 obligatorio al construir | `Dockerfile` | CVE-2026-109618/109619 (ejecución remota de código en PHP) |
| `session.upload_progress.enabled = 0`, `session.use_strict_mode = 1`, `expose_php = Off` | `docker/php-seguridad.ini` | Medida recomendada para esos fallos (cubre también **CVE-2026-115560**, sin parche aún): **no quitar** aunque PHP esté al día; no anunciar la versión |
| Envíos con archivos (multipart) **solo** con cabecera `X-XSRF-TOKEN` (403 si no) | `docker/nginx.conf` | Frena ataques automáticos; la app siempre la manda |
| nginx **solo ejecuta `index.php`**; cualquier otro `.php`, 404 | `docker/nginx.conf` | Que nadie pueda ejecutar un fichero colado en `storage` |
| `APP_DEBUG=false` | Coolify | No enseñar errores internos |
| Login limitado a 5 intentos/min | `routes/api.php` | Fuerza bruta |
| Sesión de 2 h; recuérdame 30 días solo con la casilla | Laravel | Equilibrio comodidad/seguridad |
| Repo privado, `.env` fuera del repo, clave VAPID solo en Coolify/KeePass | — | Secretos |
| Copia para local anonimizada (correos falsos, contraseña `password`) | Admin | No sacar datos reales del servidor |
| Auditoría semanal de dependencias (lunes 05:00 UTC) | GitHub Actions | Avisa de fallos conocidos en composer/npm |

**Sin scripts que entren por SSH a producción** desde el PC: para traer datos se usa la
descarga del admin.

## Actualizar PHP

PHP viene del repositorio de Ondřej (vía espejo). Cuando sale una versión de seguridad:

1. **¿Ya está publicada?** Con el script `~/comprobar-php.sh`:

   ```bash
   cat > ~/comprobar-php.sh <<'EOF'
   #!/bin/bash
   # Qué PHP 8.5 instalaría ahora la imagen de producción (Ubuntu 24.04 + repositorio de Ondřej)
   docker run --rm ubuntu:24.04 bash -c '
     apt-get update -qq >/dev/null 2>&1; apt-get install -y -qq curl gnupg ca-certificates >/dev/null 2>&1
     if ! curl -sSf "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6" -o /tmp/k 2>/dev/null; then
       echo "⚠️ El servidor de claves no responde. Prueba en un rato."; exit 0; fi
     gpg --dearmor < /tmp/k > /etc/apt/keyrings/o.gpg
     echo "deb [signed-by=/etc/apt/keyrings/o.gpg] https://ppa.setup-php.com/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/o.list
     apt-get update -qq >/dev/null 2>&1
     V=$(apt-cache policy php8.5-fpm | awk "/Candidate/{print \$2}")
     [ -z "$V" ] || [ "$V" = "(none)" ] && { echo "⚠️ No se ha podido leer el repositorio. Prueba en un rato."; exit 0; }
     echo "Disponible: $V"'
   EOF
   chmod +x ~/comprobar-php.sh && ~/comprobar-php.sh
   ```

2. **Sube el mínimo** en la línea de control del `Dockerfile`
   (`version_compare(PHP_VERSION, "8.5.12", "<")`) y **cambia la fecha** de
   `ARG PHP_ACTUALIZADO`.
3. **Construye en local** y comprueba la versión:

   ```bash
   docker build -t pronosticup-prueba-php . > /tmp/build-php.log 2>&1 \
   && docker run --rm --entrypoint sh pronosticup-prueba-php -c 'php -v | head -1; php-fpm8.5 -v | head -1; nginx -t'
   ```

4. Sube, **Deploy** en Coolify y comprueba en el servidor (ver
   [Chuletas](chuletas.md#servidor-de-producción)).

Si da **503** al descargar, el repositorio está saturado: reintentar en un rato.

## Ante un aviso de seguridad

Lo aprendido con el fallo de PHP de octubre de 2026:

1. **¿Me afecta?** Mira qué hay expuesto a internet: nginx + PHP en el contenedor de la app,
   y el panel de Coolify. Node solo se usa para **compilar** el frontend: un fallo de Node
   casi nunca te afecta en producción.
2. **Medidas provisionales** si el parche no está aún: las del aviso oficial. Súbelas solas,
   sin reinstalar nada, para que Coolify reutilice la caché.
3. **Parche** en cuanto esté publicado, siguiendo [Actualizar PHP](#actualizar-php).
4. **Comprobar** que no te han colado nada:
   `find storage/app public -name "*.php"` (solo debe salir `public/index.php`) y revisar
   los logs.
5. **Actualizar Coolify**: el panel también es una app PHP.
6. Apuntarlo aquí si se aprende algo nuevo.
