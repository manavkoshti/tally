import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useParams, Link } from 'react-router-dom'
import { ArrowLeft, RefreshCw, Download, CheckCircle, XCircle, Clock, FileCode } from 'lucide-react'
import toast from 'react-hot-toast'
import { vouchersApi } from '../../api/vouchers'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import StatusBadge from '../../components/common/StatusBadge'

export default function VoucherDetail() {
  const { id } = useParams()
  const queryClient = useQueryClient()

  const { data: voucher, isLoading } = useQuery({
    queryKey: ['voucher', id],
    queryFn: () => vouchersApi.get(id).then(r => r.data.data),
    refetchInterval: 5000, // auto-refresh every 5s
  })

  const syncMutation = useMutation({
    mutationFn: () => vouchersApi.syncToTally(id),
    onSuccess: () => {
      toast.success('Sync initiated!')
      queryClient.invalidateQueries({ queryKey: ['voucher', id] })
    },
    onError: () => toast.error('Sync failed - check Tally connection'),
  })

  const downloadXml = async () => {
    try {
      const res = await vouchersApi.downloadXml(id)
      const url = URL.createObjectURL(new Blob([res.data], { type: 'application/xml' }))
      const a = document.createElement('a')
      a.href = url
      a.download = `voucher-${voucher?.voucher_number ?? id}.xml`
      a.click()
      URL.revokeObjectURL(url)
    } catch { toast.error('Download failed') }
  }

  const fmt = (n) => `₹${Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`

  if (isLoading) return <LoadingSpinner text="Loading voucher..." />

  const isSynced = voucher?.tally_sync_status === 'synced'
  const isFailed = voucher?.tally_sync_status === 'failed'

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <Link to="/vouchers" className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
          <ArrowLeft size={18} />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Voucher {voucher?.voucher_number ?? `#${id}`}</h1>
          <p className="text-sm text-gray-500 capitalize">{voucher?.voucher_type} Voucher — {voucher?.voucher_date}</p>
        </div>
        <div className="ml-auto flex items-center gap-3">
          <button onClick={downloadXml} className="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            <FileCode size={15} /> Download XML
          </button>
          {!isSynced && (
            <button
              onClick={() => syncMutation.mutate()}
              disabled={syncMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-60 transition"
            >
              <RefreshCw size={15} className={syncMutation.isPending ? 'animate-spin' : ''} />
              Sync to Tally
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left - Voucher Info + Entries */}
        <div className="lg:col-span-2 space-y-6">
          {/* Voucher Summary */}
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div className="grid grid-cols-3 gap-6">
              <div>
                <p className="text-xs text-gray-500">Amount</p>
                <p className="text-2xl font-bold text-gray-900">{fmt(voucher?.amount)}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Status</p>
                <div className="mt-1"><StatusBadge status={voucher?.status} /></div>
              </div>
              <div>
                <p className="text-xs text-gray-500">Tally Sync</p>
                <div className="mt-1"><StatusBadge status={voucher?.tally_sync_status} /></div>
              </div>
            </div>
            {voucher?.narration && (
              <div className="mt-4 pt-4 border-t border-gray-100">
                <p className="text-xs text-gray-500">Narration</p>
                <p className="text-sm text-gray-700 mt-1">{voucher.narration}</p>
              </div>
            )}
            {isSynced && voucher?.tally_voucher_number && (
              <div className="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2">
                <CheckCircle size={16} className="text-green-500" />
                <div>
                  <p className="text-sm font-semibold text-green-700">Synced to Tally Prime ✓</p>
                  <p className="text-xs text-green-600">Tally Voucher: {voucher.tally_voucher_number} | {voucher.tally_synced_at}</p>
                </div>
              </div>
            )}
            {isFailed && (
              <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2">
                <XCircle size={16} className="text-red-500" />
                <div>
                  <p className="text-sm font-semibold text-red-700">Tally Sync Failed</p>
                  <p className="text-xs text-red-600">Check Tally connection and click "Sync to Tally" to retry</p>
                </div>
              </div>
            )}
          </div>

          {/* Debit/Credit Entries */}
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100">
              <h3 className="font-semibold text-gray-900">Accounting Entries (Debit / Credit)</h3>
              <p className="text-xs text-gray-500 mt-0.5">Auto-generated by Accounting Engine</p>
            </div>
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  <th className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ledger Account</th>
                  <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                  <th className="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Amount</th>
                  <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">CGST</th>
                  <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">SGST</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {voucher?.entries?.map((entry, i) => (
                  <tr key={i} className={entry.entry_type === 'debit' ? 'bg-blue-50/30' : 'bg-orange-50/30'}>
                    <td className="px-6 py-3 font-medium text-gray-900">{entry.ledger?.name}</td>
                    <td className="px-4 py-3 text-center">
                      <span className={`px-2 py-0.5 rounded text-xs font-bold uppercase ${
                        entry.entry_type === 'debit'
                          ? 'bg-blue-100 text-blue-700'
                          : 'bg-orange-100 text-orange-700'
                      }`}>
                        {entry.entry_type}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-right font-semibold text-gray-900">{fmt(entry.amount)}</td>
                    <td className="px-4 py-3 text-right text-gray-500">{entry.cgst_amount > 0 ? fmt(entry.cgst_amount) : '—'}</td>
                    <td className="px-4 py-3 text-right text-gray-500">{entry.sgst_amount > 0 ? fmt(entry.sgst_amount) : '—'}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50 border-t-2 border-gray-200">
                <tr>
                  <td colSpan={2} className="px-6 py-3 text-sm font-bold text-gray-700">Total</td>
                  <td className="px-6 py-3 text-right font-bold text-gray-900">{fmt(voucher?.amount)}</td>
                  <td colSpan={2}></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {/* Right - Tally Sync Logs */}
        <div className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100">
              <h3 className="font-semibold text-gray-900">Tally Sync History</h3>
              <p className="text-xs text-gray-400 mt-0.5">Auto-refreshes every 5s</p>
            </div>
            <div className="divide-y divide-gray-100 max-h-96 overflow-y-auto">
              {!voucher?.tally_sync_logs?.length && (
                <div className="px-5 py-8 text-center text-gray-400 text-sm">
                  <Clock size={24} className="mx-auto mb-2 opacity-40" />
                  No sync attempts yet
                </div>
              )}
              {voucher?.tally_sync_logs?.map((log, i) => (
                <div key={i} className="px-5 py-4">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      {log.status === 'success'
                        ? <CheckCircle size={15} className="text-green-500" />
                        : log.status === 'failed'
                          ? <XCircle size={15} className="text-red-500" />
                          : <Clock size={15} className="text-yellow-500" />
                      }
                      <span className={`text-xs font-semibold uppercase ${
                        log.status === 'success' ? 'text-green-600' :
                        log.status === 'failed' ? 'text-red-600' : 'text-yellow-600'
                      }`}>{log.status}</span>
                    </div>
                    <span className="text-xs text-gray-400">{new Date(log.created_at).toLocaleString('en-IN')}</span>
                  </div>
                  <p className="text-xs text-gray-500">{log.tally_host}:{log.tally_port}</p>
                  {log.error_message && (
                    <p className="text-xs text-red-500 mt-1 bg-red-50 px-2 py-1 rounded">{log.error_message}</p>
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Tally Check Guide */}
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <h4 className="font-semibold text-amber-800 text-sm mb-3">Tally Mein Entry Check Kaise Karein?</h4>
            <div className="space-y-2 text-xs text-amber-700">
              <div className="flex gap-2"><span className="font-bold">1.</span><span>Tally Prime open karo</span></div>
              <div className="flex gap-2"><span className="font-bold">2.</span><span><strong>Gateway of Tally</strong> → <strong>Display</strong> → <strong>Day Book</strong></span></div>
              <div className="flex gap-2"><span className="font-bold">3.</span><span>Date set karo voucher date ke equal</span></div>
              <div className="flex gap-2"><span className="font-bold">4.</span><span>Voucher type filter karo (Sales/Purchase)</span></div>
              <div className="flex gap-2"><span className="font-bold">5.</span><span>Voucher number <strong>{voucher?.voucher_number}</strong> dhundho</span></div>
            </div>
            <div className="mt-3 p-2 bg-amber-100 rounded text-xs text-amber-800">
              <strong>Alt Path:</strong> Gateway → Vouchers → Sales/Purchase → Date wise
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
