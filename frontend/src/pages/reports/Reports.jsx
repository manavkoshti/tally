import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '../../api/reports'
import PageHeader from '../../components/common/PageHeader'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import StatusBadge from '../../components/common/StatusBadge'

const tabs = ['Sales', 'Purchase', 'GST', 'Tally Failed', 'Audit']

export default function Reports() {
  const [activeTab, setActiveTab] = useState('Sales')
  const [dateRange, setDateRange] = useState({ from: '', to: '' })
  const [gstPeriod, setGstPeriod] = useState({ month: new Date().getMonth() + 1, year: new Date().getFullYear() })

  const salesQ = useQuery({ queryKey: ['report-sales', dateRange], queryFn: () => reportsApi.sales(dateRange).then(r => r.data.data), enabled: activeTab === 'Sales' })
  const purchaseQ = useQuery({ queryKey: ['report-purchase', dateRange], queryFn: () => reportsApi.purchase(dateRange).then(r => r.data.data), enabled: activeTab === 'Purchase' })
  const gstQ = useQuery({ queryKey: ['report-gst', gstPeriod], queryFn: () => reportsApi.gst(gstPeriod).then(r => r.data.data), enabled: activeTab === 'GST' })
  const failedQ = useQuery({ queryKey: ['report-failed'], queryFn: () => reportsApi.tallyFailed().then(r => r.data), enabled: activeTab === 'Tally Failed' })
  const auditQ = useQuery({ queryKey: ['report-audit'], queryFn: () => reportsApi.audit().then(r => r.data), enabled: activeTab === 'Audit' })

  const fmt = (n) => `₹${Number(n ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`

  return (
    <div>
      <PageHeader title="Reports" subtitle="Financial reports and analytics" />

      {/* Tabs */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
        <div className="flex border-b border-gray-100 px-2">
          {tabs.map(tab => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-5 py-4 text-sm font-medium transition-colors border-b-2 -mb-px ${
                activeTab === tab
                  ? 'border-indigo-600 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {tab}
            </button>
          ))}
        </div>

        <div className="p-4">
          {/* Date filters */}
          {['Sales', 'Purchase'].includes(activeTab) && (
            <div className="flex gap-3 flex-wrap">
              <div>
                <label className="text-xs text-gray-500 block mb-1">From Date</label>
                <input type="date" value={dateRange.from} onChange={e => setDateRange(d => ({ ...d, from: e.target.value }))} className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label className="text-xs text-gray-500 block mb-1">To Date</label>
                <input type="date" value={dateRange.to} onChange={e => setDateRange(d => ({ ...d, to: e.target.value }))} className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          )}
          {activeTab === 'GST' && (
            <div className="flex gap-3">
              <div>
                <label className="text-xs text-gray-500 block mb-1">Month</label>
                <select value={gstPeriod.month} onChange={e => setGstPeriod(p => ({ ...p, month: e.target.value }))} className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  {Array.from({ length: 12 }, (_, i) => <option key={i + 1} value={i + 1}>{new Date(2000, i).toLocaleString('en', { month: 'long' })}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-500 block mb-1">Year</label>
                <select value={gstPeriod.year} onChange={e => setGstPeriod(p => ({ ...p, year: e.target.value }))} className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  {[2024, 2025, 2026].map(y => <option key={y} value={y}>{y}</option>)}
                </select>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Sales Report */}
      {activeTab === 'Sales' && (
        salesQ.isLoading ? <LoadingSpinner text="Loading sales report..." /> : (
          <div className="space-y-6">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {[
                { label: 'Total Sales', value: fmt(salesQ.data?.summary?.total_amount), color: 'indigo' },
                { label: 'Taxable Amount', value: fmt(salesQ.data?.summary?.total_taxable), color: 'blue' },
                { label: 'Total GST', value: fmt(salesQ.data?.summary?.total_gst), color: 'violet' },
                { label: 'Invoices Count', value: salesQ.data?.summary?.count ?? 0, color: 'green' },
              ].map(s => (
                <div key={s.label} className="bg-white rounded-xl border border-gray-200 p-4">
                  <p className="text-xs text-gray-500 mb-1">{s.label}</p>
                  <p className="text-xl font-bold text-gray-900">{s.value}</p>
                </div>
              ))}
            </div>
            <ReportTable
              columns={['Invoice #', 'Party', 'Date', 'Taxable', 'GST', 'Total', 'Tally']}
              data={salesQ.data?.invoices ?? []}
              renderRow={row => [
                row.invoice_number ?? `INV-${row.id}`,
                row.party_name ?? '—',
                row.invoice_date,
                fmt(row.taxable_amount),
                fmt(row.total_gst_amount),
                fmt(row.total_amount),
                <StatusBadge key="s" status={row.tally_sync_status} />,
              ]}
            />
          </div>
        )
      )}

      {/* Purchase Report */}
      {activeTab === 'Purchase' && (
        purchaseQ.isLoading ? <LoadingSpinner text="Loading purchase report..." /> : (
          <ReportTable
            columns={['Invoice #', 'Party', 'Date', 'Taxable', 'GST', 'Total', 'Tally']}
            data={purchaseQ.data?.invoices ?? []}
            renderRow={row => [
              row.invoice_number ?? `INV-${row.id}`,
              row.party_name ?? '—',
              row.invoice_date,
              fmt(row.taxable_amount),
              fmt(row.total_gst_amount),
              fmt(row.total_amount),
              <StatusBadge key="s" status={row.tally_sync_status} />,
            ]}
          />
        )
      )}

      {/* GST Report */}
      {activeTab === 'GST' && (
        gstQ.isLoading ? <LoadingSpinner text="Loading GST report..." /> : (
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  {['Invoice Type', 'Taxable Amount', 'CGST', 'SGST', 'IGST', 'Total GST'].map(h => (
                    <th key={h} className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {(gstQ.data ?? []).map(row => (
                  <tr key={row.invoice_type} className="hover:bg-gray-50">
                    <td className="px-6 py-4 capitalize font-medium text-gray-900">{row.invoice_type}</td>
                    <td className="px-6 py-4">{fmt(row.taxable_amount)}</td>
                    <td className="px-6 py-4">{fmt(row.cgst)}</td>
                    <td className="px-6 py-4">{fmt(row.sgst)}</td>
                    <td className="px-6 py-4">{fmt(row.igst)}</td>
                    <td className="px-6 py-4 font-semibold">{fmt(row.total_gst)}</td>
                  </tr>
                ))}
                {(!gstQ.data?.length) && <tr><td colSpan={6} className="text-center py-8 text-gray-400">No GST data for selected period</td></tr>}
              </tbody>
            </table>
          </div>
        )
      )}

      {/* Tally Failed */}
      {activeTab === 'Tally Failed' && (
        failedQ.isLoading ? <LoadingSpinner text="Loading failed syncs..." /> : (
          <ReportTable
            columns={['Voucher #', 'Type', 'Error', 'Date']}
            data={failedQ.data?.data ?? []}
            renderRow={row => [
              row.voucher?.voucher_number ?? `V-${row.voucher_id}`,
              <StatusBadge key="t" status="failed" />,
              <span key="e" className="text-red-600 text-xs">{row.error_message ?? 'Tally offline'}</span>,
              row.created_at,
            ]}
          />
        )
      )}

      {/* Audit */}
      {activeTab === 'Audit' && (
        auditQ.isLoading ? <LoadingSpinner text="Loading audit logs..." /> : (
          <ReportTable
            columns={['User', 'Module', 'Action', 'Description', 'Date']}
            data={auditQ.data?.data ?? []}
            renderRow={row => [
              row.user?.name ?? 'System',
              row.module,
              <span key="a" className="uppercase text-xs font-semibold text-indigo-700">{row.action}</span>,
              row.description ?? '—',
              row.created_at,
            ]}
          />
        )
      )}
    </div>
  )
}

function ReportTable({ columns, data, renderRow }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 border-b border-gray-200">
              {columns.map(col => (
                <th key={col} className="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">{col}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {data.length === 0 && (
              <tr><td colSpan={columns.length} className="text-center py-12 text-gray-400">No data found</td></tr>
            )}
            {data.map((row, i) => (
              <tr key={i} className="hover:bg-gray-50 transition-colors">
                {renderRow(row).map((cell, j) => (
                  <td key={j} className="px-6 py-4 text-gray-700">{cell}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
