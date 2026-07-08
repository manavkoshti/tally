import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Search, Trash2, Eye, Zap, RefreshCw, AlertCircle, ChevronLeft, ChevronRight } from 'lucide-react'
import toast from 'react-hot-toast'
import { invoicesApi } from '../../api/invoices'
import { tallyApi } from '../../api/tally'
import PageHeader from '../../components/common/PageHeader'
import StatusBadge from '../../components/common/StatusBadge'
import LoadingSpinner from '../../components/common/LoadingSpinner'

export default function InvoiceList() {
  const [params, setParams] = useState({ page: 1, per_page: 15, search: '', type: '' })
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', params],
    queryFn: () => invoicesApi.list(params).then(r => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: invoicesApi.delete,
    onSuccess: () => {
      toast.success('Invoice deleted')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
    onError: () => toast.error('Failed to delete'),
  })

  const processMutation = useMutation({
    mutationFn: invoicesApi.processAccounting,
    onSuccess: () => {
      toast.success('Accounting processing started')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
  })

  const syncTallyMutation = useMutation({
    mutationFn: invoicesApi.syncTally,
    onSuccess: () => {
      toast.success('Tally sync queued')
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
    },
    onError: (err) => toast.error(err?.response?.data?.message ?? 'Failed to start sync'),
  })

  const { data: tallyHealth } = useQuery({
    queryKey: ['tally-health'],
    queryFn: () => tallyApi.testConnection().then(r => r.data?.data),
    refetchInterval: 30000,
    retry: false,
  })
  const tallyReachable = tallyHealth?.reachable

  const invoiceTypes = ['', 'sales', 'purchase', 'expense', 'journal', 'payment', 'receipt']
  const fmt = (n) => `₹${Number(n ?? 0).toLocaleString('en-IN')}`

  return (
    <div>
      <PageHeader
        title="Invoices"
        subtitle={`${data?.meta?.total ?? 0} total invoices`}
        actions={
          <Link to="/invoices/create" className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            <Plus size={16} />
            New Invoice
          </Link>
        }
      />

      {tallyHealth && !tallyReachable && (
        <div className="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          <AlertCircle size={18} className="mt-0.5 flex-shrink-0" />
          <div>
            <p className="font-medium">Tally is not reachable {tallyHealth?.host && tallyHealth?.port ? `at ${tallyHealth.host}:${tallyHealth.port}` : ''}.</p>
            <p className="text-xs opacity-90 mt-0.5">{tallyHealth?.message ?? 'Start Tally, open a company, and enable ODBC Server (F12 > Advanced).'} Invoices will keep saving; sync will resume automatically once Tally is online.</p>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-48">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            placeholder="Search invoice number, party..."
            value={params.search}
            onChange={e => setParams(p => ({ ...p, search: e.target.value, page: 1 }))}
            className="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <select
          value={params.type}
          onChange={e => setParams(p => ({ ...p, type: e.target.value, page: 1 }))}
          className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          {invoiceTypes.map(t => <option key={t} value={t}>{t ? t.charAt(0).toUpperCase() + t.slice(1) : 'All Types'}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? (
          <LoadingSpinner text="Loading invoices..." />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50 border-b border-gray-200">
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Party</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                    <th className="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">OCR</th>
                    <th className="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tally</th>
                    <th className="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {data?.data?.length === 0 && (
                    <tr><td colSpan={8} className="text-center py-12 text-gray-400">No invoices found. Create your first invoice!</td></tr>
                  )}
                  {data?.data?.map(inv => (
                    <tr key={inv.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 font-medium text-gray-900">{inv.invoice_number ?? `INV-${inv.id}`}</td>
                      <td className="px-6 py-4 text-gray-600">{inv.party_name ?? '—'}</td>
                      <td className="px-6 py-4">
                        <span className="capitalize px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">{inv.invoice_type}</span>
                      </td>
                      <td className="px-6 py-4 text-gray-500">{inv.invoice_date}</td>
                      <td className="px-6 py-4 text-right font-medium text-gray-900">{fmt(inv.total_amount)}</td>
                      <td className="px-6 py-4 text-center"><StatusBadge status={inv.ocr_status} /></td>
                      <td className="px-6 py-4 text-center"><StatusBadge status={inv.tally_sync_status} /></td>
                      <td className="px-6 py-4">
                        <div className="flex items-center justify-center gap-2">
                          <Link to={`/invoices/${inv.id}`} className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition">
                            <Eye size={16} />
                          </Link>
                          {inv.accounting_status !== 'completed' && (
                            <button
                              onClick={() => processMutation.mutate(inv.id)}
                              className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition"
                              title="Process Accounting"
                            >
                              <Zap size={16} />
                            </button>
                          )}
                          {inv.accounting_status === 'completed' && inv.tally_sync_status !== 'synced' && (
                            <button
                              onClick={() => syncTallyMutation.mutate(inv.id)}
                              disabled={syncTallyMutation.isPending}
                              className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition disabled:opacity-40"
                              title="Retry Tally Sync"
                            >
                              <RefreshCw size={16} />
                            </button>
                          )}
                          <button
                            onClick={() => {
                              if (confirm('Delete this invoice?')) deleteMutation.mutate(inv.id)
                            }}
                            className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {data?.meta && data.meta.last_page > 1 && (
              <div className="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                <p className="text-sm text-gray-500">
                  Page {data.meta.current_page} of {data.meta.last_page} ({data.meta.total} total)
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => setParams(p => ({ ...p, page: p.page - 1 }))}
                    disabled={data.meta.current_page === 1}
                    className="p-2 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"
                  >
                    <ChevronLeft size={16} />
                  </button>
                  <button
                    onClick={() => setParams(p => ({ ...p, page: p.page + 1 }))}
                    disabled={data.meta.current_page === data.meta.last_page}
                    className="p-2 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"
                  >
                    <ChevronRight size={16} />
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
