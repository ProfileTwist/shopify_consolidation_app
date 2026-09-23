import { createContext, useContext, useEffect, useState, type ReactNode } from "react"
import { api, getToken, setToken } from "./api"
import type { User } from "./types"

interface AuthState {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  can: (permission: string) => boolean
}

const AuthContext = createContext<AuthState | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  // Restore the session on first load if a token exists.
  useEffect(() => {
    if (!getToken()) {
      setLoading(false)
      return
    }
    api
      .get<User>("/me")
      .then((res) => setUser(res.data))
      .catch(() => setToken(null))
      .finally(() => setLoading(false))
  }, [])

  async function login(email: string, password: string) {
    const res = await api.post<{ token: string; user: User }>("/login", { email, password })
    setToken(res.data.token)
    setUser(res.data.user)
  }

  async function logout() {
    try {
      await api.post("/logout")
    } finally {
      setToken(null)
      setUser(null)
    }
  }

  const can = (permission: string) => !!user?.permissions.includes(permission)

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, can }}>
      {children}
    </AuthContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error("useAuth must be used within AuthProvider")
  return ctx
}
