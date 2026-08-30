import { Outlet } from 'react-router-dom'
import { AppHeader } from './AppHeader'

export function AppLayout() {
  return (
    <div className="app-shell">
      <AppHeader />
      <main className="content">
        <Outlet />
      </main>
    </div>
  )
}
