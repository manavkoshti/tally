import { useQuery } from '@tanstack/react-query'
import { FileText, CheckCircle, AlertCircle, TrendingUp, Receipt, BookOpen, RefreshCw } from 'lucide-react'
import { reportsApi } from '../../api/reports'
import { StatCard } from '../../components/common/Card'
import PageHeader from '../../components/common/PageHeader'
import { useAuth } from '../../contexts/AuthContext'

export default function Dashboard() {
  const { user } = useAuth()
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => reportsApi.dashboard().then(r => r.data.data),
    refetchInterval: 30000,
    retry: 1,
  })

  // safe name extraction — avoids crash when name is null/undefined
  const firstName = user?.name ? user.name.split(' ')[0] : 'User'

  const fmt = (num) => new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(num ?? 0)
  const fmtMoney = (num) => `₹${new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(num ?? 0)}`

  if (isError) {
    return (
      <div>
        <PageHeader title={`Welcome, ${firstName}!`} />
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-6 flex items-start gap-4">
          <AlertCircle size={22} className="text-amber-500 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-semibold text-amber-800">Backend server se connect nahi ho pa raha</p>
            <p className="text-sm text-amber-700 mt-1">Make sure Laravel server chal raha hai: <code className="bg-amber-100 px-1 rounded">php artisan serve --port=8000</code></p>
            <button onClick={() => refetch()} className="mt-3 flex items-center gap-2 px-3 py-1.5 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 transition">
              <RefreshCw size={14} /> Retry
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title={`Welcome, ${firstName}!`}
        subtitle="Here's what's happening with your accounting today"
        actions={
          <button onClick={() => refetch()} className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <RefreshCw size={15} />
            Refresh
          </button>
        }
      />

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <StatCard title="Total Invoices" value={fmt(data?.total_invoices)} icon={FileText} color="indigo" loading={isLoading} />
        <StatCard title="Synced Today" value={fmt(data?.synced_today)} icon={CheckCircle} color="green" loading={isLoading} />
        <StatCard title="Pending Sync" value={fmt(data?.pending_sync)} icon={RefreshCw} color="yellow" loading={isLoading} />
        <StatCard title="Failed Sync" value={fmt(data?.failed_sync)} icon={AlertCircle} color="red" loading={isLoading} />
      </div>

      {/* Financial Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div className="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl p-6 text-white">
          <div className="flex items-center gap-2 mb-3">
            <TrendingUp size={18} />
            <p className="text-indigo-200 text-sm font-medium">Monthly Sales</p>
          </div>
          {isLoading ? (
            <div className="h-8 w-32 bg-indigo-500 animate-pulse rounded" />
          ) : (
            <p className="text-3xl font-bold">{fmtMoney(data?.monthly_sales)}</p>
          )}
        </div>

        <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-6 text-white">
          <div className="flex items-center gap-2 mb-3">
            <Receipt size={18} />
            <p className="text-emerald-100 text-sm font-medium">Monthly Purchase</p>
          </div>
          {isLoading ? (
            <div className="h-8 w-32 bg-emerald-400 animate-pulse rounded" />
          ) : (
            <p className="text-3xl font-bold">{fmtMoney(data?.monthly_purchase)}</p>
          )}
        </div>

        <div className="bg-gradient-to-br from-violet-500 to-violet-600 rounded-xl p-6 text-white">
          <div className="flex items-center gap-2 mb-3">
            <BookOpen size={18} />
            <p className="text-violet-100 text-sm font-medium">Monthly GST</p>
          </div>
          {isLoading ? (
            <div className="h-8 w-32 bg-violet-400 animate-pulse rounded" />
          ) : (
            <p className="text-3xl font-bold">{fmtMoney(data?.monthly_gst)}</p>
          )}
        </div>
      </div>

      {/* Flow Steps */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h3 className="text-base font-semibold text-gray-900 mb-5">Automation Flow</h3>
        <div className="flex items-center gap-2 flex-wrap">
          {[
            'Upload Invoice',
            'OCR Extraction',
            'Accounting Engine',
            'Voucher Generated',
            'Tally XML',
            'Synced to Tally',
          ].map((step, i) => (
            <div key={i} className="flex items-center gap-2">
              <div className="flex items-center gap-2 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-full">
                <div className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                  {i + 1}
                </div>
                <span className="text-sm font-medium text-indigo-700 whitespace-nowrap">{step}</span>
              </div>
              {i < 5 && <span className="text-gray-300 text-lg">→</span>}
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
