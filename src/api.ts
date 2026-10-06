// El PHP vive en /api del mismo dominio (en dev lo proxea Vite a localhost:8000)

export class AuthError extends Error {}

export async function api<T = unknown>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(`/api/${path}`, {
    method: body === undefined ? 'GET' : 'POST',
    credentials: 'include',
    headers: body === undefined ? undefined : { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  let data: any = null
  try {
    data = await res.json()
  } catch {
    // respuesta vacía o no-JSON
  }

  if (res.status === 401) throw new AuthError(data?.error || 'No autenticado')
  if (!res.ok || data?.ok === false || data?.error) {
    throw new Error(data?.error || `Error ${res.status}`)
  }
  return data as T
}
