import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, RefreshCw, CheckCircle, Trash2, Edit2, ChevronLeft, ChevronRight } from 'lucide-react'
import toast from 'react-hot-toast'
import { ledgersApi } from '../../api/ledgers'
import PageHeader from '../../components/common/PageHeader'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import LedgerForm from './LedgerForm'

export default function LedgerList() {
  const [params, setParams] = useState({ page: 1, per_page: 15, search: '', type: '' })
  const [showForm, setShowForm] = useState(false)
  const [editLedger, setEditLedger] = useState(null)
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['ledgers', params],
    queryFn: () => ledgersApi.list(params).then(r => r.data),
  })

  const deleteMutation = useMutation({
    mutationFn: ledgersApi.delete,
    onSuccess: () => { toast.success('Ledger deleted'); queryClient.invalidateQueries({ queryKey: ['ledgers'] }) },
    onError: () => toast.error('Cannot delete ledger in use'),
  })

  const syncMutation = useMutation({
    mutationFn: ledgersApi.syncToTally,
    onSuccess: (res) => {
      if (res.data.data?.success) toast.success('Ledger synced to Tally!')
      else toast.error('Sync failed - check Tally connection')
      queryClient.invalidateQueries({ queryKey: ['ledgers'] })
    },
  })

  const ledgerTypes = ['', 'debtor', 'creditor', 'bank', 'cash', 'income', 'expense', 'gst', 'other']

  return (
    <div>
      <PageHeader
        title="Ledgers"
        subtitle={`${data?.meta?.total ?? 0} ledgers`}
        actions={
          <button
            onClick={() => { setEditLedger(null); setShowForm(true) }}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition"
          >
            <Plus size={16} /> New Ledger
          </button>
        }
      />

      {showForm && (
        <LedgerForm
          ledger={editLedger}
          onClose={() => { setShowForm(false); setEditLedger(null) }}
          onSave={() => { setShowForm(false); setEditLedger(null); queryClient.invalidateQueries({ queryKey: ['ledgers'] }) }}
        />
      )}

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-48">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            placeholder="Search ledger name, GSTIN..."
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
          {ledgerTypes.map(t => <option key={t} value={t}>{t ? t.charAt(0).toUpperCase() + t.slice(1) : 'All Types'}</option>)}
        </select>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? <LoadingSpinner text="Loading ledgers..." /> : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50 border-b border-gray-200">
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">GSTIN</th>
                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Opening Balance</th>
                    <th className="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Tally Synced</th>
                    <th className="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {data?.data?.length === 0 && (
                    <tr><td colSpan={6} className="text-center py-12 text-gray-400">No ledgers found. Create your first ledger!</td></tr>
                  )}
                  {data?.data?.map(l => (
                    <tr key={l.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 font-medium text-gray-900">{l.name}</td>
                      <td className="px-6 py-4">
                        <span className="capitalize px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs font-medium">{l.type}</span>
                      </td>
                      <td className="px-6 py-4 text-gray-500 font-mono text-xs">{l.gstin ?? '—'}</td>
                      <td className="px-6 py-4 text-right text-gray-600">
                        {l.opening_balance > 0 ? `₹${Number(l.opening_balance).toLocaleString('en-IN')} ${l.opening_balance_type}` : '—'}
                      </td>
                      <td className="px-6 py-4 text-center">
                        {l.synced_to_tally
                          ? <CheckCircle size={16} className="text-green-500 mx-auto" />
                          : <span className="text-gray-300">—</span>}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center justify-center gap-2">
                          <button onClick={() => { setEditLedger(l); setShowForm(true) }} className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition">
                            <Edit2 size={15} />
                          </button>
                          <button
                            onClick={() => syncMutation.mutate(l.id)}
                            disabled={syncMutation.isPending}
                            className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition"
                            title="Sync to Tally"
                          >
                            <RefreshCw size={15} />
                          </button>
                          <button onClick={() => { if (confirm('Delete this ledger?')) deleteMutation.mutate(l.id) }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {data?.meta && data.meta.last_page > 1 && (
              <div className="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                <p className="text-sm text-gray-500">Page {data.meta.current_page} of {data.meta.last_page}</p>
                <div className="flex gap-2">
                  <button onClick={() => setParams(p => ({ ...p, page: p.page - 1 }))} disabled={data.meta.current_page === 1} className="p-2 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"><ChevronLeft size={16} /></button>
                  <button onClick={() => setParams(p => ({ ...p, page: p.page + 1 }))} disabled={data.meta.current_page === data.meta.last_page} className="p-2 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50 transition"><ChevronRight size={16} /></button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
