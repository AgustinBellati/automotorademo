import { useCallback, useEffect, useState } from 'react'
import { api } from './api'
import type { User } from './types'
import Login from './components/Login'
import Cuentas from './components/autos/Cuentas'

export default function App() {
  const [user, setUser] = useState<User | null | undefined>(undefined)

  const alSalirSesion = useCallback(() => setUser(null), [])

  const cargarUsuario = useCallback(async () => {
    try {
      const res = await api<{ authenticated: boolean; user: User }>('auth/me.php')
      setUser(res.authenticated ? res.user : null)
    } catch {
      setUser(null)
    }
  }, [])

  useEffect(() => {
    cargarUsuario()
  }, [cargarUsuario])

  async function logout() {
    await api('auth/logout.php', {}).catch(() => {})
    setUser(null)
  }

  if (user === undefined) {
    return <div className="flex min-h-screen items-center justify-center text-sm text-slate-500">Cargando…</div>
  }
  if (user === null) return <Login onLogin={cargarUsuario} />

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
          <h1 className="shrink-0 text-lg font-bold">Automotoras</h1>
          <div className="ml-auto flex shrink-0 items-center gap-3 text-sm text-slate-500">
            <span className="hidden sm:inline">{user.name || user.email}</span>
            <button onClick={logout} className="hover:text-slate-900">Salir</button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-6">
        <Cuentas onAuth={alSalirSesion} />
      </main>
    </div>
  )
}
