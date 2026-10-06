// Copia api/autos y el catálogo autos/ a ../agusdevpro, donde viven junto al resto del sitio.
// Lo corren solos `npm run api` y `npm run build`.
// api/leads NO se copia: lo publica el repo leadfinder y acá quedaría una versión vieja pisándolo.
import fs from 'node:fs'

const copias = [
  ['api/autos', '../agusdevpro/api/autos'],
  ['autos', '../agusdevpro/autos'],
]

for (const [origen, destino] of copias) {
  fs.mkdirSync(destino, { recursive: true })
  fs.cpSync(origen, destino, {
    recursive: true,
    filter: (src) => !src.endsWith('_demo_referencia.html'),
  })
  console.log(`Copiado ${origen} → ${destino}`)
}
