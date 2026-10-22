# Frontend de PronostiCup Liga

SPA en React 19 + Vite 8 + TanStack Query 5 + Tailwind 4 + React Router 7.

Toda la documentación del proyecto está en la raíz: [README](../README.md) y la carpeta
[`docs/`](../docs). Lo específico del frontend está en
[Arquitectura → Frontend](../docs/01-arquitectura.md#frontend) y
[Desarrollo](../docs/05-desarrollo.md).

```bash
cd ~/proyectos/pronosticup-liga/frontend
docker compose up -d                                      # servidor de desarrollo (http://localhost:5173)
docker compose exec -T node npx vite build --outDir /tmp/build-prueba --emptyOutDir   # comprobar que compila
```
