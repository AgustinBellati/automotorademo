export type EstadoCuenta = 'demo' | 'activa' | 'pausada'
export type EstadoVehiculo = 'disponible' | 'reservado' | 'vendido'
export type TipoVehiculo = 'sedan' | 'hatch' | 'suv' | 'pickup' | 'otro'
export type Moneda = 'USD' | 'UYU'

export interface ConteoAutos {
  disponible: number
  reservado: number
  vendido: number
}

export interface Cuenta {
  id: number
  lead_id: number | null
  slug: string
  nombre: string
  iniciales: string
  direccion: string
  whatsapp: string
  color: string
  tasa_anual: number
  plazos: number[]
  entrega_min_pct: number
  estado: EstadoCuenta
  creado: string
  actualizado: string
  conteo: ConteoAutos
}

export interface Foto {
  id: number
  orden: number
  archivo: string
  ext: string
  ancho: number
  alto: number
  url_800: string
  url_1600: string
}

export interface Vehiculo {
  id: number
  cuenta_id: number
  ref: string
  marca: string
  modelo: string
  version: string
  anio: number
  km: number
  precio: number
  moneda: Moneda
  combustible: string
  caja: string
  tipo: TipoVehiculo
  color: string
  descripcion: string | null
  estado: EstadoVehiculo
  destacado: 0 | 1
  ingreso_at: string
  vendido_at: string | null
  creado: string
  actualizado: string
  dias_en_stock: number
  fotos: Foto[]
}

export const ESTADOS_CUENTA: { id: EstadoCuenta; label: string }[] = [
  { id: 'demo', label: 'Demo' },
  { id: 'activa', label: 'Activa' },
  { id: 'pausada', label: 'Pausada' },
]

export const ESTADOS_VEHICULO: { id: EstadoVehiculo; label: string }[] = [
  { id: 'disponible', label: 'Disponible' },
  { id: 'reservado', label: 'Reservado' },
  { id: 'vendido', label: 'Vendido' },
]

export const TIPOS_VEHICULO: { id: TipoVehiculo; label: string }[] = [
  { id: 'sedan', label: 'Sedán' },
  { id: 'hatch', label: 'Hatch' },
  { id: 'suv', label: 'SUV' },
  { id: 'pickup', label: 'Pickup' },
  { id: 'otro', label: 'Otro' },
]

export const COMBUSTIBLES = ['Nafta', 'Diésel', 'Híbrido', 'Eléctrico'] as const
export const CAJAS = ['Manual', 'Automática'] as const

export function urlCatalogo(slug: string): string {
  return `${window.location.origin}/autos/${encodeURIComponent(slug)}/`
}
