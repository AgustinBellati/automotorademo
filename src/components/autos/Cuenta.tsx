import { useEffect, useState, type FormEvent } from 'react'
import { api, AuthError } from '../../api'
import {
  ESTADOS_CUENTA,
  ESTADOS_VEHICULO,
  urlCatalogo,
  type Cuenta,
  type EstadoCuenta,
  type EstadoVehiculo,
  type Vehiculo,
} from '../../autosTypes'
import { btnGhost, btnPrimary, field, input } from '../shared'
import VehiculoForm from './VehiculoForm'

const ESTADO_AUTO: Record<EstadoVehiculo, string> = {
  disponible: 'bg-emerald-100 text-emerald-800',
  reservado: 'bg-amber-100 text-amber-800',
  vendido: 'bg-slate-200 text-slate-700',
}

type Filtro = 'stock' | EstadoVehiculo | 'todos'

const FILTROS: { id: Filtro; label: string }[] = [
  { id: 'stock', label: 'Disponibles y reservados' },
  { id: 'disponible', label: 'Disponibles' },
  { id: 'reservado', label: 'Reservados' },
  { id: 'vendido', label: 'Vendidos' },
  { id: 'todos', label: 'Todos' },
]

function plata(precio: number, moneda: Vehiculo['moneda']) {
  const n = new Intl.NumberFormat('es-UY').format(precio)
  return moneda === 'USD' ? `US$ ${n}` : `$ ${n}`
}

function dias(n: number) {
  if (n === 0) return 'Ingresó hoy'
  if (n === 1) return '1 día en stock'
  return `${n} días en stock`
}

interface Props {
  cuenta: Cuenta
  onVolver: () => void
  onActualizada: (cuenta: Cuenta) => void
  onAuth: () => void
}

