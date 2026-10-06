// Descarga los negocios de Uruguay desde Overture Maps (datos abiertos, gratis) a data/uy_places.jsonl
// Después correr: php scripts/importar_overture.php  (o todo junto: npm run overture)
import { DuckDBInstance } from '@duckdb/node-api'
import fs from 'node:fs'

const BUCKET = 'https://overturemaps-us-west-2.s3.us-west-2.amazonaws.com'

// Raíces de categoría que no son negocios (playas, plazas, gobierno, monumentos…)
const ROOTS_EXCLUIDAS = ['cultural_and_historic', 'community_and_government', 'geographic_entities']
const CATS_EXCLUIDAS = [
  'beach', 'park', 'public_plaza', 'river', 'lake', 'bridge', 'public_fountain', 'monument', 'playground',
  'atm', 'bus_station', 'airport', 'parking', 'public_school', 'police_station', 'embassy', 'military_site',
  'soccer_field', 'stadium_arena', 'campus_building', 'shopping_mall', 'bank', 'bank_or_credit_union',
]

async function ultimaRelease() {
  const xml = await (await fetch(`${BUCKET}/?list-type=2&prefix=release/&delimiter=/`)).text()
  const releases = [...xml.matchAll(/<Prefix>release\/([^<]+)\/<\/Prefix>/g)].map((m) => m[1]).sort()
  if (!releases.length) throw new Error('No se encontraron releases de Overture')
  return releases.at(-1)
}

const release = process.argv[2] || (await ultimaRelease())
console.log(`Release de Overture: ${release}`)

fs.mkdirSync('data', { recursive: true })
const db = await DuckDBInstance.create(':memory:')
const c = await db.connect()
await c.run("INSTALL httpfs; LOAD httpfs; SET s3_region='us-west-2';")

const t = Date.now()
const lista = (arr) => arr.map((s) => `'${s}'`).join(',')
await c.run(`COPY (
  SELECT
    id AS place_id,
    names.primary AS nombre,
    coalesce(taxonomy.primary, '') AS categoria,
    coalesce(array_to_string(taxonomy.hierarchy, ' '), '') AS jerarquia,
    coalesce(addresses[1].freeform, '') AS direccion,
    coalesce(addresses[1].locality, '') AS localidad,
    coalesce(phones, []) AS telefonos,
    coalesce(websites, []) AS webs,
    coalesce(socials, []) AS redes,
    coalesce(emails, []) AS emails,
    round(confidence, 2) AS confianza,
    round((bbox.ymin + bbox.ymax) / 2, 6) AS lat,
    round((bbox.xmin + bbox.xmax) / 2, 6) AS lon
  FROM read_parquet('s3://overturemaps-us-west-2/release/${release}/theme=places/type=place/*', hive_partitioning=1)
  WHERE bbox.xmin BETWEEN -58.6 AND -53.0 AND bbox.ymin BETWEEN -35.2 AND -30.0
    AND addresses[1].country = 'UY'
    AND names.primary IS NOT NULL
    AND coalesce(operating_status, 'open') = 'open'
    AND coalesce(taxonomy.hierarchy[1], '') NOT IN (${lista(ROOTS_EXCLUIDAS)})
    AND coalesce(taxonomy.primary, '') NOT IN (${lista(CATS_EXCLUIDAS)})
    AND coalesce(taxonomy.primary, '') NOT LIKE '%place_of_worship%'
) TO 'data/uy_places.jsonl' (FORMAT json)`)

const n = fs.readFileSync('data/uy_places.jsonl', 'utf8').split('\n').filter(Boolean).length
console.log(`${n} negocios guardados en data/uy_places.jsonl (${((Date.now() - t) / 1000).toFixed(0)}s)`)
