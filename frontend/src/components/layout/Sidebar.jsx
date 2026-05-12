import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, FileText, BookOpen, Receipt, BarChart2,
  Users, Settings, LogOut, Zap, ChevronRight
} from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'

const navItems = [
  { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/invoices', icon: FileText, label: 'Invoices' },
  { to: '/ledgers', icon: BookOpen, label: 'Ledgers' },
  { to: '/vouchers', icon: Receipt, label: 'Vouchers' },
  { to: '/reports', icon: BarChart2, label: 'Reports' },
  { to: '/users', icon: Users, label: 'Users', adminOnly: true },
  { to: '/settings', icon: Settings, label: 'Settings' },
]

export default function Sidebar() {
  const { user, logout, hasRole } = useAuth()

  return (
    <aside className="fixed inset-y-0 left-0 w-64 bg-slate-900 flex flex-col z-30">
      {/* Logo */}
      <div className="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
        <div className="w-9 h-9 bg-indigo-500 rounded-lg flex items-center justify-center flex-shrink-0">
          <Zap size={20} className="text-white" />
        </div>
        <div>
          <p className="text-white font-semibold text-sm leading-tight">TallyAuto</p>
          <p className="text-slate-400 text-xs">Accounting Platform</p>
        </div>
      </div>

      {/* Company Info */}
      <div className="px-4 py-3 border-b border-slate-700">
        <p className="text-slate-400 text-xs uppercase tracking-wider mb-1">Company</p>
        <p className="text-white text-sm font-medium truncate">{user?.company?.name ?? 'Loading...'}</p>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        {navItems.map(({ to, icon: Icon, label, adminOnly }) => {
          if (adminOnly && !hasRole('admin')) return null
          return (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all group ${
                  isActive
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-400 hover:text-white hover:bg-slate-800'
                }`
              }
            >
              <Icon size={18} />
              <span className="flex-1">{label}</span>
              <ChevronRight size={14} className="opacity-0 group-hover:opacity-60 transition-opacity" />
            </NavLink>
          )
        })}
      </nav>

      {/* User Footer */}
      <div className="px-4 py-4 border-t border-slate-700">
        <div className="flex items-center gap-3 mb-3">
          <div className="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-white text-sm font-medium truncate">{user?.name}</p>
            <p className="text-slate-400 text-xs truncate">{user?.email}</p>
          </div>
        </div>
        <button
          onClick={logout}
          className="w-full flex items-center gap-2 px-3 py-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg text-sm transition-colors"
        >
          <LogOut size={16} />
          Sign out
        </button>
      </div>
    </aside>
  )
}
