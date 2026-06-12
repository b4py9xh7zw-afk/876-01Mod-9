import { ref, watch, onUnmounted } from 'vue'

const getStorageKey = (examRecordId, suffix) => {
  return `exam_${examRecordId}_${suffix}`
}

export const useExamLocalStorage = (examRecordId) => {
  const answersKey = ref(getStorageKey(examRecordId.value, 'answers'))
  const statusKey = ref(getStorageKey(examRecordId.value, 'status'))
  const disconnectionKey = ref(getStorageKey(examRecordId.value, 'disconnection'))

  const answers = ref({})
  const questionStatus = ref({})
  const lastSavedAt = ref(null)

  watch(examRecordId, (newId) => {
    if (newId) {
      answersKey.value = getStorageKey(newId, 'answers')
      statusKey.value = getStorageKey(newId, 'status')
      disconnectionKey.value = getStorageKey(newId, 'disconnection')
      loadFromStorage()
    }
  }, { immediate: true })

  const loadFromStorage = () => {
    try {
      const savedAnswers = localStorage.getItem(answersKey.value)
      if (savedAnswers) {
        answers.value = JSON.parse(savedAnswers)
      }

      const savedStatus = localStorage.getItem(statusKey.value)
      if (savedStatus) {
        questionStatus.value = JSON.parse(savedStatus)
      }

      const savedTime = localStorage.getItem(getStorageKey(examRecordId.value, 'last_saved'))
      if (savedTime) {
        lastSavedAt.value = parseInt(savedTime, 10)
      }
    } catch (e) {
      console.error('Failed to load from localStorage:', e)
    }
  }

  const saveAnswers = () => {
    try {
      localStorage.setItem(answersKey.value, JSON.stringify(answers.value))
      localStorage.setItem(statusKey.value, JSON.stringify(questionStatus.value))
      lastSavedAt.value = Date.now()
      localStorage.setItem(
        getStorageKey(examRecordId.value, 'last_saved'),
        lastSavedAt.value.toString()
      )
    } catch (e) {
      console.error('Failed to save to localStorage:', e)
    }
  }

  const recordDisconnectionEvent = (eventType, data = {}) => {
    try {
      const event = {
        type: eventType,
        timestamp: Date.now(),
        localTime: new Date().toISOString(),
        ...data,
      }

      const key = disconnectionKey.value
      const existing = localStorage.getItem(key)
      const events = existing ? JSON.parse(existing) : []
      events.push(event)

      if (events.length > 50) {
        events.splice(0, events.length - 50)
      }

      localStorage.setItem(key, JSON.stringify(events))
    } catch (e) {
      console.error('Failed to record disconnection event:', e)
    }
  }

  const getDisconnectionEvents = () => {
    try {
      const existing = localStorage.getItem(disconnectionKey.value)
      return existing ? JSON.parse(existing) : []
    } catch (e) {
      return []
    }
  }

  const getLastDisconnectionInfo = () => {
    const events = getDisconnectionEvents()
    const disconnects = events.filter(e => e.type === 'disconnected')
    const lastDisconnect = disconnects[disconnects.length - 1]

    if (lastDisconnect) {
      const reconnects = events.filter(
        e => e.type === 'reconnected' && e.timestamp > lastDisconnect.timestamp
      )
      const lastReconnect = reconnects[0]

      return {
        disconnectedAt: lastDisconnect.timestamp,
        reconnectedAt: lastReconnect?.timestamp || null,
        durationSeconds: lastReconnect
          ? Math.floor((lastReconnect.timestamp - lastDisconnect.timestamp) / 1000)
          : Math.floor((Date.now() - lastDisconnect.timestamp) / 1000),
        answersAtDisconnect: lastDisconnect.answers || null,
      }
    }

    return null
  }

  const setAnswer = (questionId, answer) => {
    answers.value[questionId] = answer
    questionStatus.value[questionId] = {
      answered: true,
      updatedAt: Date.now(),
    }
    saveAnswers()
  }

  const clearStorage = () => {
    try {
      localStorage.removeItem(answersKey.value)
      localStorage.removeItem(statusKey.value)
      localStorage.removeItem(disconnectionKey.value)
      localStorage.removeItem(getStorageKey(examRecordId.value, 'last_saved'))
      answers.value = {}
      questionStatus.value = {}
      lastSavedAt.value = null
    } catch (e) {
      console.error('Failed to clear localStorage:', e)
    }
  }

  const hasUnsavedAnswers = () => {
    return Object.keys(answers.value).length > 0
  }

  const getAnswersForSync = () => {
    return Object.entries(answers.value).map(([questionId, answer]) => ({
      question_id: parseInt(questionId, 10),
      answer: answer,
    }))
  }

  let autoSaveTimer = null
  const startAutoSave = (intervalSeconds = 5) => {
    autoSaveTimer = setInterval(() => {
      saveAnswers()
    }, intervalSeconds * 1000)
  }

  const stopAutoSave = () => {
    if (autoSaveTimer) {
      clearInterval(autoSaveTimer)
      autoSaveTimer = null
    }
  }

  onUnmounted(() => {
    stopAutoSave()
  })

  return {
    answers,
    questionStatus,
    lastSavedAt,
    loadFromStorage,
    saveAnswers,
    setAnswer,
    clearStorage,
    hasUnsavedAnswers,
    getAnswersForSync,
    recordDisconnectionEvent,
    getDisconnectionEvents,
    getLastDisconnectionInfo,
    startAutoSave,
    stopAutoSave,
  }
}
