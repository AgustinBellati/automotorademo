import { useMemo, useState } from 'react'
import { api } from '../api'
import { ESTADOS, MOTIVOS_DESCARTE, type Estado, type LeadGuardado, type MotivoDescarte } from '../types'
import { Contacto, MapsLink, Telefono, WebBadge, btnGhost, field, td, th } from './shared'

const ESTADO_COLOR: Record<Estado, string> = {
  nuevo: 'bg-slate-100 text-slate-700',
  demo_lista: 'bg-indigo-100 text-indigo-800',
  contactado: 'bg-sky-100 text-sky-800',
  vio_demo: 'bg-cyan-100 text-cyan-800',
  respondio: 'bg-amber-100 text-amber-800',
  reunion: 'bg-violet-100 text-violet-800',
  cliente: 'bg-emerald-100 text-emerald-800',
  perdido: 'bg-orange-100 text-orange-800',
  descartado: 'bg-rose-100 text-rose-700',
}

function etiquetaEstado(e: string) {
  return e.replace(/_/g, ' ')
}

function etiquetaMotivo(id: string) {
  return MOTIVOS_DESCARTE.find((m) => m.id === id)?.label ?? id
}

const RECORDATORIO_CALIFICADO = '≥ 20 reseñas, nota ≥ 4,2, sin web propia, abierto, con teléfono'

