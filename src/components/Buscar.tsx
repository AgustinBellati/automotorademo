import { useEffect, useState, type FormEvent } from 'react'
import { api } from '../api'
import type { Fuente, ResultadoBusqueda } from '../types'
import { Contacto, MapsLink, Telefono, WebBadge, btnGhost, btnPrimary, input, td, th } from './shared'

interface BuscarResponse {
  data: { revisados: number; leads: ResultadoBusqueda[]; fuente: Fuente; limite?: number }
}

export default function Buscar({ onGuardado }: { onGuardado: () => void }) {
  const [rubro, setRubro] = useState('')
  const [ciudad, setCiudad] = useState('')
  const [incluirRedes, setIncluirRedes] = useState(true)
  const [buscado, setBuscado] = useState({ rubro: '', ciudad: '' })
  const [resultados, setResultados] = useState<ResultadoBusqueda[] | null>(null)
  const [revisados, setRevisados] = useState(0)
  const [fuente, setFuente] = useState<Fuente>('overture')
  const [limite, setLimite] = useState<number | undefined>()
  const [rubros, setRubros] = useState<string[]>([])
  const [seleccion, setSeleccion] = useState<Set<string>>(new Set())
  const [loading, setLoading] = useState(false)
  const [guardando, setGuardando] = useState(false)
  const [msg, setMsg] = useState<{ tipo: 'ok' | 'error'; texto: string } | null>(null)

  useEffect(() => {
    api<{ data: string[] }>('leads/buscar.php')
      .then((res) => setRubros(res.data))
      .catch(() => {})
  }, [])

  async function buscar(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setMsg(null)
    setSeleccion(new Set())
    try {
      const res = await api<BuscarResponse>('leads/buscar.php', { rubro, ciudad, incluirRedes })
      setResultados(res.data.leads)
      setRevisados(res.data.revisados)
      setFuente(res.data.fuente)
      setLimite(res.data.limite)
      setBuscado({ rubro, ciudad })
    } catch (err) {
      setMsg({ tipo: 'error', texto: err instanceof Error ? err.message : 'Error al buscar' })
    } finally {
      setLoading(false)
    }
  }

  const pendientes = resultados?.filter((r) => !r.guardado) ?? []
  const todosSeleccionados = pendientes.length > 0 && pendientes.every((r) => seleccion.has(r.place_id))

  function toggle(id: string) {
    const next = new Set(seleccion)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSeleccion(next)
  }

  function toggleTodos() {
    setSeleccion(todosSeleccionados ? new Set() : new Set(pendientes.map((r) => r.place_id)))
  }

  async function guardar(items: ResultadoBusqueda[]) {
    if (!items.length) return
    setGuardando(true)
    setMsg(null)
    try {
      const res = await api<{ data: { guardados: number } }>('leads/leads.php', {
        action: 'save',
        rubro: buscado.rubro,
        ciudad: buscado.ciudad,
        leads: items,
      })
      const ids = new Set(items.map((i) => i.place_id))
      setResultados((prev) => prev?.map((r) => (ids.has(r.place_id) ? { ...r, guardado: true } : r)) ?? null)
      setSeleccion(new Set())
      setMsg({ tipo: 'ok', texto: `${res.data.guardados} lead(s) guardados` })
      onGuardado()
    } catch (err) {
      setMsg({ tipo: 'error', texto: err instanceof Error ? err.message : 'Error al guardar' })
    } finally {
      setGuardando(false)
    }
  }

  return (
    <div className="space-y-4">
      <form onSubmit={buscar} className="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-[1fr_1fr_auto_auto] md:items-center">
        <input className={input} list="rubros" placeholder="Rubro (ej: peluquería)" value={rubro} onChange={(e) => setRubro(e.target.value)} required />
        <input className={input} placeholder="Ciudad o departamento (ej: Montevideo)" value={ciudad} onChange={(e) => setCiudad(e.target.value)} required />
        <datalist id="rubros">
          {rubros.map((r) => (
            <option key={r} value={r} />
          ))}
        </datalist>
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" checked={incluirRedes} onChange={(e) => setIncluirRedes(e.target.checked)} />
          Incluir los que solo tienen redes
        </label>
        <button className={btnPrimary} disabled={loading}>
          {loading ? 'Buscando…' : 'Buscar'}
        </button>
      </form>

      {resultados && fuente === 'overture' && (
        <div className="rounded-lg bg-sky-50 px-4 py-2 text-sm text-sky-800 ring-1 ring-sky-200">
          Datos abiertos de <b>Overture Maps</b>. Antes de contactar, revisá el negocio en Google Maps (link “Maps”): algunos pueden haber cerrado o tener web nueva.
        </div>
      )}
      {msg && (
        <div className={`rounded-lg px-4 py-2 text-sm ring-1 ${msg.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-rose-200'}`}>
          {msg.texto}
        </div>
      )}

      {resultados && (
        <div className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
            <p className="text-sm text-slate-600">
              <b className="text-slate-900">{resultados.length}</b> sin web propia de {revisados} negocios revisados
              {limite && resultados.length >= limite && <span className="text-slate-400"> · mostrando los primeros {limite}</span>}
            </p>
            <div className="flex gap-2">
              <button className={btnGhost} disabled={guardando || !pendientes.length} onClick={() => guardar(pendientes)}>
                Guardar todos ({pendientes.length})
              </button>
              <button
                className={btnPrimary}
                disabled={guardando || !seleccion.size}
                onClick={() => guardar(pendientes.filter((r) => seleccion.has(r.place_id)))}
              >
                {guardando ? 'Guardando…' : `Guardar seleccionados (${seleccion.size})`}
              </button>
            </div>
          </div>

          {resultados.length === 0 ? (
            <p className="p-8 text-center text-sm text-slate-500">Todos los negocios de esta búsqueda tienen web. Probá otro rubro o zona.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className={th}>
                      <input type="checkbox" checked={todosSeleccionados} onChange={toggleTodos} disabled={!pendientes.length} />
                    </th>
                    <th className={th}>Negocio</th>
                    <th className={th}>Teléfono</th>
                    <th className={th}>Dirección</th>
                    <th className={th}>Web</th>
                    <th className={th}>Contacto</th>
                    <th className={th}></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {resultados.map((r) => (
                    <tr key={r.place_id} className={r.guardado ? 'bg-slate-50/60' : 'hover:bg-indigo-50/40'}>
                      <td className={td}>
                        {r.guardado ? (
                          <span className="text-xs font-medium text-emerald-600" title="Ya guardado">✓</span>
                        ) : (
                          <input type="checkbox" checked={seleccion.has(r.place_id)} onChange={() => toggle(r.place_id)} />
                        )}
                      </td>
                      <td className={td}>
                        <div className="font-medium text-slate-900">{r.nombre}</div>
                        <div className="text-xs text-slate-500">{r.categoria}</div>
                      </td>
                      <td className={td}><Telefono lead={r} /></td>
                      <td className={`${td} max-w-xs text-slate-600`}>{r.direccion}</td>
                      <td className={td}><WebBadge lead={r} /></td>
                      <td className={td}><Contacto lead={r} /></td>
                      <td className={td}><MapsLink lead={r} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
