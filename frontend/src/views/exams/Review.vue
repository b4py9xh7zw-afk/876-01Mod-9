<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">断网审核管理</h1>
      <button @click="loadData" :disabled="loading" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 disabled:opacity-50">
        {{ loading ? '刷新中...' : '刷新' }}
      </button>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      </div>

      <div v-else-if="records.length === 0" class="text-center py-12 text-gray-500">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p>暂无待审核的申请</p>
      </div>

      <div v-else class="divide-y divide-gray-200">
        <div v-for="record in records" :key="record.id" class="p-6 hover:bg-gray-50">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-medium text-gray-900">
                {{ record.user?.name }} - {{ record.exam_paper?.title }}
              </h3>
              <p class="text-sm text-gray-500 mt-1">
                考试开始时间: {{ formatDateTime(record.start_time) }}
              </p>
            </div>
            <span class="bg-red-100 text-red-800 text-sm px-2.5 py-0.5 rounded">
              待审核
            </span>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-gray-50 rounded p-3">
              <p class="text-sm text-gray-500">断网次数</p>
              <p class="text-xl font-semibold text-gray-900">{{ record.disconnection_count }} 次</p>
            </div>
            <div class="bg-gray-50 rounded p-3">
              <p class="text-sm text-gray-500">累计断网</p>
              <p class="text-xl font-semibold" :class="isOverLimit(record.total_disconnection_seconds) ? 'text-red-600' : 'text-gray-900'">
                {{ formatDuration(record.total_disconnection_seconds) }}
              </p>
            </div>
            <div class="bg-gray-50 rounded p-3">
              <p class="text-sm text-gray-500">允许最大时长</p>
              <p class="text-xl font-semibold text-gray-900">{{ formatDuration(maxAllowedSeconds) }}</p>
            </div>
            <div class="bg-gray-50 rounded p-3">
              <p class="text-sm text-gray-500">超时</p>
              <p class="text-xl font-semibold text-red-600">
                {{ formatDuration(Math.max(0, record.total_disconnection_seconds - maxAllowedSeconds)) }}
              </p>
            </div>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-4">
            <p class="text-sm text-yellow-800 whitespace-pre-wrap">{{ record.review_note }}</p>
          </div>

          <div v-if="expandedRecordId === record.id" class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">网络事件日志</h4>
            <div class="bg-gray-50 rounded p-4 max-h-60 overflow-y-auto">
              <div v-if="loadingEvents" class="text-center py-4">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600 mx-auto"></div>
              </div>
              <div v-else-if="networkEvents.length === 0" class="text-center text-gray-500 py-4">
                暂无事件记录
              </div>
              <div v-else class="space-y-2">
                <div v-for="event in networkEvents" :key="event.id" class="flex items-start text-sm">
                  <span class="w-4 h-4 rounded-full mr-3 mt-0.5"
                        :class="getEventColor(event.event_type)"></span>
                  <div class="flex-1">
                    <p class="text-gray-900">
                      {{ getEventLabel(event.event_type) }}
                      <span class="text-gray-500 ml-2">{{ formatDateTime(event.event_at) }}</span>
                    </p>
                    <p v-if="event.duration_seconds > 0" class="text-gray-500">
                      持续: {{ formatDuration(event.duration_seconds) }}
                    </p>
                    <p v-if="event.ip_address" class="text-gray-500">
                      IP: {{ event.ip_address }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <button @click="toggleEvents(record.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
              {{ expandedRecordId === record.id ? '收起事件' : '查看网络事件' }}
            </button>

            <div class="flex space-x-3">
              <button @click="openReviewModal(record, 'reject')" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                拒绝申请
              </button>
              <button @click="openReviewModal(record, 'approve')" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                同意延时
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showReviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">
          {{ reviewAction === 'approve' ? '同意延时' : '拒绝申请' }}
        </h3>

        <div v-if="reviewAction === 'approve'" class="space-y-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">延时时长（分钟）</label>
            <input type="number" v-model.number="extensionMinutes" min="1" max="120" class="w-full border border-gray-300 rounded px-3 py-2">
            <p class="text-sm text-gray-500 mt-1">
              建议: 可延时 {{ Math.ceil(selectedRecord?.total_disconnection_seconds / 60) }} 分钟（与累计断网时长相当）
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">审核备注</label>
            <textarea v-model="reviewNote" rows="2" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="请填写审核意见"></textarea>
          </div>
        </div>

        <div v-else class="space-y-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">拒绝原因</label>
            <textarea v-model="reviewNote" rows="3" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="请说明拒绝原因"></textarea>
          </div>
        </div>

        <div class="flex justify-end space-x-3">
          <button @click="showReviewModal = false" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">取消</button>
          <button @click="submitReview" :disabled="submitting" class="px-4 py-2 rounded text-white disabled:opacity-50"
                  :class="reviewAction === 'approve' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700'">
            {{ submitting ? '提交中...' : (reviewAction === 'approve' ? '确认同意' : '确认拒绝') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'
import { useModal } from '../../composables/useModal'

const { success, error } = useToast()
const { confirm } = useModal()

const records = ref([])
const loading = ref(false)
const loadingEvents = ref(false)
const maxAllowedSeconds = ref(300)
const expandedRecordId = ref(null)
const networkEvents = ref([])

const showReviewModal = ref(false)
const reviewAction = ref('approve')
const selectedRecord = ref(null)
const extensionMinutes = ref(5)
const reviewNote = ref('')
const submitting = ref(false)

onMounted(() => {
  loadData()
})

const loadData = async () => {
  loading.value = true
  try {
    const response = await api.get('/exams/reviews/pending')
    records.value = response.data.records.data || response.data.records
    maxAllowedSeconds.value = response.data.max_allowed_seconds || 300
  } catch (e) {
    error(e.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const loadEvents = async (recordId) => {
  loadingEvents.value = true
  try {
    const response = await api.get(`/exams/records/${recordId}/events`)
    networkEvents.value = response.data.events.data || response.data.events
  } catch (e) {
    error(e.response?.data?.message || '加载事件失败')
  } finally {
    loadingEvents.value = false
  }
}

const toggleEvents = async (recordId) => {
  if (expandedRecordId.value === recordId) {
    expandedRecordId.value = null
    networkEvents.value = []
  } else {
    expandedRecordId.value = recordId
    await loadEvents(recordId)
  }
}

const openReviewModal = (record, action) => {
  selectedRecord.value = record
  reviewAction.value = action
  extensionMinutes.value = Math.max(1, Math.ceil(record.total_disconnection_seconds / 60))
  reviewNote.value = ''
  showReviewModal.value = true
}

const submitReview = async () => {
  if (submitting.value) return

  if (reviewAction.value === 'approve' && (!extensionMinutes.value || extensionMinutes.value < 1)) {
    error('请输入有效的延时时长')
    return
  }
  if (!reviewNote.value.trim()) {
    error('请填写审核备注')
    return
  }

  const confirmed = await confirm(
    `确定要${reviewAction.value === 'approve' ? '同意延时' : '拒绝申请'}吗？`,
    '确认审核'
  )
  if (!confirmed) return

  submitting.value = true
  try {
    await api.post(`/exams/reviews/${selectedRecord.value.id}`, {
      action: reviewAction.value,
      extension_minutes: reviewAction.value === 'approve' ? extensionMinutes.value : 0,
      review_note: reviewNote.value,
    })

    success(`审核${reviewAction.value === 'approve' ? '通过' : '拒绝'}成功`)
    showReviewModal.value = false
    await loadData()
  } catch (e) {
    error(e.response?.data?.message || '审核失败')
  } finally {
    submitting.value = false
  }
}

const isOverLimit = (seconds) => {
  return seconds > maxAllowedSeconds.value
}

const formatDuration = (seconds) => {
  if (!seconds || seconds < 60) return `${seconds || 0}秒`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return secs > 0 ? `${mins}分${secs}秒` : `${mins}分钟`
}

const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}:${String(date.getSeconds()).padStart(2, '0')}`
}

const getEventColor = (type) => {
  const colors = {
    disconnected: 'bg-red-500',
    reconnected: 'bg-green-500',
    page_refresh: 'bg-yellow-500',
    device_change: 'bg-orange-500',
    heartbeat: 'bg-blue-500',
  }
  return colors[type] || 'bg-gray-500'
}

const getEventLabel = (type) => {
  const labels = {
    disconnected: '网络断开',
    reconnected: '网络恢复',
    page_refresh: '页面刷新',
    device_change: '设备变更',
    heartbeat: '心跳',
  }
  return labels[type] || type
}
</script>
