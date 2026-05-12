import { useState } from 'react'
import { Settings2, Server, User, Building2 } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import PageHeader from '../../components/common/PageHeader'

export default function Settings() {
  const { user } = useAuth()
  const [tallySettings, setTallySettings] = useState({
    host: user?.company?.tally_host ?? 'localhost',
    port: user?.company?.tally_port ?? 9000,
    company_name: user?.company?.tally_company_name ?? '',
  })

  return (
    <div>
      <PageHeader title="Settings" subtitle="Configure your accounting automation platform" />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Company Info */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <div className="flex items-center gap-3 mb-5">
            <div className="w-9 h-9 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
              <Building2 size={18} />
            </div>
            <h3 className="font-semibold text-gray-900">Company Info</h3>
          </div>
          <div className="space-y-3">
            {[
              { label: 'Company Name', value: user?.company?.name },
              { label: 'GSTIN', value: user?.company?.gstin },
              { label: 'PAN', value: user?.company?.pan },
              { label: 'City', value: user?.company?.city },
            ].map(({ label, value }) => (
              <div key={label}>
                <p className="text-xs text-gray-500">{label}</p>
                <p className="text-sm font-medium text-gray-900">{value ?? '—'}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Tally Configuration */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <div className="flex items-center gap-3 mb-5">
            <div className="w-9 h-9 bg-green-50 text-green-600 rounded-lg flex items-center justify-center">
              <Server size={18} />
            </div>
            <h3 className="font-semibold text-gray-900">Tally Connection</h3>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tally Host</label>
              <input
                value={tallySettings.host}
                onChange={e => setTallySettings(s => ({ ...s, host: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="localhost"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tally Port</label>
              <input
                type="number"
                value={tallySettings.port}
                onChange={e => setTallySettings(s => ({ ...s, port: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="9000"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tally Company Name</label>
              <input
                value={tallySettings.company_name}
                onChange={e => setTallySettings(s => ({ ...s, company_name: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Your Company Name in Tally"
              />
            </div>
          </div>

          <div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p className="text-xs text-amber-700 font-medium">Tally Setup Required</p>
            <p className="text-xs text-amber-600 mt-1">1. Open Tally Prime → F12 Config → Tally.net Features</p>
            <p className="text-xs text-amber-600">2. Enable "Allow BrowserWeb based access"</p>
            <p className="text-xs text-amber-600">3. Set port to 9000</p>
          </div>
        </div>

        {/* Profile Settings */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <div className="flex items-center gap-3 mb-5">
            <div className="w-9 h-9 bg-violet-50 text-violet-600 rounded-lg flex items-center justify-center">
              <User size={18} />
            </div>
            <h3 className="font-semibold text-gray-900">Your Profile</h3>
          </div>
          <div className="space-y-3">
            <div className="w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
              {user?.name?.charAt(0).toUpperCase()}
            </div>
            {[
              { label: 'Full Name', value: user?.name },
              { label: 'Email', value: user?.email },
              { label: 'Phone', value: user?.phone },
              { label: 'Role', value: user?.roles?.[0]?.name },
            ].map(({ label, value }) => (
              <div key={label}>
                <p className="text-xs text-gray-500">{label}</p>
                <p className="text-sm font-medium text-gray-900 capitalize">{value ?? '—'}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* API Info */}
      <div className="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-9 h-9 bg-gray-50 text-gray-600 rounded-lg flex items-center justify-center">
            <Settings2 size={18} />
          </div>
          <h3 className="font-semibold text-gray-900">System Information</h3>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { label: 'API Version', value: 'v1' },
            { label: 'Backend', value: 'Laravel 13' },
            { label: 'Frontend', value: 'React + Vite' },
            { label: 'Queue Driver', value: 'Database' },
          ].map(({ label, value }) => (
            <div key={label} className="p-3 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500">{label}</p>
              <p className="text-sm font-semibold text-gray-800">{value}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
