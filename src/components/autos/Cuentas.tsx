import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { api, AuthError } from '../../api'
import { ESTADOS_CUENTA, urlCatalogo, type Cuenta, type EstadoCuenta } from '../../autosTypes'
import { btnGhost, btnPrimary, input } from '../shared'
import CuentaVista from './Cuenta'

const ESTADO_COLOR: Record<EstadoCuenta, string> = {
  demo: 'bg-indigo-100 text-indigo-800',
  activa: 'bg-emerald-100 text-emerald-800',
  pausada: 'bg-amber-100 text-amber-800',
}

function etiqueta(estado: EstadoCuenta) {
  return ESTADOS_CUENTA.find((e) => e.id === estado)?.label ?? estado
}

interface Props {
  onAuth: () => void
}

export default function Cuentas({ onAuth }: Props) {
  const [cuentas, setCuentas] = useState<Cuenta[]>([])
  const [abierta, setAbierta] = useState<Cuenta | null>(null)
  const [creando, setCreando] = useState(false)
  const [nombre, setNombre] = useState('')
  const [whatsapp, setWhatsapp] = useState('')
  const [guardando, setGuardando] = useState(false)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [copiado, setCopiado] = useState<number | null>(null)

  const cargar = useCallback(async () => {
    try {
      const res = await api<{ data: Cuenta[] }>('autos/cuentas.php')
      setCuentas(res.data)
      setError('')
    } catch (err) {
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudieron cargar las automotoras')
    } finally {
      setCargando(false)
    }
  }, [onAuth])

  useEffect(() => {
    void cargar()
  }, [cargar])

  async function crear(e: FormEvent) {
    e.preventDefault()
    const nombreLimpio = nombre.trim()
    const whatsappLimpio = whatsapp.trim()
    if (!nombreLimpio || !whatsappLimpio) {
      setError('Completá el nombre y el WhatsApp')
      return
    }
    setGuardando(true)
    setError('')
    try {
      const res = await api<{ data: Cuenta }>('autos/cuentas.php', {
        action: 'crear',
        nombre: nombreLimpio,
        whatsapp: whatsappLimpio,
      })
      setCuentas((prev) => [res.data, ...prev])
      setNombre('')
      setWhatsapp('')
      setCreando(false)
      setAbierta(res.data)
    } catch (err) {
      if (err instanceof AuthError) onAuth()
      else setError(err instanceof Error ? err.message : 'No se pudo crear la automotora')
    } finally {
      setGuardando(false)
    }
  }

  async function copiar(cuenta: Cuenta) {
    const url = urlCatalogo(cuenta.slug)
    try {
      if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(url)
      else {
        const el = document.createElement('textarea')
        el.value = url
        document.body.appendChild(el)
        el.select()
        document.execCommand('copy')
        el.remove()
      }
      setCopiado(cuenta.id)
      window.setTimeout(() => setCopiado((actual) => (actual === cuenta.id ? null : actual)), 2000)
    } catch {
      setError('No se pudo copiar el link')
    }
  }

  if (abierta) {
    return (
      <CuentaVista
        cuenta={abierta}
        onVolver={() => {
          setAbierta(null)
          void cargar()
        }}
        onActualizada={(cuenta) => {
          setAbierta(cuenta)
          setCuentas((prev) => prev.map((c) => (c.id === cuenta.id ? cuenta : c)))
        }}
        onAuth={onAuth}
      />
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-xl font-bold text-slate-900">Automotoras</h2>
        {!creando && (
          <button className={`${btnPrimary} w-full sm:w-auto`} onClick={() => setCreando(true)}>
            Nueva automotora
          </button>
        )}
      </div>

      {error && <div className="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700 ring-1 ring-rose-200">{error}</div>}

      {creando && (
        <form onSubmit={crear} className="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
          <h3 className="font-semibold text-slate-900">Nueva automotora</h3>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">Nombre</span>
            <input className={input} value={nombre} onChange={(e) => setNombre(e.target.value)} maxLength={120} required autoFocus />
          </label>
          <label className="block space-y-1">
            <span className="text-sm font-medium text-slate-700">WhatsApp</span>
            <input className={input} value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} inputMode="tel" placeholder="099 123 456" required />
          </label>
          <div className="flex gap-2">
            <button type="button" className={btnGhost} onClick={() => setCreando(false)} disabled={guardando}>
              Cancelar
            </button>
            <button className={`${btnPrimary} flex-1`} disabled={guardando}>
              {guardando ? 'Creando…' : 'Crear'}
            </button>
          </div>
        </form>
      )}

      {cargando ? (
        <p className="text-sm text-slate-500">Cargando…</p>
      ) : cuentas.length === 0 ? (
        <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
          Todavía no hay automotoras. Creá la primera para cargar el stock.
        </p>
      ) : (
        <ul className="space-y-3">
          {cuentas.map((cuenta) => (
            <li key={cuenta.id} className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
              <div className="flex items-start gap-3">
                <span
                  className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                  style={{ background: cuenta.color }}
                >
                  {cuenta.iniciales || cuenta.nombre.slice(0, 1)}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold text-slate-900">{cuenta.nombre}</h3>
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${ESTADO_COLOR[cuenta.estado]}`}>
                      {etiqueta(cuenta.estado)}
                    </span>
                  </div>
                  <p className="mt-1 text-sm text-slate-500">
                    {cuenta.conteo.disponible} disponibles · {cuenta.conteo.reservado} reservados · {cuenta.conteo.vendido} vendidos
                  </p>
                </div>
              </div>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <button className={`${btnPrimary} col-span-2`} onClick={() => setAbierta(cuenta)}>
                  Abrir
                </button>
                <a
                  href={urlCatalogo(cuenta.slug)}
                  target="_blank"
                  rel="noreferrer"
                  className={`${btnGhost} inline-flex items-center justify-center text-center`}
                >
                  Ver catálogo
                </a>
                <button className={btnGhost} onClick={() => void copiar(cuenta)}>
                  {copiado === cuenta.id ? 'Copiado' : 'Copiar link'}
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
