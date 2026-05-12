import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import toast from 'react-hot-toast'
import { Zap, Loader2 } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'

const schema = z.object({
  company_name: z.string().min(2, 'Company name required'),
  name: z.string().min(2, 'Name required'),
  email: z.string().email('Valid email required'),
  phone: z.string().optional(),
  password: z.string().min(8, 'Minimum 8 characters'),
  password_confirmation: z.string(),
}).refine(d => d.password === d.password_confirmation, {
  message: "Passwords don't match",
  path: ['password_confirmation'],
})

function Field({ label, error, children }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  )
}

export default function Register() {
  const navigate = useNavigate()
  const { register: registerUser } = useAuth()
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm({ resolver: zodResolver(schema) })

  const onSubmit = async (data) => {
    try {
      await registerUser(data)
      toast.success('Account created successfully!')
      navigate('/dashboard')
    } catch (err) {
      const errs = err.response?.data?.errors
      if (errs) {
        Object.values(errs).flat().forEach(msg => toast.error(msg))
      } else {
        toast.error(err.response?.data?.message ?? 'Registration failed')
      }
    }
  }

  const inputCls = "w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4">
      <div className="w-full max-w-lg">
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center">
            <Zap size={24} className="text-white" />
          </div>
          <div>
            <h1 className="text-white text-2xl font-bold">TallyAuto</h1>
            <p className="text-indigo-300 text-sm">Accounting Automation</p>
          </div>
        </div>

        <div className="bg-white rounded-2xl shadow-2xl p-8">
          <div className="mb-6">
            <h2 className="text-2xl font-bold text-gray-900">Create account</h2>
            <p className="text-gray-500 text-sm mt-1">Set up your company's accounting automation</p>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Field label="Company Name" error={errors.company_name?.message}>
              <input {...register('company_name')} placeholder="Acme Pvt Ltd" className={inputCls} />
            </Field>

            <div className="grid grid-cols-2 gap-4">
              <Field label="Full Name" error={errors.name?.message}>
                <input {...register('name')} placeholder="John Doe" className={inputCls} />
              </Field>
              <Field label="Phone" error={errors.phone?.message}>
                <input {...register('phone')} placeholder="+91 99999 99999" className={inputCls} />
              </Field>
            </div>

            <Field label="Email Address" error={errors.email?.message}>
              <input {...register('email')} type="email" placeholder="john@company.com" className={inputCls} />
            </Field>

            <div className="grid grid-cols-2 gap-4">
              <Field label="Password" error={errors.password?.message}>
                <input {...register('password')} type="password" placeholder="Min 8 characters" className={inputCls} />
              </Field>
              <Field label="Confirm Password" error={errors.password_confirmation?.message}>
                <input {...register('password_confirmation')} type="password" placeholder="Repeat password" className={inputCls} />
              </Field>
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full py-3 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 disabled:opacity-60 transition flex items-center justify-center gap-2"
            >
              {isSubmitting && <Loader2 size={16} className="animate-spin" />}
              {isSubmitting ? 'Creating account...' : 'Create account'}
            </button>
          </form>

          <p className="text-center text-sm text-gray-500 mt-6">
            Already have an account?{' '}
            <Link to="/login" className="text-indigo-600 font-medium hover:underline">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  )
}
