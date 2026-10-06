# Automotoras (AgusDevPro)

Catálogo de autos usados para automotoras: panel con login para cargar el stock y un catálogo público por automotora.

- **Panel:** React 19 + TypeScript + Tailwind 4 (Vite) en `src/` → https://agusdevpro.com/autos-admin/ (mismo login que agusdevpro)
- **API del panel:** PHP + MySQL en `api/autos/` (con login)
- **Catálogo público:** `autos/index.php` + `autos/catalogo.js` → https://agusdevpro.com/autos/<slug>/ y `/autos/<slug>/<ref>` para cada auto
- **API pública:** `api/autos/publico/` (sin login): datos del catálogo y registro de visitas y clics
- **Fotos:** `autos-media/` en la raíz del sitio, fuera del repo (el deploy nunca la toca)

## Estructura

```
SERVIDOR/
├── agusdevpro/          ← sitio que se sube al hosting
│   ├── api/autos/       ← copia del backend (la genera npm run build / sync)
│   ├── autos/           ← catálogo público
│   └── autos-admin/     ← build del panel
└── automotorademo/      ← este repo
```

`api/leads/` y los componentes de LeadFinder quedan en el repo como referencia, pero **no se publican desde acá**: LeadFinder se deploya desde su propio repo.

## Desarrollo

```bash
npm install
npm run api   # copia el backend y levanta PHP en :8000 sirviendo ../agusdevpro
npm run dev   # http://localhost:5175 (panel); catálogo en http://localhost:8000/autos/?c=<slug>
```

## Deploy

Cada push a `main` compila y sube por SFTP `autos-admin/`, `api/autos/` y `autos/` (`.github/workflows/deploy.yml`).
Necesita los secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` y `FTP_ROOT` en este repo (los mismos que leadfinder).
