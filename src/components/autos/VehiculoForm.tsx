import { useEffect, useRef, useState, type FormEvent } from 'react'
import { api, AuthError } from '../../api'
import {
  CAJAS,
  COMBUSTIBLES,
  ESTADOS_VEHICULO,
  TIPOS_VEHICULO,
  type EstadoVehiculo,
  type Foto,
  type Moneda,
  type TipoVehiculo,
  type Vehiculo,
} from '../../autosTypes'
import { btnGhost, btnPrimary, field, input } from '../shared'

const ANIO_MAX = new Date().getFullYear() + 1
const MAX_FOTO = 10 * 1024 * 1024

type Item =
  | { key: string; tipo: 'foto'; foto: Foto }
  | { key: string; tipo: 'nueva'; file: File; url: string; progreso: number | null; error: string }

function hoy() {
  const d = new Date()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const dia = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${m}-${dia}`
}

type RespuestaFoto = { ok?: boolean; error?: string; data?: { fotos?: Foto[]; errores?: string[] } }

function subirFoto(vehiculoId: number, file: File, onProgreso: (pct: number) => void): Promise<Foto> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', '/api/autos/fotos.php')
    xhr.withCredentials = true
    xhr.upload.onprogress = (ev) => {
      if (ev.lengthComputable) onProgreso(Math.round((ev.loaded / ev.total) * 100))
    }
    xhr.onerror = () => reject(new Error('No se pudo subir la foto'))
    xhr.onload = () => {
      let data: RespuestaFoto | null = null
      try {
        data = JSON.parse(xhr.responseText) as RespuestaFoto
      } catch {
        data = null
      }
      if (xhr.status === 401) {
        reject(new AuthError(data?.error || 'No autenticado'))
        return
      }
      const foto = data?.data?.fotos?.[0]
      if (xhr.status >= 400 || data?.ok === false || data?.error || !foto) {
        reject(new Error(data?.error || data?.data?.errores?.[0] || 'No se pudo subir la foto'))
        return
      }
      onProgreso(100)
      resolve(foto)
    }
    const fd = new FormData()
    fd.append('action', 'subir')
    fd.append('vehiculo_id', String(vehiculoId))
    fd.append('fotos[]', file)
    xhr.send(fd)
  })
}

function Segmentos({ opciones, value, onChange }: { opciones: string[]; value: string; onChange: (v: string) => void }) {
  return (
    <div className="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
      {opciones.map((op) => (
        <button
          key={op}
          type="button"
          onClick={() => onChange(op)}
          className={`min-h-11 flex-1 rounded-lg px-3 py-2 text-sm font-medium ${value === op ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'}`}
        >
          {op}
        </button>
      ))}
    </div>
  )
}

interface Props {
  cuentaId: number
  vehiculo: Vehiculo | null
  onCerrar: () => void
  onActualizar: (vehiculo: Vehiculo) => void
  onAuth: () => void
}

