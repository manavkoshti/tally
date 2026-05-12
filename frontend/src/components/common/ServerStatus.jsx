import { useState, useEffect } from 'react'
import { Wifi, WifiOff } from 'lucide-react'
import api from '../../api/axios'

export default function ServerStatus() {
  const [online, setOnline] = useState(true)
  const [checking, setChecking] = useState(false)

  const check = async () => {
    try {
      await api.get('/auth/profile')
      setOnline(true)
    } catch (err) {
      if (err.response?.status === 503 || !err.response) {
        setOnline(false)
      } else {
        // 401, 422 etc = server is up, just auth issue
        setOnline(true)
      }
    }
  }

  useEffect(() => {
    check()
    const interval = setInterval(check, 15000)
    return () => clearInterval(interval)
  }, [])

  if (online) return null

  return (
    <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-full shadow-lg text-sm font-medium animate-bounce">
      <WifiOff size={16} />
      Backend server offline — Start Laravel server
    </div>
  )
}
