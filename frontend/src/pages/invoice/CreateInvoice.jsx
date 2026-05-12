import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm, useFieldArray } from 'react-hook-form'
import toast from 'react-hot-toast'
import { Upload, Plus, Trash2, FileText, Loader2, ScanLine, PenLine, CheckCircle2, Info, X } from 'lucide-react'
import { invoicesApi } from '../../api/invoices'
import PageHeader from '../../components/common/PageHeader'

const INVOICE_TYPES = ['sales', 'purchase', 'expense', 'journal', 'payment', 'receipt']
const GST_RATES = [0, 5, 12, 18, 28]

export default function CreateInvoice() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [file, setFile] = useState(null)
  const [dragOver, setDragOver] = useState(false)
  const [mode, setMode] = useState('file') // 'file' | 'manual'
  const fileRef = useRef()

  // Single form for both modes — validation done manually in onSubmit
  const { register, control, handleSubmit, watch, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      invoice_type: 'sales',
      invoice_date: new Date().toISOString().split('T')[0],
      invoice_number: '',
      party_name: '',
      party_gstin: '',
      narration: '',
      items: [{ description: '', quantity: '1', rate: '', gst_rate: '18', hsn_sac: '', unit: 'Nos' }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })
  const items = watch('items') ?? []

  const switchMode = (newMode) => {
    setMode(newMode)
    setFile(null)
    reset({
      invoice_type: 'sales',
      invoice_date: new Date().toISOString().split('T')[0],
      invoice_number: '',
      party_name: '',
      party_gstin: '',
      narration: '',
      items: [{ description: '', quantity: '1', rate: '', gst_rate: '18', hsn_sac: '', unit: 'Nos' }],
    })
  }

  const calcTotals = () => {
    let taxable = 0, gstAmt = 0
    items.forEach(item => {
      const qty = parseFloat(item.quantity) || 0
      const rate = parseFloat(item.rate) || 0
      const gstRate = parseFloat(item.gst_rate) || 0
      const ta = qty * rate
      taxable += ta
      gstAmt += ta * gstRate / 100
    })
    return {
      taxable: taxable.toFixed(2),
      gst: gstAmt.toFixed(2),
      total: (taxable + gstAmt).toFixed(2),
    }
  }

  const totals = calcTotals()

  const mutation = useMutation({
    mutationFn: (fd) => invoicesApi.create(fd),
    onSuccess: () => {
      if (mode === 'file') {
        toast.success('Invoice uploaded! OCR processing shuru ho gaya...', { duration: 5000 })
      } else {
        toast.success('Invoice create ho gaya! Accounting processing shuru ho gaya...')
      }
      queryClient.invalidateQueries({ queryKey: ['invoices'] })
      navigate('/invoices')
    },
    onError: (err) => {
      const errs = err.response?.data?.errors
      if (errs) {
        Object.values(errs).flat().forEach(m => toast.error(m))
      } else if (err.response?.status === 503) {
        toast.error('Server offline hai. Laravel start karo: php artisan serve --port=8000')
      } else {
        toast.error(err.response?.data?.message ?? 'Invoice create karne mein error aaya')
      }
    },
  })

  const onSubmit = (data) => {
    // FILE MODE validation
    if (mode === 'file') {
      if (!file) {
        toast.error('Pehle ek file select karo (PDF/Image)')
        return
      }
    }

    // MANUAL MODE validation
    if (mode === 'manual') {
      if (!data.invoice_date) {
        toast.error('Invoice date required hai')
        return
      }
      const filledItems = items.filter(i => i.description?.trim())
      if (filledItems.length === 0) {
        toast.error('Kam se kam ek item add karo')
        return
      }
      const invalidItem = filledItems.find(i => !parseFloat(i.rate) || parseFloat(i.rate) <= 0)
      if (invalidItem) {
        toast.error(`"${invalidItem.description}" ka rate fill karo`)
        return
      }
    }

    // Build FormData
    const fd = new FormData()
    fd.append('invoice_type', data.invoice_type)

    if (data.invoice_date) fd.append('invoice_date', data.invoice_date)
    if (data.invoice_number?.trim()) fd.append('invoice_number', data.invoice_number.trim())
    if (data.party_name?.trim()) fd.append('party_name', data.party_name.trim())
    if (data.party_gstin?.trim()) fd.append('party_gstin', data.party_gstin.trim())
    if (data.narration?.trim()) fd.append('narration', data.narration.trim())

    if (mode === 'manual') {
      const filledItems = items.filter(i => i.description?.trim())
      filledItems.forEach((item, i) => {
        fd.append(`items[${i}][description]`, item.description)
        fd.append(`items[${i}][quantity]`, item.quantity || '1')
        fd.append(`items[${i}][rate]`, item.rate || '0')
        fd.append(`items[${i}][gst_rate]`, item.gst_rate || '0')
        if (item.hsn_sac?.trim()) fd.append(`items[${i}][hsn_sac]`, item.hsn_sac)
        if (item.unit?.trim()) fd.append(`items[${i}][unit]`, item.unit)
      })
    }

    if (file) fd.append('file', file)

    mutation.mutate(fd)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setDragOver(false)
    const f = e.dataTransfer.files[0]
    if (!f) return
    const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']
    if (allowed.includes(f.type)) {
      setFile(f)
    } else {
      toast.error('Sirf PDF, JPG, PNG allowed hain')
    }
  }

  const handleFileSelect = (e) => {
    const f = e.target.files?.[0]
    if (f) setFile(f)
  }

  const cls = (extra = '') =>
    `w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition ${extra}`

  return (
    <div>
      <PageHeader
        title="Invoice Banao"
        subtitle="File upload karo (OCR auto-fill) ya manually details bharo"
      />

      {/* Mode Switcher */}
      <div className="grid grid-cols-2 gap-4 mb-6">
        {[
          {
            key: 'file',
            icon: ScanLine,
            title: 'File Upload (OCR)',
            desc: 'PDF / Image → OCR automatically saari details fill karta hai',
          },
          {
            key: 'manual',
            icon: PenLine,
            title: 'Manual Entry',
            desc: 'Khud date, party, items type karo',
          },
        ].map(({ key, icon: Icon, title, desc }) => (
          <button
            key={key}
            type="button"
            onClick={() => switchMode(key)}
            className={`flex items-center gap-3 p-4 rounded-xl border-2 text-left transition ${
              mode === key
                ? 'border-indigo-600 bg-indigo-50'
                : 'border-gray-200 bg-white hover:border-indigo-300 hover:bg-gray-50'
            }`}
          >
            <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${mode === key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500'}`}>
              <Icon size={20} />
            </div>
            <div className="flex-1 min-w-0">
              <p className={`font-semibold text-sm ${mode === key ? 'text-indigo-700' : 'text-gray-700'}`}>{title}</p>
              <p className="text-xs text-gray-500 mt-0.5 truncate">{desc}</p>
            </div>
            {mode === key && <CheckCircle2 size={18} className="text-indigo-600 flex-shrink-0" />}
          </button>
        ))}
      </div>

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* ─── LEFT ─── */}
          <div className="lg:col-span-2 space-y-6">

            {/* ── FILE UPLOAD MODE ── */}
            {mode === 'file' && (
              <>
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                  <div className="flex items-center justify-between mb-1">
                    <h3 className="font-semibold text-gray-900">Invoice File Upload*</h3>
                  </div>
                  <p className="text-xs text-gray-500 mb-4">
                    OCR extract karta hai: party name, GSTIN, date, amounts, GST — sab kuch automatic
                  </p>

                  {/* Drop zone */}
                  <div
                    onDragOver={e => { e.preventDefault(); setDragOver(true) }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={handleDrop}
                    onClick={() => fileRef.current?.click()}
                    className={`border-2 border-dashed rounded-xl p-12 text-center cursor-pointer transition-all ${
                      dragOver ? 'border-indigo-500 bg-indigo-50 scale-[1.01]' :
                      file ? 'border-green-400 bg-green-50' :
                      'border-gray-300 hover:border-indigo-400 hover:bg-gray-50'
                    }`}
                  >
                    {file ? (
                      <div className="space-y-2">
                        <CheckCircle2 size={48} className="mx-auto text-green-500" />
                        <p className="text-base font-semibold text-green-700">{file.name}</p>
                        <p className="text-sm text-gray-500">{(file.size / 1024).toFixed(1)} KB</p>
                        <button
                          type="button"
                          onClick={e => { e.stopPropagation(); setFile(null) }}
                          className="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 hover:underline"
                        >
                          <X size={12} /> Remove file
                        </button>
                      </div>
                    ) : (
                      <div className="space-y-3">
                        <Upload size={48} className="mx-auto text-gray-300" />
                        <div>
                          <p className="text-base font-semibold text-gray-600">Drag & drop karo</p>
                          <p className="text-sm text-gray-400">ya click karke browse karo</p>
                        </div>
                        <p className="text-xs text-gray-400">PDF, JPG, PNG — max 10MB</p>
                      </div>
                    )}
                  </div>
                  <input
                    ref={fileRef}
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    className="hidden"
                    onChange={handleFileSelect}
                  />

                  {file && (
                    <div className="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg flex gap-3">
                      <Info size={16} className="text-blue-500 flex-shrink-0 mt-0.5" />
                      <p className="text-xs text-blue-700">
                        <strong>Auto flow:</strong> File save → OCR processing → Accounting entries generate → Tally sync<br />
                        Invoice list mein status track karo.
                      </p>
                    </div>
                  )}
                </div>

                {/* Optional fields in file mode */}
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="font-semibold text-gray-900">Basic Info</h3>
                    <span className="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">
                      Optional — OCR fill karta hai
                    </span>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Invoice Type *</label>
                      <select {...register('invoice_type')} className={cls()}>
                        {INVOICE_TYPES.map(t => (
                          <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">
                        Invoice Date <span className="text-gray-400 font-normal">(optional)</span>
                      </label>
                      <input {...register('invoice_date')} type="date" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">
                        Party Name <span className="text-gray-400 font-normal">(optional)</span>
                      </label>
                      <input {...register('party_name')} placeholder="OCR detect karega" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">
                        GSTIN <span className="text-gray-400 font-normal">(optional)</span>
                      </label>
                      <input {...register('party_gstin')} placeholder="OCR detect karega" className={cls()} />
                    </div>
                  </div>
                </div>
              </>
            )}

            {/* ── MANUAL MODE ── */}
            {mode === 'manual' && (
              <>
                {/* Invoice Details */}
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                  <h3 className="font-semibold text-gray-900 mb-4">Invoice Details</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Invoice Type *</label>
                      <select {...register('invoice_type')} className={cls()}>
                        {INVOICE_TYPES.map(t => (
                          <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Invoice Date *</label>
                      <input {...register('invoice_date')} type="date" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Invoice Number</label>
                      <input {...register('invoice_number')} placeholder="INV-001" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Party Name</label>
                      <input {...register('party_name')} placeholder="Customer / Vendor ka naam" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">GSTIN</label>
                      <input {...register('party_gstin')} placeholder="27AABCD1234E1Z5" className={cls()} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Narration</label>
                      <input {...register('narration')} placeholder="Optional note" className={cls()} />
                    </div>
                  </div>
                </div>

                {/* Line Items */}
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <h3 className="font-semibold text-gray-900">Line Items *</h3>
                      <p className="text-xs text-gray-400 mt-0.5">Kam se kam ek item required hai</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => append({ description: '', quantity: '1', rate: '', gst_rate: '18', hsn_sac: '', unit: 'Nos' })}
                      className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-100 transition"
                    >
                      <Plus size={15} /> Add Item
                    </button>
                  </div>

                  <div className="space-y-3">
                    {fields.map((field, i) => {
                      const qty = parseFloat(items[i]?.quantity) || 0
                      const rate = parseFloat(items[i]?.rate) || 0
                      const gstPct = parseFloat(items[i]?.gst_rate) || 0
                      const ta = qty * rate
                      const gstAmt = ta * gstPct / 100
                      return (
                        <div key={field.id} className="border border-gray-200 rounded-lg p-4 bg-gray-50/60">
                          <div className="grid grid-cols-12 gap-3 mb-3">
                            {/* Description */}
                            <div className="col-span-5">
                              <label className="text-xs text-gray-500 block mb-1">Description *</label>
                              <input
                                {...register(`items.${i}.description`)}
                                placeholder="Item name"
                                className={cls()}
                              />
                            </div>
                            {/* HSN */}
                            <div className="col-span-2">
                              <label className="text-xs text-gray-500 block mb-1">HSN/SAC</label>
                              <input {...register(`items.${i}.hsn_sac`)} placeholder="9988" className={cls()} />
                            </div>
                            {/* Unit */}
                            <div className="col-span-2">
                              <label className="text-xs text-gray-500 block mb-1">Unit</label>
                              <input {...register(`items.${i}.unit`)} placeholder="Nos" className={cls()} />
                            </div>
                            {/* Delete */}
                            <div className="col-span-1 flex items-end justify-end">
                              {fields.length > 1 && (
                                <button
                                  type="button"
                                  onClick={() => remove(i)}
                                  className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                >
                                  <Trash2 size={15} />
                                </button>
                              )}
                            </div>
                          </div>

                          <div className="grid grid-cols-12 gap-3 items-end">
                            <div className="col-span-2">
                              <label className="text-xs text-gray-500 block mb-1">Quantity</label>
                              <input
                                {...register(`items.${i}.quantity`)}
                                type="number"
                                min="0.001"
                                step="0.001"
                                placeholder="1"
                                className={cls()}
                              />
                            </div>
                            <div className="col-span-3">
                              <label className="text-xs text-gray-500 block mb-1">Rate (₹) *</label>
                              <input
                                {...register(`items.${i}.rate`)}
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                className={cls()}
                              />
                            </div>
                            <div className="col-span-2">
                              <label className="text-xs text-gray-500 block mb-1">GST %</label>
                              <select {...register(`items.${i}.gst_rate`)} className={cls()}>
                                {GST_RATES.map(r => (
                                  <option key={r} value={r}>{r}%</option>
                                ))}
                              </select>
                            </div>
                            <div className="col-span-5">
                              <div className="flex gap-3 text-sm bg-white border border-gray-200 rounded-lg px-3 py-2">
                                <span className="text-gray-500">Tax: <strong className="text-gray-700">₹{gstAmt.toFixed(2)}</strong></span>
                                <span className="text-gray-300">|</span>
                                <span className="text-gray-700 font-semibold">Total: ₹{(ta + gstAmt).toFixed(2)}</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      )
                    })}
                  </div>

                  {/* Grand Totals */}
                  <div className="mt-5 pt-4 border-t border-gray-200 flex justify-end">
                    <div className="w-64 space-y-1.5">
                      <div className="flex justify-between text-sm text-gray-600">
                        <span>Taxable Amount</span><span>₹{totals.taxable}</span>
                      </div>
                      <div className="flex justify-between text-sm text-gray-600">
                        <span>Total GST</span><span>₹{totals.gst}</span>
                      </div>
                      <div className="flex justify-between text-base font-bold text-gray-900 pt-1.5 border-t border-gray-300 mt-1">
                        <span>Grand Total</span><span>₹{totals.total}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>

          {/* ─── RIGHT PANEL ─── */}
          <div className="space-y-5">
            {/* Submit */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
              <h3 className="font-semibold text-gray-900">Submit</h3>

              {/* Status summary */}
              <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                {mode === 'file' ? (
                  <>
                    <div className="flex items-center gap-2 text-sm">
                      <div className={`w-2 h-2 rounded-full flex-shrink-0 ${file ? 'bg-green-500' : 'bg-gray-300'}`} />
                      {file
                        ? <span className="text-green-700 font-medium truncate">{file.name}</span>
                        : <span className="text-gray-400">File select nahi ki</span>
                      }
                    </div>
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <div className="w-2 h-2 rounded-full bg-indigo-400" />
                      OCR → Accounting → Tally — sab automatic
                    </div>
                  </>
                ) : (
                  <>
                    <div className="flex justify-between text-sm text-gray-700">
                      <span>Items</span>
                      <strong>{items.filter(i => i.description?.trim()).length}</strong>
                    </div>
                    <div className="flex justify-between text-sm font-semibold text-gray-900">
                      <span>Total</span>
                      <span>₹{totals.total}</span>
                    </div>
                  </>
                )}
              </div>

              <button
                type="submit"
                disabled={mutation.isPending || (mode === 'file' && !file)}
                className="w-full py-3 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-2"
              >
                {mutation.isPending && <Loader2 size={16} className="animate-spin" />}
                {mutation.isPending
                  ? 'Processing...'
                  : mode === 'file'
                    ? (file ? 'Upload & Process' : 'Pehle file select karo')
                    : 'Invoice Banao'
                }
              </button>

              <button
                type="button"
                onClick={() => navigate('/invoices')}
                className="w-full py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition"
              >
                Cancel
              </button>
            </div>

            {/* Steps */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
              <p className="text-sm font-semibold text-gray-900 mb-3">
                {mode === 'file' ? 'OCR Flow' : 'Manual Flow'}
              </p>
              <div className="space-y-2.5">
                {(mode === 'file'
                  ? ['File Upload (PDF/Image)', 'OCR → Data Extract', 'Accounting Entries Auto', 'Tally Sync']
                  : ['Details + Items Enter Karo', 'GST Auto-Calculate', 'Accounting Entries', 'Tally Sync']
                ).map((step, i) => (
                  <div key={i} className="flex items-center gap-3">
                    <div className="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                      {i + 1}
                    </div>
                    <p className="text-xs text-gray-600">{step}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  )
}
