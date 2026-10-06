export type EstadoWeb = 'sin_web' | 'solo_redes'

export type Estado =
  | 'nuevo'
  | 'demo_lista'
  | 'contactado'
  | 'vio_demo'
  | 'respondio'
  | 'reunion'
  | 'cliente'
  | 'perdido'
  | 'descartado'

export const ESTADOS: Estado[] = [
  'nuevo', 'demo_lista', 'contactado', 'vio_demo', 'respondio', 'reunion', 'cliente', 'perdido', 'descartado',
]

export interface Lead {
  place_id: string
  nombre: string
  categoria: string
  direccion: string
  telefono: string
  telefono_int: string
  estado_web: EstadoWeb
  web_actual: string
  rating: number | null
  resenas: number
  maps_url: string
  facebook?: string
  email?: string
}

export type Fuente = 'overture'

export interface ResultadoBusqueda extends Lead {
  guardado: boolean
}

export const MOTIVOS_DESCARTE = [
  { id: 'cerrado', label: 'Cerrado' },
  { id: 'tiene_web', label: 'Tiene web' },
  { id: 'pocas_resenas', label: 'Pocas reseñas' },
  { id: 'nota_baja', label: 'Nota baja' },
  { id: 'sin_telefono', label: 'Sin teléfono' },
  { id: 'otro', label: 'Otro' },
] as const

export type MotivoDescarte = (typeof MOTIVOS_DESCARTE)[number]['id']

export interface LeadGuardado extends Lead {
  id: number
  rubro: string
  ciudad: string
  estado: Estado
  notas: string
  creado: string
  calificado: boolean | null
  motivo_descarte: MotivoDescarte | null
  revisado_google_at: string | null
  template_id: string | null
}

export interface User {
  id: number
  email: string
  name: string
}
