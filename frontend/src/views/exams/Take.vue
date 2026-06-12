<template>
  <div class="space-y-6">
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4" v-if="showNetworkWarning">
      <div class="flex items-center">
        <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
          <p class="font-medium text-yellow-800">{{ networkWarningMessage }}</p>
          <p class="text-sm text-yellow-700" v-if="lastOfflineDuration > 0">
            本次断网时长: {{ formatDuration(lastOfflineDuration) }} | 累计断网: {{ formatDuration(totalDisconnectionSeconds) }}
          </p>
        </div>
      </div>
    </div>

    <div class="bg-red-50 border border-red-200 rounded-lg p-4" v-if="needsReview">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="font-medium text-red-800">累计断网时间过长，需要监考老师审核</p>
            <p class="text-sm text-red-700">{{ reviewNote }}</p>
          </div>
        </div>
        <button @click="showExtensionModal = true" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
          申请延时
        </button>
      </div>
    </div>

    <div v-if="suspiciousIssues.length > 0" class="bg-orange-50 border border-orange-200 rounded-lg p-4">
      <div class="flex items-center">
        <svg class="w-6 h-6 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="font-medium text-orange-800">检测到异常行为</p>
          <ul class="text-sm text-orange-700 mt-1">
            <li v-for="(issue, idx) in suspiciousIssues" :key="idx">{{ issue.message }}</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">{{ examPaper?.title }}</h1>
      <div class="flex items-center space-x-4">
        <div class="flex items-center" :class="isOnline ? 'text-green-600' : 'text-red-600'">
          <span class="w-2 h-2 rounded-full mr-2" :class="isOnline ? 'bg-green-500' : 'bg-red-500 animate-pulse'"></span>
          <span class="text-sm">{{ isOnline ? '在线' : '离线' }}</span>
        </div>
        <div class="text-lg">
          剩余时间: <span class="font-mono font-bold" :class="{'text-red-600': timeRemaining < 60}">{{ formatTime(timeRemaining) }}</span>
        </div>
        <div class="text-sm text-gray-500" v-if="extensionMinutes > 0">
          已延时: +{{ extensionMinutes }}分钟
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      <p class="mt-2 text-gray-600">{{ loadingMessage }}</p>
    </div>

    <div v-else-if="!loading && questions.length > 0" class="space-y-8">
      <div v-for="(question, index) in questions" :key="question.id" class="bg-white rounded-lg shadow p-6"
           :class="{'border-2 border-yellow-400': isQuestionMarked(question.id)}">
        <div class="flex items-start mb-4">
          <div class="flex items-center mr-3">
            <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded mr-2">{{ index + 1 }}</span>
            <button @click="toggleQuestionMark(question.id)" class="text-gray-400 hover:text-yellow-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      :d="isQuestionMarked(question.id) ? 'M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z' : 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'" />
              </svg>
            </button>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ question.title }}</h3>
            <p class="text-sm text-gray-500 mb-3">分值: {{ question.score }}分 | 题型: {{ questionTypeLabel(question.type) }}
              <span v-if="getQuestionStatus(question.id)" class="ml-2 text-green-600">✓ 已作答</span>
            </p>
            <div class="space-y-2">
              <template v-if="question.type === 'single_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === key}">
                  <input type="radio" :name="'question_' + question.id" :value="key" @change="onAnswerChange(question.id, key)" :checked="answers[question.id] === key" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else-if="question.type === 'true_false'">
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'true'}">
                  <input type="radio" :name="'question_' + question.id" value="true" @change="onAnswerChange(question.id, 'true')" :checked="answers[question.id] === 'true'" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">正确</span>
                </label>
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'false'}">
                  <input type="radio" :name="'question_' + question.id" value="false" @change="onAnswerChange(question.id, 'false')" :checked="answers[question.id] === 'false'" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">错误</span>
                </label>
              </template>
              <template v-else-if="question.type === 'multiple_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': (answers[question.id] || []).includes(key)}">
                  <input type="checkbox" :value="key" @change="toggleMultipleChoice(question.id, key)" :checked="(answers[question.id] || []).includes(key)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else>
                <textarea :value="answers[question.id]" @input="onAnswerChange(question.id, $event.target.value)" rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="请输入答案"></textarea>
              </template>
            </div>
          </div>
        </div>
      </div>
      <div class="flex justify-between items-center">
        <div class="text-sm text-gray-500">
          <span v-if="lastSavedAt">本地保存于 {{ formatSaveTime(lastSavedAt) }}</span>
          <span class="mx-2">|</span>
          <span>已答 {{ answeredCount }}/{{ questions.length }} 题</span>
        </div>
        <div class="flex space-x-3">
          <button @click="syncToServer" :disabled="syncing || !isOnline" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600 disabled:opacity-50">
            {{ syncing ? '同步中...' : '同步到服务器' }}
          </button>
          <router-link to="/exams" class="bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">返回</router-link>
          <button @click="submitExam" :disabled="submitting" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ submitting ? '提交中...' : '提交答卷' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showExtensionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">申请考试延时</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">申请延时时长（分钟）</label>
            <input type="number" v-model.number="requestedMinutes" min="1" max="60" class="w-full border border-gray-300 rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">申请理由</label>
            <textarea v-model="extensionReason" rows="3" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="请简要说明断网情况和申请延时的原因"></textarea>
          </div>
          <div class="text-sm text-gray-500">
            <p>累计断网: {{ formatDuration(totalDisconnectionSeconds) }}</p>
            <p>断网次数: {{ disconnectionCount }} 次</p>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button @click="showExtensionModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
          <button @click="submitExtensionRequest" :disabled="submittingExtension" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ submittingExtension ? '提交中...' : '提交申请' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import { useToast } from '../../composables/useToast'
