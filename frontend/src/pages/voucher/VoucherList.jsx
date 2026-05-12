import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { RefreshCw, Download, Eye, CheckSquare, ChevronLeft, ChevronRight } from 'lucide-react'
import toast from 'react-hot-toast'
import { vouchersApi } from '../../api/vouchers'
import PageHeader from '../../components/common/PageHeader'
import StatusBadge from '../../components/common/StatusBadge'
import LoadingSpinner from '../../components/common/LoadingSpinner'

export default function VoucherList() {
  const [params, setParams] = useState({ page: 1, per_page: 15, type: '', status: '' })
  const [selected, setSelected] = useState([])
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['vouchers', params],
    queryFn: () => vouchersApi.list(params).then(r => r.data),
  })

  const syncMutation = useMutation({
    mutationFn: vouchersApi.syncToTally,
    onSuccess: () => { toast.success('Sync initiated!'); queryClient.invalidateQueries({ queryKey: ['vouchers'] }) },
    onError: () => toast.error('Sync failed'),
  })

  const bulkSyncMutation = useMutation({
    mutationFn: () => vouchersApi.bulkSync(selected),
    onSuccess: (res) => {
      toast.success(`${res.data.data.queued} vouchers queued for sync`)
      setSelected([])
      queryClient.invalidateQueries({ queryKey: ['vouchers'] })
    },
  })

  const downloadXml = async (id) => {
    try {
      const res = await vouchersApi.downloadXml(id)
      const url = URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = `voucher-${id}.xml`
      a.click()
      URL.revokeObjectURL(url)
    } catch { toast.error('Download failed') }
  }

  const toggleSelect = (id) => setSelected(s => s.includes(id) ? s.filter(x => x !== id) : [...s, id])
  const voucherTypes = ['', 'sales', 'purchase', 'payment', 'receipt', 'contra', 'journal']
  const syncStatuses = ['', 'pending', 'synced', 'failed']
  const fmt = (n) => `₹${Number(n ?? 0).toLocaleString('en-IN')}`

  return (
    <div>
      <PageHeader
        title="Vouchers"
        subtitle={`${data?.meta?.total ?? 0} vouchers`}
        actions={selected.length > 0 && (
          <button
            onClick={() => bulkSyncMutation.mutate()}
            disabled={bulkSyncMutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"
          >
            <CheckSquare size={16} /> Sync Selected ({selected.length})
          </button>
        )}
      />

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3">
        <select value={params.type} onChange={e => setParams(p => ({ ...p, type: e.target.value, page: 1 }))} className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          {voucherTypes.map(t => <option key={t} value={t}>{t ? t.charAt(0).toUpperCase() + t.slice(1) : 'All Types'}</option>)}
        </select>
        <select value={params.status} onChange={e => setParams(p => ({ ...p, status: e.target.value, page: 1 }))} className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          {syncStatuses.map(s => <option key={s} value={s}>{s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All Statuses'}</option>)}
        </select>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {isLoading ? <LoadingSpinner text="Loading vouchers..." /> : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50 border-b border-gray-200">
                    <th className="px-4 py-3 w-10">
                      <input type="checkbox" onChange={e => setSelected(e.target.checked ? data?.data?.map(v => v.id) ?? [] : [])} checked={selected.length === data?.data?.length && data?.data?.length > 0} className="rounded" />
                    </th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Voucher #</th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Amount</th>
                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Tally</th>
                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {data?.data?.length === 0 && (
                    <tr><td colSpan={8} className="text-center py-12 text-gray-400">No vouchers found. Process some invoices first!</td></tr>
                  )}
                  {data?.data?.map(v => (
                    <tr key={v.id} className={`hover:bg-gray-50 transition-colors ${selected.includes(v.id) ? 'bg-indigo-50' : ''}`}>
                      <td className="px-4 py-4">
                        <input type="checkbox" checked={selected.includes(v.id)} onChange={() => toggleSelect(v.id)} className="rounded" />
                      </td>
                      <td className="px-4 py-4 font-medium text-gray-900">{v.voucher_number ?? `V-${v.id}`}</td>
                      <td className="px-4 py-4">
                        <span className="capitalize px-2 py-0.5 bg-purple-50 text-purple-700 rounded text-xs font-medium">{v.voucher_type}</span>
                      </td>
                      <td className="px-4 py-4 text-gray-500">{v.voucher_date}</td>
                      <td className="px-4 py-4 text-right font-medium text-gray-900">{fmt(v.amount)}</td>
                      <td className="px-4 py-4 text-center"><StatusBadge status={v.status} /></td>
                      <td className="px-4 py-4 text-center"><StatusBadge status={v.tally_sync_status} /></td>
                      <td className="px-4 py-4">
                        <div className="flex items-center justify-center gap-1">
                          <Link to={`/vouchers/${v.id}`} className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition"><Eye size={15} /></Link>
                          {v.tally_sync_status !== 'synced' && (
                            <button onClick={() => syncMutation.mutate(v.id)} className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition" title="Sync to Tally">
                              <RefreshCw size={15} />
                            </button>
                          )}
                          <button onClick={() => downloadXml(v.id)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Download XML">
                            <Download size={15} />
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
