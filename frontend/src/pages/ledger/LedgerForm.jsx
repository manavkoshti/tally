import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { X, Loader2 } from 'lucide-react'
import { ledgersApi } from '../../api/ledgers'

export default function LedgerForm({ ledger, onClose, onSave }) {
  const isEdit = !!ledger
  const { register, handleSubmit, reset, formState: { errors } } = useForm()

  useEffect(() => {
    if (ledger) reset(ledger)
    else reset({})
  }, [ledger, reset])

  const mutation = useMutation({
    mutationFn: (data) => isEdit ? ledgersApi.update(ledger.id, data) : ledgersApi.create(data),
    onSuccess: () => {
      toast.success(isEdit ? 'Ledger updated' : 'Ledger created')
      onSave()
    },
    onError: (err) => {
      const errs = err.response?.data?.errors
      if (errs) Object.values(errs).flat().forEach(m => toast.error(m))
      else toast.error('Operation failed')
    },
  })

  const inputCls = "w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-screen overflow-y-auto">
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 className="text-lg font-semibold text-gray-900">{isEdit ? 'Edit Ledger' : 'Create Ledger'}</h2>
          <button onClick={onClose} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"><X size={18} /></button>
        </div>

        <form onSubmit={handleSubmit(d => mutation.mutate(d))} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Ledger Name *</label>
            <input {...register('name', { required: 'Required' })} className={inputCls} placeholder="e.g. Reliance Industries" />
            {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name.message}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Type *</label>
              <select {...register('type', { required: true })} className={inputCls}>
                {['debtor', 'creditor', 'bank', 'cash', 'income', 'expense', 'gst', 'other'].map(t => (
                  <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Alias</label>
              <input {...register('alias')} className={inputCls} placeholder="Short name" />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
              <input {...register('gstin')} className={inputCls} placeholder="27AABCD1234E1Z5" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">PAN</label>
              <input {...register('pan')} className={inputCls} placeholder="AABCD1234E" />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
              <input {...register('opening_balance')} type="number" step="0.01" className={inputCls} placeholder="0.00" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Balance Type</label>
              <select {...register('opening_balance_type')} className={inputCls}>
                <option value="debit">Debit</option>
                <option value="credit">Credit</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea {...register('address')} rows={2} className={inputCls} placeholder="Full address..." />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">State</label>
              <input {...register('state')} className={inputCls} placeholder="Maharashtra" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
              <input {...register('phone')} className={inputCls} placeholder="+91 99999 99999" />
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <button
              type="submit"
              disabled={mutation.isPending}
              className="flex-1 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 disabled:opacity-60 transition flex items-center justify-center gap-2"
            >
              {mutation.isPending && <Loader2 size={15} className="animate-spin" />}
              {isEdit ? 'Update Ledger' : 'Create Ledger'}
            </button>
            <button type="button" onClick={onClose} className="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50 transition">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