import { useNetworkMonitor } from '../../composables/useNetworkMonitor'
import { useExamLocalStorage } from '../../composables/useExamLocalStorage'
import { getDeviceFingerprint, getSessionId } from '../../utils/deviceFingerprint'

const route = useRoute()
const router = useRouter()
const { alert, confirm } = useModal()
const { success, error } = useToast()

const { isOnline, isOffline, offlineStartTime, lastOfflineDuration } = useNetworkMonitor()

const examPaper = ref(null)
const examRecordId = ref(null)
const examRecord = ref(null)
const questions = ref([])
const answers = ref({})
const markedQuestions = ref({})
const loading = ref(true)
const loadingMessage = ref('加载考试信息...')
const submitting = ref(false)
const syncing = ref(false)
const timeRemaining = ref(0)
const deviceFingerprint = ref('')
const sessionId = ref('')

const extensionMinutes = ref(0)
const needsReview = ref(false)
const reviewNote = ref('')
const suspiciousIssues = ref([])
const totalDisconnectionSeconds = ref(0)
const disconnectionCount = ref(0)
const showExtensionModal = ref(false)
const requestedMinutes = ref(5)
const extensionReason = ref('')
const submittingExtension = ref(false)

const {
  answers: storedAnswers,
  lastSavedAt,
  setAnswer,
  saveAnswers,
  clearStorage,
  getAnswersForSync,
  recordDisconnectionEvent,
  getLastDisconnectionInfo,
  startAutoSave,
  stopAutoSave,
} = useExamLocalStorage(examRecordId)

watch(storedAnswers, (newVal) => {
  if (Object.keys(newVal).length > 0) {
    answers.value = { ...newVal }
  }
}, { deep: true, immediate: true })

watch(answers, (newVal) => {
  if (examRecordId.value) {
    for (const [key, val] of Object.entries(newVal)) {
      if (storedAnswers.value[key] !== val) {
        setAnswer(key, val)
      }
    }
  }
}, { deep: true })

const showNetworkWarning = computed(() => {
  return isOffline.value || (lastOfflineDuration.value > 0 && Date.now() - offlineStartTime.value < 10000)
})

const networkWarningMessage = computed(() => {
  if (isOffline.value) {
    return '网络已断开，答案将保存在本地。请尽快恢复网络连接，以免影响考试。'
  }
  return '网络已恢复，正在同步数据...'
})

const answeredCount = computed(() => {
  return Object.values(answers.value).filter(v => v !== undefined && v !== null && v !== '' && (Array.isArray(v) ? v.length > 0 : true)).length
})

let timer = null
let heartbeatTimer = null

