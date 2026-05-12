const statusConfig = {
  // Tally sync
  pending: { label: 'Pending', cls: 'bg-yellow-100 text-yellow-700' },
  synced: { label: 'Synced', cls: 'bg-green-100 text-green-700' },
  failed: { label: 'Failed', cls: 'bg-red-100 text-red-700' },
  // OCR / accounting
  processing: { label: 'Processing', cls: 'bg-blue-100 text-blue-700' },
  completed: { label: 'Completed', cls: 'bg-green-100 text-green-700' },
  // Voucher status
  draft: { label: 'Draft', cls: 'bg-gray-100 text-gray-600' },
  approved: { label: 'Approved', cls: 'bg-green-100 text-green-700' },
  cancelled: { label: 'Cancelled', cls: 'bg-red-100 text-red-700' },
}

export default function StatusBadge({ status }) {
  const config = statusConfig[status] ?? { label: status, cls: 'bg-gray-100 text-gray-600' }
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.cls}`}>
      {config.label}
    </span>
  )
}