function exportarCsv(leads: LeadGuardado[]) {
  const cols: (keyof LeadGuardado)[] = [
    'nombre', 'rubro', 'ciudad', 'categoria', 'telefono', 'telefono_int', 'direccion',
    'estado_web', 'web_actual', 'facebook', 'email', 'rating', 'resenas', 'estado', 'notas', 'maps_url',
  ]
  const esc = (v: unknown) => {
    const s = v == null ? '' : String(v)
    return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
  }
  const csv = '﻿' + [cols.join(','), ...leads.map((l) => cols.map((c) => esc(l[c])).join(','))].join('\n')
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  a.download = `leads-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(a.href)
}

interface Props {
  leads: LeadGuardado[]
  setLeads: (fn: (prev: LeadGuardado[]) => LeadGuardado[]) => void
  error: string
}

type Revision = '' | 'calificados' | 'sin_revisar'

export default function MisLeads({ leads, setLeads, error }: Props) {
  const [filtro, setFiltro] = useState('')
  const [estado, setEstado] = useState<Estado | ''>('')
  const [revision, setRevision] = useState<Revision>('')
  const [descartando, setDescartando] = useState<number | null>(null)
  const [errorAccion, setErrorAccion] = useState('')

  const visibles = useMemo(() => {
    const q = filtro.trim().toLowerCase()
    return leads.filter((l) => {
      if (estado && l.estado !== estado) return false
      if (revision === 'calificados' && l.calificado !== true) return false
      if (revision === 'sin_revisar' && l.calificado != null) return false
      if (q && ![l.nombre, l.rubro, l.ciudad, l.direccion, l.notas].some((v) => v?.toLowerCase().includes(q))) return false
      return true
    })
  }, [leads, filtro, estado, revision])

  async function actualizar(lead: LeadGuardado, cambios: Partial<Pick<LeadGuardado, 'estado' | 'notas'>>) {
    const nuevo = { ...lead, ...cambios }
    setLeads((prev) => prev.map((l) => (l.id === lead.id ? nuevo : l)))
    try {
      setErrorAccion('')
      await api('leads/leads.php', { action: 'update', id: lead.id, estado: nuevo.estado, notas: nuevo.notas })
    } catch (err) {
      setLeads((prev) => prev.map((l) => (l.id === lead.id ? lead : l)))
      setErrorAccion(err instanceof Error ? err.message : 'No se pudo actualizar')
    }
  }

  async function calificar(lead: LeadGuardado, calificado: boolean, motivo?: MotivoDescarte) {
    const previo = lead
    setLeads((prev) =>
      prev.map((l) =>
        l.id === lead.id
          ? {
              ...l,
              calificado,
              motivo_descarte: calificado ? null : (motivo ?? l.motivo_descarte),
              estado: calificado ? l.estado : 'descartado',
              template_id: calificado ? l.template_id : null,
            }
          : l,
      ),
    )
    try {
      setErrorAccion('')
      const res = await api<{
        data: Pick<LeadGuardado, 'calificado' | 'motivo_descarte' | 'revisado_google_at' | 'template_id' | 'estado'>
      }>('leads/leads.php', { action: 'calificar', id: lead.id, calificado, motivo })
      setLeads((prev) => prev.map((l) => (l.id === lead.id ? { ...l, ...res.data } : l)))
      setDescartando(null)
    } catch (err) {
      setLeads((prev) => prev.map((l) => (l.id === lead.id ? previo : l)))
      setErrorAccion(err instanceof Error ? err.message : 'No se pudo calificar')
    }
  }

  async function eliminar(lead: LeadGuardado) {
    if (!confirm(`¿Eliminar "${lead.nombre}" de tus leads?`)) return
    try {
      setErrorAccion('')
      await api('leads/leads.php', { action: 'delete', id: lead.id })
      setLeads((prev) => prev.filter((l) => l.id !== lead.id))
    } catch (err) {
      setErrorAccion(err instanceof Error ? err.message : 'No se pudo eliminar')
    }
  }

  const conteo = ESTADOS.map((e) => ({ e, n: leads.filter((l) => l.estado === e).length }))

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-9">
        {conteo.map(({ e, n }) => (
          <button
            key={e}
            onClick={() => setEstado(estado === e ? '' : e)}
            className={`rounded-xl bg-white p-3 text-left shadow-sm ring-1 transition ${estado === e ? 'ring-2 ring-indigo-500' : 'ring-slate-200 hover:ring-slate-300'}`}
          >
            <div className="text-2xl font-bold text-slate-900">{n}</div>
            <div className="text-xs capitalize text-slate-500">{etiquetaEstado(e)}</div>
          </button>
        ))}
      </div>

      {(error || errorAccion) && (
        <div className="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700 ring-1 ring-rose-200">{error || errorAccion}</div>
      )}

      <div className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
          <input className={`${field} w-full max-w-xs`} placeholder="Buscar por nombre, rubro, notas…" value={filtro} onChange={(e) => setFiltro(e.target.value)} />
          <select className={field} value={estado} onChange={(e) => setEstado(e.target.value as Estado | '')}>
            <option value="">Todos los estados</option>
            {ESTADOS.map((e) => (
              <option key={e} value={e} className="capitalize">{etiquetaEstado(e)}</option>
            ))}
          </select>
          <button
            onClick={() => setRevision(revision === 'calificados' ? '' : 'calificados')}
            className={`rounded-lg px-3 py-2 text-sm ${revision === 'calificados' ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'}`}
          >
            Sólo calificados
          </button>
          <button
            onClick={() => setRevision(revision === 'sin_revisar' ? '' : 'sin_revisar')}
            className={`rounded-lg px-3 py-2 text-sm ${revision === 'sin_revisar' ? 'bg-slate-200 text-slate-800 ring-1 ring-slate-300' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'}`}
          >
            Sin revisar
          </button>
          <span className="text-sm text-slate-500">{visibles.length} lead(s)</span>
          <button className={`${btnGhost} ml-auto`} onClick={() => exportarCsv(visibles)} disabled={!visibles.length}>
            Exportar CSV
          </button>
        </div>

        {visibles.length === 0 ? (
          <p className="p-8 text-center text-sm text-slate-500">
            {leads.length ? 'No hay leads con ese filtro.' : 'Todavía no guardaste leads. Hacé una búsqueda y guardá los que te interesen.'}
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className={th}>Negocio</th>
                  <th className={th}>Teléfono</th>
                  <th className={th}>Web</th>
                  <th className={th}>Contacto</th>
                  <th className={th}>Estado</th>
                  <th className={th}>Notas</th>
                  <th className={th}>Calificación</th>
                  <th className={th}></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {visibles.map((l) => (
                  <tr key={l.id} className="hover:bg-indigo-50/40">
                    <td className={td}>
                      <div className="font-medium text-slate-900">{l.nombre}</div>
                      <div className="text-xs text-slate-500">{l.rubro} · {l.ciudad}</div>
                      <div className="text-xs text-slate-400">{l.direccion}</div>
                    </td>
                    <td className={td}><Telefono lead={l} /></td>
                    <td className={td}><WebBadge lead={l} /></td>
                    <td className={td}><Contacto lead={l} /></td>
                    <td className={td}>
                      <select
                        value={l.estado}
                        onChange={(e) => actualizar(l, { estado: e.target.value as Estado })}
                        className={`rounded-full border-0 px-2 py-1 text-xs font-medium capitalize ${ESTADO_COLOR[l.estado]}`}
                      >
                        {ESTADOS.map((e) => (
                          <option key={e} value={e}>{etiquetaEstado(e)}</option>
                        ))}
                      </select>
                    </td>
                    <td className={td}>
                      <input
                        key={`${l.id}-${l.notas}`}
                        defaultValue={l.notas}
                        placeholder="Agregar nota…"
                        onBlur={(e) => e.target.value !== l.notas && actualizar(l, { notas: e.target.value })}
                        className="w-48 rounded border border-transparent px-2 py-1 text-sm hover:border-slate-200 focus:border-indigo-400 focus:outline-none"
                      />
                    </td>
                    <td className={td}>
                      <div className="flex items-center gap-2 whitespace-nowrap">
                        <MapsLink lead={l} />
                        <button
                          title={RECORDATORIO_CALIFICADO}
                          onClick={() => calificar(l, true)}
                          className={`rounded-md px-1.5 py-0.5 text-sm font-semibold ${l.calificado === true ? 'bg-emerald-100 text-emerald-700' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'}`}
                        >
                          ✓
                        </button>
                        <button
                          title="Descartar"
                          onClick={() => setDescartando(descartando === l.id ? null : l.id)}
                          className={`rounded-md px-1.5 py-0.5 text-sm font-semibold ${l.calificado === false ? 'bg-rose-100 text-rose-700' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'}`}
                        >
                          ✗
                        </button>
                      </div>
                      {descartando === l.id && (
                        <select
                          className={`${field} mt-2 w-40`}
                          value={l.motivo_descarte ?? ''}
                          autoFocus
                          onChange={(e) => {
                            const motivo = e.target.value as MotivoDescarte
                            if (motivo) void calificar(l, false, motivo)
                          }}
                          onBlur={() => {
                            window.setTimeout(() => setDescartando((actual) => (actual === l.id ? null : actual)), 0)
                          }}
                        >
                          <option value="">Elegí un motivo</option>
                          {MOTIVOS_DESCARTE.map((m) => (
                            <option key={m.id} value={m.id}>{m.label}</option>
                          ))}
                        </select>
                      )}
                      {l.calificado === false && l.motivo_descarte && descartando !== l.id && (
                        <div className="mt-1 text-xs text-rose-700">{etiquetaMotivo(l.motivo_descarte)}</div>
                      )}
                    </td>
                    <td className={`${td} whitespace-nowrap`}>
                      <button onClick={() => eliminar(l)} className="text-slate-400 hover:text-rose-600" title="Eliminar">
                        ✕
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