onMounted(async () => {
  try {
    deviceFingerprint.value = await getDeviceFingerprint()
    sessionId.value = getSessionId()
    loadingMessage.value = '正在进入考试...'

    const response = await api.post(`/exams/${route.params.id}/start`, {
      device_fingerprint: deviceFingerprint.value,
    })

    examPaper.value = response.data.exam_paper
    examRecord.value = response.data.exam_record
    examRecordId.value = response.data.exam_record.id
    questions.value = response.data.questions
    timeRemaining.value = response.data.remaining_time
    extensionMinutes.value = response.data.extension_minutes || 0
    needsReview.value = response.data.needs_review || false
    suspiciousIssues.value = response.data.suspicious_issues || []

    if (response.data.exam_record) {
      totalDisconnectionSeconds.value = response.data.exam_record.total_disconnection_seconds || 0
      disconnectionCount.value = response.data.exam_record.disconnection_count || 0
      reviewNote.value = response.data.exam_record.review_note || ''
    }

    if (suspiciousIssues.value.length > 0) {
      const issues = suspiciousIssues.value.map(i => i.message).join('、')
      alert(`检测到异常: ${issues}`, '系统提示', 'warning')
    }

    startAutoSave(5)
    startTimer()
    startHeartbeat()
    setupNetworkListeners()

  } catch (e) {
    const message = e.response?.data?.message || '考试加载失败'
    alert(message, '错误', 'error')
    router.push('/exams')
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (heartbeatTimer) clearInterval(heartbeatTimer)
  stopAutoSave()
  removeNetworkListeners()
})

const setupNetworkListeners = () => {
  window.addEventListener('online', handleOnline)
  window.addEventListener('offline', handleOffline)
}

const removeNetworkListeners = () => {
  window.removeEventListener('online', handleOnline)
  window.removeEventListener('offline', handleOffline)
}

const handleOffline = () => {
  if (!examRecordId.value) return

  recordDisconnectionEvent('disconnected', {
    answers: { ...answers.value },
    timeRemaining: timeRemaining.value,
  })
}

const handleOnline = async () => {
  if (!examRecordId.value) return

  const disconnectionInfo = getLastDisconnectionInfo()

  recordDisconnectionEvent('reconnected', {
    durationSeconds: disconnectionInfo?.durationSeconds || 0,
  })

  try {
    const response = await api.post(`/exams/${route.params.id}/disconnection`, {
      exam_record_id: examRecordId.value,
      device_fingerprint: deviceFingerprint.value,
      event_type: 'reconnected',
      disconnection_started_at: disconnectionInfo?.disconnectedAt,
      disconnection_ended_at: Date.now(),
      duration_seconds: disconnectionInfo?.durationSeconds || 0,
      local_answers: getAnswersForSync(),
      client_time: Date.now(),
    })

    timeRemaining.value = response.data.remaining_time
    needsReview.value = response.data.needs_review
    extensionMinutes.value = response.data.extension_minutes
    totalDisconnectionSeconds.value = response.data.total_disconnection_seconds
    disconnectionCount.value = response.data.disconnection_count

    if (response.data.needs_review) {
      reviewNote.value = response.data.exam_record.review_note
    }

    if (getAnswersForSync().length > 0) {
      await syncToServer()
    }

    success('网络已恢复，数据同步完成')
  } catch (e) {
    error('重连数据同步失败，请稍后重试')
  }
}

const startTimer = () => {
  timer = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--
    } else {
      clearInterval(timer)
      submitExam()
    }
  }, 1000)
}

const startHeartbeat = () => {
  heartbeatTimer = setInterval(async () => {
    if (!examRecordId.value || !isOnline.value) return

    try {
      const response = await api.post(`/exams/${route.params.id}/heartbeat`, {
        exam_record_id: examRecordId.value,
        device_fingerprint: deviceFingerprint.value,
        is_offline: isOffline.value,
        local_time: Date.now(),
      })

      timeRemaining.value = response.data.remaining_time
      needsReview.value = response.data.needs_review
      extensionMinutes.value = response.data.extension_minutes
      totalDisconnectionSeconds.value = response.data.total_disconnection_seconds

      if (response.data.suspicious_issues?.length > 0) {
        suspiciousIssues.value = response.data.suspicious_issues
      }
    } catch (e) {
    }
  }, 30000)
}

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const formatDuration = (seconds) => {
  if (seconds < 60) return `${seconds}秒`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return secs > 0 ? `${mins}分${secs}秒` : `${mins}分钟`
}

