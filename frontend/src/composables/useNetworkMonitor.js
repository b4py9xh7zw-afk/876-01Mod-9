import { ref, onMounted, onUnmounted } from 'vue'

export const useNetworkMonitor = () => {
  const isOnline = ref(navigator.onLine)
  const isOffline = ref(!navigator.onLine)
  const offlineStartTime = ref(null)
  const lastOfflineDuration = ref(0)
  const offlineHistory = ref([])

  const handleOnline = () => {
    isOnline.value = true
    isOffline.value = false

    if (offlineStartTime.value) {
      const duration = Math.floor((Date.now() - offlineStartTime.value) / 1000)
      lastOfflineDuration.value = duration

      offlineHistory.value.push({
        startedAt: offlineStartTime.value,
        endedAt: Date.now(),
        durationSeconds: duration,
      })

      if (offlineHistory.value.length > 10) {
        offlineHistory.value = offlineHistory.value.slice(-10)
      }

      offlineStartTime.value = null
    }
  }

  const handleOffline = () => {
    isOnline.value = false
    isOffline.value = true
    offlineStartTime.value = Date.now()
  }

  onMounted(() => {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
  })

  onUnmounted(() => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  })

  const getTotalOfflineDuration = () => {
    const historyDuration = offlineHistory.value.reduce((sum, item) => sum + item.durationSeconds, 0)
    const currentDuration = offlineStartTime.value
      ? Math.floor((Date.now() - offlineStartTime.value) / 1000)
      : 0
    return historyDuration + currentDuration
  }

  return {
    isOnline,
    isOffline,
    offlineStartTime,
    lastOfflineDuration,
    offlineHistory,
    getTotalOfflineDuration,
  }
}