export default function CuentaVista({ cuenta, onVolver, onActualizada, onAuth }: Props) {
  const [vehiculos, setVehiculos] = useState<Vehiculo[]>([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [filtro, setFiltro] = useState<Filtro>('stock')
  const [config, setConfig] = useState(false)
  const [guardandoConfig, setGuardandoConfig] = useState(false)
  const [form, setForm] = useState<Vehiculo | null | undefined>(undefined)
  const [guardandoEstado, setGuardandoEstado] = useState<number | null>(null)

  const [direccion, setDireccion] = useState(cuenta.direccion)
  const [whatsapp, setWhatsapp] = useState(cuenta.whatsapp)
  const [color, setColor] = useState(/^#[0-9a-fA-F]{6}$/.test(cuenta.color) ? cuenta.color : '#1d4ed8')
  const [tasa, setTasa] = useState(String(cuenta.tasa_anual))
  const [plazos, setPlazos] = useState(cuenta.plazos.join(', '))
  const [entrega, setEntrega] = useState(String(cuenta.entrega_min_pct))
  const [estado, setEstado] = useState<EstadoCuenta>(cuenta.estado)

  useEffect(() => {
    let vivo = true
    setCargando(true)
    api<{ data: Vehiculo[] }>(`autos/vehiculos.php?cuenta_id=${cuenta.id}`)
      .then((res) => {
        if (vivo) setVehiculos(res.data)
      })
      .catch((err: unknown) => {
        if (!vivo) return
        if (err instanceof AuthError) onAuth()
        else setError(err instanceof Error ? err.message : 'No se pudo cargar el stock')
      })
      .finally(() => {
        if (vivo) setCargando(false)
      })
    return () => {
      vivo = false
    }
  }, [cuenta.id, onAuth])

  const visibles = vehiculos.filter((v) => {
    if (filtro === 'todos') return true
    if (filtro === 'stock') return v.estado === 'disponible' || v.estado === 'reservado'
    return v.estado === filtro
  })

  function aplicar(vehiculo: Vehiculo) {
    setVehiculos((prev) => {
      const existe = prev.some((v) => v.id === vehiculo.id)
      if (!existe) return [vehiculo, ...prev]
      return prev.map((v) => (v.id === vehiculo.id ? vehiculo : v))
    })
  }

  async function cambiarEstado(vehiculo: Vehiculo, nuevo: EstadoVehiculo) {
    if (nuevo === vehiculo.estado) return
    const previo = vehiculos
    setVehiculos((prev) => prev.map((v) => (v.id === vehiculo.id ? { ...v, estado: nuevo } : v)))
    setGuardandoEstado(vehiculo.id)
    setError('')
    try {
      const res = await api<{ data: Vehiculo }>('autos/vehiculos.php', {
        action: 'estado',
        id: vehiculo.id,
        estado: nuevo,
      })
      aplicar(res.data)
    } catch (err) {
      setVehiculos(previo)
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo cambiar el estado')
    } finally {
      setGuardandoEstado(null)
    }
  }

  async function guardarConfig(e: FormEvent) {
    e.preventDefault()
    const tasaNum = Number(tasa.replace(',', '.'))
    if (!Number.isFinite(tasaNum) || tasaNum < 0 || tasaNum > 60) {
      setError('La tasa anual tiene que estar entre 0 y 60')
      return
    }
    const entregaNum = Number(entrega)
    if (!/^\d+$/.test(entrega.trim()) || entregaNum < 0 || entregaNum > 90) {
      setError('La entrega mínima tiene que estar entre 0 y 90')
      return
    }
    const listaPlazos = plazos.split(',').map((p) => p.trim()).filter(Boolean)
    const nums: number[] = []
    for (const parte of listaPlazos) {
      if (!/^\d+$/.test(parte)) {
        setError('Cada plazo tiene que estar entre 6 y 84 meses')
        return
      }
      const n = Number(parte)
      if (n < 6 || n > 84) {
        setError('Cada plazo tiene que estar entre 6 y 84 meses')
        return
      }
      if (!nums.includes(n)) nums.push(n)
    }
    if (nums.length === 0) {
      setError('Indicá al menos un plazo')
      return
    }
    if (!/^#[0-9a-fA-F]{6}$/.test(color)) {
      setError('El color tiene que ser #rrggbb')
      return
    }
    setGuardandoConfig(true)
    setError('')
    try {
      const res = await api<{ data: Cuenta }>('autos/cuentas.php', {
        action: 'editar',
        id: cuenta.id,
        direccion: direccion.trim(),
        whatsapp: whatsapp.trim(),
        color,
        tasa_anual: tasaNum,
        plazos: nums,
        entrega_min_pct: entregaNum,
        estado,
      })
      onActualizada(res.data)
      setConfig(false)
    } catch (err) {
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo guardar')
    } finally {
      setGuardandoConfig(false)
    }
  }

  if (form !== undefined) {
    return (
      <VehiculoForm
        cuentaId={cuenta.id}
        vehiculo={form}
        onCerrar={() => setForm(undefined)}
        onActualizar={aplicar}
        onAuth={onAuth}
      />
    )
  }

  const catalogo = urlCatalogo(cuenta.slug)

  return (
    <div className="space-y-4 pb-24">
      <div>
        <button type="button" onClick={onVolver} className="text-sm font-medium text-indigo-600">
          ← Automotoras
        </button>
        <h2 className="mt-1 text-2xl font-bold text-slate-900">{cuenta.nombre}</h2>
        <a href={catalogo} target="_blank" rel="noreferrer" className="mt-1 block break-all text-sm text-indigo-600">
          {catalogo.replace(/^https?:\/\//, '')}
        </a>
      </div>

      <button type="button" className={`${btnGhost} w-full`} onClick={() => setConfig((v) => !v)}>
        {config ? 'Cerrar configuración' : 'Configurar'}
      </button>

      {error && <div className="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700 ring-1 ring-rose-200">{error}</div>}

      {config && (
        <form onSubmit={guardarConfig} className="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Dirección</span>
            <input className={input} value={direccion} onChange={(e) => setDireccion(e.target.value)} maxLength={200} />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">WhatsApp</span>
            <input className={input} value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} inputMode="tel" />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Color</span>
            <input type="color" value={color} onChange={(e) => setColor(e.target.value)} className="h-11 w-16 cursor-pointer rounded-lg border border-slate-300 bg-white p-1" />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Tasa anual (%)</span>
            <input className={input} value={tasa} onChange={(e) => setTasa(e.target.value)} inputMode="decimal" />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Plazos (meses, separados por coma)</span>
            <input className={input} value={plazos} onChange={(e) => setPlazos(e.target.value)} inputMode="numeric" placeholder="12, 24, 36, 48" />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Entrega mínima (%)</span>
            <input className={input} value={entrega} onChange={(e) => setEntrega(e.target.value)} inputMode="numeric" />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Estado</span>
            <select className={`${field} w-full`} value={estado} onChange={(e) => setEstado(e.target.value as EstadoCuenta)}>
              {ESTADOS_CUENTA.map((op) => (
                <option key={op.id} value={op.id}>{op.label}</option>
              ))}
            </select>
          </label>
          <button className={`${btnPrimary} w-full`} disabled={guardandoConfig}>
            {guardandoConfig ? 'Guardando…' : 'Guardar'}
          </button>
        </form>
      )}

      <div className="flex gap-2 overflow-x-auto pb-1">
        {FILTROS.map((op) => (
          <button
            key={op.id}
            type="button"
            onClick={() => setFiltro(op.id)}
            className={`shrink-0 rounded-full px-3 py-2 text-sm font-medium ${filtro === op.id ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'}`}
          >
            {op.label}
          </button>
        ))}
      </div>

      {cargando ? (
        <p className="text-sm text-slate-500">Cargando…</p>
      ) : visibles.length === 0 ? (
        <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
          {vehiculos.length === 0 ? 'Todavía no hay autos cargados.' : 'No hay autos con ese filtro.'}
        </p>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {visibles.map((v) => {
            const portada = v.fotos[0]
            return (
              <li key={v.id} className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div className="aspect-[16/10] bg-slate-100">
                  {portada ? (
                    <img src={portada.url_800} alt={`${v.marca} ${v.modelo}`} className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex h-full items-center justify-center text-sm text-slate-400">Sin foto</div>
                  )}
                </div>
                <div className="space-y-3 p-4">
                  <div>
                    <h3 className="text-lg font-semibold text-slate-900">
                      {v.marca} {v.modelo} {v.anio || ''}
                    </h3>
                    <p className="text-sm text-slate-500">
                      {v.ref}
                      {v.version ? ` · ${v.version}` : ''}
                      {v.destacado === 1 ? ' · Destacado' : ''}
                    </p>
                  </div>
                  <p className="text-xl font-bold text-slate-900">{plata(v.precio, v.moneda)}</p>
                  <p className="text-sm text-slate-600">
                    {new Intl.NumberFormat('es-UY').format(v.km)} km · {dias(v.dias_en_stock)}
                  </p>
                  <select
                    value={v.estado}
                    disabled={guardandoEstado === v.id}
                    onChange={(e) => void cambiarEstado(v, e.target.value as EstadoVehiculo)}
                    className={`w-full rounded-lg border-0 px-3 py-2 text-sm font-medium ${ESTADO_AUTO[v.estado]}`}
                  >
                    {ESTADOS_VEHICULO.map((op) => (
                      <option key={op.id} value={op.id}>{op.label}</option>
                    ))}
                  </select>
                  <button type="button" className={`${btnGhost} w-full`} onClick={() => setForm(v)}>
                    Editar
                  </button>
                </div>
              </li>
            )
          })}
        </ul>
      )}

      <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 p-3 backdrop-blur">
        <div className="mx-auto max-w-7xl">
          <button type="button" className={`${btnPrimary} w-full`} onClick={() => setForm(null)}>
            + Cargar auto
          </button>
        </div>
      </div>
    </div>
  )
}