const formatSaveTime = (timestamp) => {
  const date = new Date(timestamp)
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}:${date.getSeconds().toString().padStart(2, '0')}`
}

const questionTypeLabel = (type) => {
  const labels = {
    single_choice: '单选题',
    multiple_choice: '多选题',
    true_false: '判断题',
    fill_blank: '填空题',
    essay: '问答题'
  }
  return labels[type] || type
}

const getQuestionStatus = (questionId) => {
  const answer = answers.value[questionId]
  if (answer === undefined || answer === null || answer === '') return false
  if (Array.isArray(answer)) return answer.length > 0
  return true
}

const isQuestionMarked = (questionId) => {
  return !!markedQuestions.value[questionId]
}

const toggleQuestionMark = (questionId) => {
  markedQuestions.value[questionId] = !markedQuestions.value[questionId]
}

const onAnswerChange = (questionId, value) => {
  answers.value[questionId] = value
  if (examRecordId.value) {
    setAnswer(questionId, value)
  }
}

const toggleMultipleChoice = (questionId, key) => {
  if (!answers.value[questionId]) {
    answers.value[questionId] = []
  }
  const index = answers.value[questionId].indexOf(key)
  if (index === -1) {
    answers.value[questionId].push(key)
  } else {
    answers.value[questionId].splice(index, 1)
  }
  if (examRecordId.value) {
    setAnswer(questionId, [...answers.value[questionId]])
  }
}

const syncToServer = async () => {
  if (syncing.value || !isOnline.value || !examRecordId.value) return

  const answersForSync = getAnswersForSync()
  if (answersForSync.length === 0) {
    success('没有需要同步的答案')
    return
  }

  syncing.value = true
  try {
    await api.post(`/exams/${route.params.id}/sync-answers`, {
      exam_record_id: examRecordId.value,
      device_fingerprint: deviceFingerprint.value,
      answers: answersForSync,
      sync_time: Date.now(),
    })
    success('答案同步成功')
  } catch (e) {
    error(e.response?.data?.message || '同步失败')
  } finally {
    syncing.value = false
  }
}

const submitExtensionRequest = async () => {
  if (submittingExtension.value) return

  if (!requestedMinutes.value || requestedMinutes.value < 1) {
    error('请输入有效的延时时长')
    return
  }
  if (!extensionReason.value.trim()) {
    error('请填写申请理由')
    return
  }

  submittingExtension.value = true
  try {
    await api.post(`/exams/${route.params.id}/request-extension`, {
      exam_record_id: examRecordId.value,
      device_fingerprint: deviceFingerprint.value,
      reason: extensionReason.value,
      requested_minutes: requestedMinutes.value,
    })
    success('延时申请已提交，请等待监考老师审核')
    showExtensionModal.value = false
  } catch (e) {
    error(e.response?.data?.message || '提交失败')
  } finally {
    submittingExtension.value = false
  }
}

const submitExam = async () => {
  if (submitting.value) return

  const confirmed = await confirm('确定要提交答卷吗？提交后将无法修改答案。', '确认提交')
  if (!confirmed) return

  if (isOffline.value) {
    const offlineConfirmed = await confirm(
      '当前处于离线状态，提交可能失败。是否继续？答案已保存到本地，联网后可再次提交。',
      '离线提交'
    )
    if (!offlineConfirmed) return
  }

  submitting.value = true
  try {
    const answerData = Object.entries(answers.value).map(([questionId, answer]) => ({
      question_id: parseInt(questionId),
      answer: Array.isArray(answer) ? answer.join(',') : answer
    }))

    const response = await api.post(`/exams/${route.params.id}/submit`, {
      exam_record_id: examRecordId.value,
      answers: answerData
    })

    clearStorage()
    alert(`考试完成！得分: ${response.data.score}`, '考试完成', 'success')
    router.push('/records')
  } catch (e) {
    if (isOffline.value) {
      alert('当前网络不可用，答案已保存到本地。请恢复网络后重新提交。', '提交失败', 'warning')
    } else {
      alert(e.response?.data?.message || '提交失败', '提交失败', 'error')
    }
  } finally {
    submitting.value = false
  }
}
</script>
