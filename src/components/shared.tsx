import type { Lead } from '../types'

export function WebBadge({ lead }: { lead: Lead }) {
  if (lead.estado_web === 'solo_redes') {
    return (
      <a
        href={lead.web_actual}
        target="_blank"
        rel="noreferrer"
        className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 hover:bg-amber-200"
        title={lead.web_actual}
      >
        Solo redes
      </a>
    )
  }
  return (
    <span className="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">
      Sin web
    </span>
  )
}

// wa.me necesita el número sin símbolos. En Uruguay solo los celulares (09X) tienen WhatsApp;
// los celulares de Argentina llevan 549.
function whatsappUrl(telInt: string): string | null {
  let digits = telInt.replace(/\D/g, '')
  if (!digits) return null
  if (digits.startsWith('598')) return digits.startsWith('5989') ? `https://wa.me/${digits}` : null
  if (digits.startsWith('54') && !digits.startsWith('549')) digits = '549' + digits.slice(2)
  return `https://wa.me/${digits}`
}

// +59895501166 → 095 501 166 · +59826220959 → 2622 0959
function formatearTelefono(tel: string): string {
  const d = tel.replace(/\D/g, '')
  if (d.startsWith('5989') && d.length === 11) return `0${d.slice(3, 5)} ${d.slice(5, 8)} ${d.slice(8)}`
  if (d.startsWith('598') && d.length === 11) return `${d.slice(3, 7)} ${d.slice(7)}`
  return tel
}

export function Telefono({ lead }: { lead: Lead }) {
  if (!lead.telefono) return <span className="text-slate-400">—</span>
  const wa = whatsappUrl(lead.telefono_int)
  return (
    <div className="flex items-center gap-2 whitespace-nowrap">
      <a href={`tel:${lead.telefono_int || lead.telefono}`} className="hover:text-indigo-600">
        {formatearTelefono(lead.telefono)}
      </a>
      {wa && (
        <a
          href={wa}
          target="_blank"
          rel="noreferrer"
          className="rounded bg-emerald-500 px-1.5 py-0.5 text-[10px] font-semibold text-white hover:bg-emerald-600"
          title="Abrir en WhatsApp"
        >
          WA
        </a>
      )}
    </div>
  )
}

export function Contacto({ lead }: { lead: Lead }) {
  if (!lead.facebook && !lead.email) return <span className="text-slate-400">—</span>
  return (
    <div className="flex items-center gap-2 whitespace-nowrap">
      {lead.facebook && (
        <a
          href={lead.facebook}
          target="_blank"
          rel="noreferrer"
          className="rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-semibold text-white hover:bg-blue-700"
          title="Página de Facebook (podés escribirle por Messenger)"
        >
          FB
        </a>
      )}
      {lead.email && (
        <a href={`mailto:${lead.email}`} className="text-xs text-indigo-600 hover:underline" title={lead.email}>
          Email
        </a>
      )}
    </div>
  )
}

export function Rating({ lead }: { lead: Lead }) {
  if (lead.rating == null) return <span className="text-slate-400">—</span>
  return (
    <span className="whitespace-nowrap">
      <span className="text-amber-500">★</span> {lead.rating.toFixed(1)}
      <span className="text-slate-400"> ({lead.resenas})</span>
    </span>
  )
}

export function MapsLink({ lead }: { lead: Lead }) {
  if (!lead.maps_url) return null
  return (
    <a href={lead.maps_url} target="_blank" rel="noreferrer" className="text-indigo-600 hover:underline">
      Maps ↗
    </a>
  )
}

export const th = 'px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500'
export const td = 'px-3 py-2 align-top text-sm'
export const btn =
  'rounded-lg px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50'
export const btnPrimary = `${btn} bg-indigo-600 text-white hover:bg-indigo-700`
export const btnGhost = `${btn} border border-slate-300 bg-white text-slate-700 hover:bg-slate-100`
export const field =
  'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
export const input =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
