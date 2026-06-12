import { murmurHash3 } from './murmurHash'

const getCanvasFingerprint = () => {
  try {
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    ctx.textBaseline = 'top'
    ctx.font = '14px Arial'
    ctx.fillStyle = '#f60'
    ctx.fillRect(125, 1, 62, 20)
    ctx.fillStyle = '#069'
    ctx.fillText('fingerprint', 2, 15)
    return canvas.toDataURL()
  } catch (e) {
    return ''
  }
}

const getWebGLFingerprint = () => {
  try {
    const canvas = document.createElement('canvas')
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl')
    if (!gl) return ''

    const debugInfo = gl.getExtension('WEBGL_debug_renderer_info')
    const renderer = gl.getParameter(debugInfo ? debugInfo.UNMASKED_RENDERER_WEBGL : 0x1f01)
    const vendor = gl.getParameter(debugInfo ? debugInfo.UNMASKED_VENDOR_WEBGL : 0x1f00)

    return `${vendor}~${renderer}`
  } catch (e) {
    return ''
  }
}

const getScreenInfo = () => {
  return `${screen.width}x${screen.height}x${screen.colorDepth}~${screen.pixelDepth}~${window.devicePixelRatio || 1}`
}

const getTimezone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone
  } catch (e) {
    return new Date().getTimezoneOffset().toString()
  }
}

const getLanguages = () => {
  return [navigator.language, ...(navigator.languages || [])].join(',')
}

const getPlatformInfo = () => {
  return `${navigator.platform}~${navigator.userAgent}`
}

const getFonts = () => {
  const baseFonts = ['Arial', 'Times New Roman', 'Courier New']
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  const results = []

  for (const font of baseFonts) {
    ctx.font = `72px "${font}"`
    const width = ctx.measureText('mmmmmmmmmwwwwwwwww').width
    results.push(`${font}:${width}`)
  }

  return results.join('~')
}

const generateFingerprint = async () => {
  const components = [
    getCanvasFingerprint(),
    getWebGLFingerprint(),
    getScreenInfo(),
    getTimezone(),
    getLanguages(),
    getPlatformInfo(),
    getFonts(),
    navigator.hardwareConcurrency || '',
    navigator.maxTouchPoints || '',
    navigator.doNotTrack || '',
  ]

  const raw = components.filter(Boolean).join('|')
  const hash = await murmurHash3(raw, 0x9747b28c)

  return hash.toString(16).padStart(16, '0')
}

const FINGERPRINT_KEY = 'device_fingerprint'

export const getDeviceFingerprint = async () => {
  let fingerprint = localStorage.getItem(FINGERPRINT_KEY)

  if (!fingerprint) {
    fingerprint = await generateFingerprint()
    localStorage.setItem(FINGERPRINT_KEY, fingerprint)
  }

  return fingerprint
}

export const getSessionId = () => {
  let sessionId = sessionStorage.getItem('exam_session_id')
  if (!sessionId) {
    sessionId = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2)
    sessionStorage.setItem('exam_session_id', sessionId)
  }
  return sessionId
}

export const clearSessionId = () => {
  sessionStorage.removeItem('exam_session_id')
}
