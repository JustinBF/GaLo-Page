import { Outlet } from 'react-router-dom'
import { ErrorBoundary } from '../ErrorBoundary'
import { AppHeader } from './AppHeader'

export function AppLayout() {
  return (
    <div className="app-shell">
      <AppHeader />
      <main className="content">
        <ErrorBoundary>
          <Outlet />
        </ErrorBoundary>
      </main>
    </div>
  )
}