export default function VehiculoForm({ cuentaId, vehiculo, onCerrar, onActualizar, onAuth }: Props) {
  const [creado, setCreado] = useState<Vehiculo | null>(vehiculo)
  const [marca, setMarca] = useState(vehiculo?.marca ?? '')
  const [modelo, setModelo] = useState(vehiculo?.modelo ?? '')
  const [version, setVersion] = useState(vehiculo?.version ?? '')
  const [anio, setAnio] = useState(vehiculo ? String(vehiculo.anio) : '')
  const [km, setKm] = useState(vehiculo ? String(vehiculo.km) : '')
  const [precio, setPrecio] = useState(vehiculo ? String(vehiculo.precio) : '')
  const [moneda, setMoneda] = useState<Moneda>(vehiculo?.moneda ?? 'USD')
  const [tipo, setTipo] = useState<TipoVehiculo>(vehiculo?.tipo ?? 'otro')
  const [combustible, setCombustible] = useState(vehiculo?.combustible ?? '')
  const [caja, setCaja] = useState(vehiculo?.caja ?? '')
  const [color, setColor] = useState(vehiculo?.color ?? '')
  const [descripcion, setDescripcion] = useState(vehiculo?.descripcion ?? '')
  const [destacado, setDestacado] = useState<0 | 1>(vehiculo?.destacado ?? 0)
  const [ingreso, setIngreso] = useState(vehiculo?.ingreso_at || hoy())
  const [estado, setEstado] = useState<EstadoVehiculo>(vehiculo?.estado ?? 'disponible')
  const [fotos, setFotos] = useState<Item[]>(() => (vehiculo?.fotos ?? []).map((foto) => ({ key: `foto-${foto.id}`, tipo: 'foto' as const, foto })))
  const [error, setError] = useState('')
  const [guardando, setGuardando] = useState(false)
  const archivos = useRef<HTMLInputElement>(null)
  const urls = useRef<string[]>([])
  const lock = useRef(false)

  useEffect(() => () => {
    urls.current.forEach((url) => URL.revokeObjectURL(url))
  }, [])

  const combustibles = combustible && !COMBUSTIBLES.includes(combustible as (typeof COMBUSTIBLES)[number])
    ? [...COMBUSTIBLES, combustible]
    : [...COMBUSTIBLES]
  const cajas = caja && !CAJAS.includes(caja as (typeof CAJAS)[number]) ? [...CAJAS, caja] : [...CAJAS]

  function agregarFotos(lista: FileList | null) {
    if (!lista) return
    const nuevas: Item[] = []
    for (const file of lista) {
      const url = URL.createObjectURL(file)
      urls.current.push(url)
      nuevas.push({
        key: crypto.randomUUID(),
        tipo: 'nueva',
        file,
        url,
        progreso: null,
        error: file.size > MAX_FOTO ? 'Supera los 10 MB' : '',
      })
    }
    setFotos((prev) => [...prev, ...nuevas])
  }

  function quitarUrl(url: string) {
    URL.revokeObjectURL(url)
    urls.current = urls.current.filter((item) => item !== url)
  }

  async function mover(index: number, dir: -1 | 1) {
    const j = index + dir
    if (j < 0 || j >= fotos.length || lock.current) return
    const next = fotos.slice()
    const [item] = next.splice(index, 1)
    if (!item) return
    next.splice(j, 0, item)
    const antes = fotos.filter((f) => f.tipo === 'foto').map((f) => f.foto.id).join()
    const despues = next.filter((f) => f.tipo === 'foto').map((f) => f.foto.id).join()
    setFotos(next)
    if (!creado || antes === despues) return
    const ids = next.filter((f) => f.tipo === 'foto').map((f) => f.foto.id)
    if (ids.length === 0) return
    lock.current = true
    setGuardando(true)
    setError('')
    try {
      await api('autos/fotos.php', { action: 'ordenar', vehiculo_id: creado.id, ids })
      const ordenadas = next.filter((f) => f.tipo === 'foto').map((f) => f.foto)
      onActualizar({ ...creado, fotos: ordenadas })
    } catch (err) {
      setFotos(fotos)
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo ordenar')
    } finally {
      lock.current = false
      setGuardando(false)
    }
  }

  async function borrar(item: Item) {
    if (item.tipo === 'nueva') {
      quitarUrl(item.url)
      setFotos((prev) => prev.filter((f) => f.key !== item.key))
      return
    }
    if (!creado || lock.current || !confirm('¿Sacar esta foto?')) return
    lock.current = true
    setGuardando(true)
    setError('')
    try {
      await api('autos/fotos.php', { action: 'borrar', id: item.foto.id })
      const siguientes = fotos.filter((f) => f.key !== item.key)
      setFotos(siguientes)
      onActualizar({ ...creado, fotos: siguientes.filter((f) => f.tipo === 'foto').map((f) => f.foto) })
    } catch (err) {
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo borrar la foto')
    } finally {
      lock.current = false
      setGuardando(false)
    }
  }

  function validar(): { anio: number; km: number; precio: number } | null {
    if (!marca.trim() || !modelo.trim()) {
      setError('La marca y el modelo son obligatorios')
      return null
    }
    if (!/^\d+$/.test(anio) || Number(anio) < 1980 || Number(anio) > ANIO_MAX) {
      setError(`El año tiene que estar entre 1980 y ${ANIO_MAX}`)
      return null
    }
    if (!/^\d+$/.test(km) || Number(km) > 1_000_000) {
      setError('Los kilómetros tienen que estar entre 0 y 1.000.000')
      return null
    }
    if (!/^\d+$/.test(precio) || Number(precio) < 1) {
      setError('El precio tiene que ser mayor a 0')
      return null
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(ingreso)) {
      setError('Fecha de ingreso inválida')
      return null
    }
    return { anio: Number(anio), km: Number(km), precio: Number(precio) }
  }

  async function guardar(e: FormEvent) {
    e.preventDefault()
    if (lock.current) return
    const nums = validar()
    if (!nums) return
    lock.current = true
    setGuardando(true)
    setError('')
    try {
      const cuerpo = {
        marca: marca.trim(),
        modelo: modelo.trim(),
        version: version.trim(),
        anio: nums.anio,
        km: nums.km,
        precio: nums.precio,
        moneda,
        combustible,
        caja,
        tipo,
        color: color.trim(),
        descripcion: descripcion.trim(),
        destacado,
        ingreso_at: ingreso,
      }
      let actual = creado
      if (!actual) {
        const res = await api<{ data: Vehiculo }>('autos/vehiculos.php', {
          action: 'crear',
          cuenta_id: cuentaId,
          ...cuerpo,
          estado,
        })
        actual = res.data
        setCreado(actual)
        onActualizar(actual)
      } else {
        const res = await api<{ data: Vehiculo }>('autos/vehiculos.php', {
          action: 'editar',
          id: actual.id,
          ...cuerpo,
        })
        actual = { ...res.data, fotos: fotos.filter((f) => f.tipo === 'foto').map((f) => f.foto) }
        setCreado(actual)
        onActualizar(actual)
      }

      let lista = fotos
      let falloFoto = false
      for (const item of fotos) {
        if (item.tipo !== 'nueva' || item.error) {
          if (item.tipo === 'nueva' && item.error) falloFoto = true
          continue
        }
        setFotos((prev) => prev.map((f) => (f.key === item.key && f.tipo === 'nueva' ? { ...f, progreso: 0, error: '' } : f)))
        try {
          const foto = await subirFoto(actual.id, item.file, (pct) => {
            setFotos((prev) => prev.map((f) => (f.key === item.key && f.tipo === 'nueva' ? { ...f, progreso: pct } : f)))
          })
          quitarUrl(item.url)
          lista = lista.map((f) => (f.key === item.key ? { key: f.key, tipo: 'foto' as const, foto } : f))
          setFotos(lista)
        } catch (err) {
          if (err instanceof AuthError) throw err
          falloFoto = true
          const mensaje = err instanceof Error ? err.message : 'No se pudo subir la foto'
          lista = lista.map((f) => (f.key === item.key && f.tipo === 'nueva' ? { ...f, progreso: null, error: mensaje } : f))
          setFotos(lista)
        }
      }

      const ids = lista.filter((f) => f.tipo === 'foto').map((f) => f.foto.id)
      if (ids.length > 0) {
        const res = await api<{ data: Foto[] }>('autos/fotos.php', { action: 'ordenar', vehiculo_id: actual.id, ids })
        const porId = new Map(res.data.map((f) => [f.id, f]))
        lista = lista.map((f) => (f.tipo === 'foto' ? { ...f, foto: porId.get(f.foto.id) ?? f.foto } : f))
        setFotos(lista)
      }
      const guardado = { ...actual, fotos: lista.filter((f) => f.tipo === 'foto').map((f) => f.foto) }
      setCreado(guardado)
      onActualizar(guardado)
      if (falloFoto) setError('El auto quedó guardado. Revisá las fotos que no se subieron.')
      else onCerrar()
    } catch (err) {
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo guardar')
    } finally {
      lock.current = false
      setGuardando(false)
    }
  }

  const titulo = creado ? `Editar ${creado.ref}` : 'Cargar auto'

  return (
    <form onSubmit={guardar} autoComplete="off" className="space-y-4 pb-24">
      <div>
        <button type="button" onClick={onCerrar} className="text-sm font-medium text-indigo-600">
          ← Volver al stock
        </button>
        <h2 className="mt-1 text-2xl font-bold text-slate-900">{titulo}</h2>
      </div>

      {error && <div className="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700 ring-1 ring-rose-200">{error}</div>}

      <div className="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Marca</span>
          <input className={input} value={marca} onChange={(e) => setMarca(e.target.value)} maxLength={80} required />
        </label>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Modelo</span>
          <input className={input} value={modelo} onChange={(e) => setModelo(e.target.value)} maxLength={80} required />
        </label>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Versión</span>
          <input className={input} value={version} onChange={(e) => setVersion(e.target.value)} maxLength={80} />
        </label>
        <div className="grid grid-cols-2 gap-3">
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Año</span>
            <input className={input} value={anio} onChange={(e) => setAnio(e.target.value)} inputMode="numeric" required />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Km</span>
            <input className={input} value={km} onChange={(e) => setKm(e.target.value)} inputMode="numeric" required />
          </label>
        </div>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Precio</span>
          <input className={input} value={precio} onChange={(e) => setPrecio(e.target.value)} inputMode="numeric" required />
        </label>
        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-700">Moneda</span>
          <Segmentos opciones={['USD', 'UYU']} value={moneda} onChange={(v) => setMoneda(v as Moneda)} />
        </div>
        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-700">Tipo</span>
          <div className="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
            {TIPOS_VEHICULO.map((op) => (
              <button
                key={op.id}
                type="button"
                onClick={() => setTipo(op.id)}
                className={`min-h-11 flex-1 rounded-lg px-3 py-2 text-sm font-medium ${tipo === op.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'}`}
              >
                {op.label}
              </button>
            ))}
          </div>
        </div>
        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-700">Combustible</span>
          <Segmentos opciones={combustibles} value={combustible} onChange={setCombustible} />
        </div>
        <div className="space-y-1">
          <span className="text-sm font-medium text-slate-700">Caja</span>
          <Segmentos opciones={cajas} value={caja} onChange={setCaja} />
        </div>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Color</span>
          <input className={input} value={color} onChange={(e) => setColor(e.target.value)} maxLength={30} placeholder="Blanco" />
        </label>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Descripción</span>
          <textarea className={`${input} min-h-24`} value={descripcion} onChange={(e) => setDescripcion(e.target.value)} maxLength={5000} />
        </label>
        <label className="flex min-h-11 items-center gap-3 text-sm font-medium text-slate-700">
          <input type="checkbox" className="h-5 w-5" checked={destacado === 1} onChange={(e) => setDestacado(e.target.checked ? 1 : 0)} />
          Destacado
        </label>
        <label className="block space-y-1">
          <span className="text-sm font-medium text-slate-700">Ingreso</span>
          <input type="date" className={input} value={ingreso} onChange={(e) => setIngreso(e.target.value)} required />
        </label>
        {!creado && (
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Estado</span>
            <select className={`${field} w-full`} value={estado} onChange={(e) => setEstado(e.target.value as EstadoVehiculo)}>
              {ESTADOS_VEHICULO.map((op) => (
                <option key={op.id} value={op.id}>{op.label}</option>
              ))}
            </select>
          </label>
        )}
      </div>

      <section className="space-y-3">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-semibold text-slate-900">Fotos</h3>
          <button type="button" className={btnGhost} onClick={() => archivos.current?.click()} disabled={guardando}>
            Agregar fotos
          </button>
        </div>
        <p className="text-sm text-slate-500">La primera foto es la portada. En el celular podés usar la cámara o la galería.</p>
        <input
          ref={archivos}
          type="file"
          accept="image/*"
          multiple
          className="sr-only"
          onChange={(e) => {
            agregarFotos(e.target.files)
            e.target.value = ''
          }}
        />
        {fotos.length === 0 ? (
          <p className="rounded-2xl bg-white p-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">Sin fotos todavía.</p>
        ) : (
          <ul className="space-y-3">
            {fotos.map((item, index) => {
              const src = item.tipo === 'foto' ? item.foto.url_800 : item.url
              return (
                <li key={item.key} className="flex gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                  <img src={src} alt="" className="h-20 w-28 shrink-0 rounded-lg object-cover bg-slate-100" />
                  <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-sm font-medium text-slate-700">{index === 0 ? 'Portada' : `Foto ${index + 1}`}</span>
                      <button type="button" onClick={() => void borrar(item)} disabled={guardando} className="text-sm text-rose-600">
                        Borrar
                      </button>
                    </div>
                    {item.tipo === 'nueva' && item.progreso != null && (
                      <div className="h-2 overflow-hidden rounded-full bg-slate-200">
                        <div className="h-full bg-indigo-600" style={{ width: `${item.progreso}%` }} />
                      </div>
                    )}
                    {item.tipo === 'nueva' && item.error && <p className="text-xs text-rose-600">{item.error}</p>}
                    <div className="flex gap-2">
                      <button type="button" aria-label="Mover antes" disabled={index === 0 || guardando} onClick={() => void mover(index, -1)} className="h-11 w-11 rounded-lg border border-slate-300 text-lg disabled:opacity-40">
                        ←
                      </button>
                      <button type="button" aria-label="Mover después" disabled={index === fotos.length - 1 || guardando} onClick={() => void mover(index, 1)} className="h-11 w-11 rounded-lg border border-slate-300 text-lg disabled:opacity-40">
                        →
                      </button>
                    </div>
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </section>

      <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 p-3 backdrop-blur">
        <div className="mx-auto flex max-w-7xl gap-2">
          <button type="button" className={btnGhost} onClick={onCerrar} disabled={guardando}>
            Cancelar
          </button>
          <button className={`${btnPrimary} flex-1`} disabled={guardando}>
            {guardando ? 'Guardando…' : 'Guardar'}
          </button>
        </div>
      </div>
    </form>
  )
}
