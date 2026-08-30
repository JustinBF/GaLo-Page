import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AppLayout } from './components/layout/AppLayout'
import { AuthProvider } from './auth/AuthContext'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { Dashboard } from './pages/Dashboard'
import { Events } from './pages/Events'
import { Login } from './pages/Login'
import { OrganizersTable } from './pages/OrganizersTable'
import { PlayersTable } from './pages/PlayersTable'
import { Settings } from './pages/Settings'
import { Shop } from './pages/Shop'
import { ThemeProvider } from './theme/ThemeContext'

export default function App() {
  return (
    <ThemeProvider>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<Login />} />

            <Route element={<ProtectedRoute />}>
              <Route element={<AppLayout />}>
                <Route index element={<Dashboard />} />
                <Route path="/jugadores" element={<PlayersTable />} />
                <Route path="/organizadores" element={<OrganizersTable />} />
                <Route path="/eventos" element={<Events />} />
                <Route path="/tienda" element={<Shop />} />
                <Route path="/ajustes" element={<Settings />} />
              </Route>
            </Route>
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </ThemeProvider>
  )
}
