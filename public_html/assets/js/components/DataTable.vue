<template>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            :class="[
              'px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500',
              column.sortable ? 'cursor-pointer hover:bg-gray-100' : '',
            ]"
            @click="column.sortable ? sortBy(column.key) : null"
          >
            <div class="flex items-center gap-2">
              {{ column.label }}
              <span v-if="column.sortable && sortKey === column.key">
                <svg
                  v-if="sortDirection === 'asc'"
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 15l7-7 7 7"
                  />
                </svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                  />
                </svg>
              </span>
            </div>
          </th>
          <th
            v-if="$slots.actions"
            class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
          >
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 bg-white">
        <tr v-if="isLoading">
          <td
            :colspan="columns.length + ($slots.actions ? 1 : 0)"
            class="px-6 py-4 text-center text-gray-500"
          >
            Loading...
          </td>
        </tr>
        <tr v-else-if="!data.length">
          <td
            :colspan="columns.length + ($slots.actions ? 1 : 0)"
            class="px-6 py-4 text-center text-gray-500"
          >
            {{ emptyMessage }}
          </td>
        </tr>
        <tr
          v-for="(item, index) in paginatedData"
          :key="item.id || index"
          class="transition-colors hover:bg-gray-50"
        >
          <td
            v-for="column in columns"
            :key="column.key"
            class="whitespace-nowrap px-6 py-4 text-sm text-gray-900"
          >
            <slot
              v-if="$slots[`cell-${column.key}`]"
              :name="`cell-${column.key}`"
              :record="item"
              :value="getItemValue(item, column.key)"
            />
            <span v-else>{{ formatValue(getItemValue(item, column.key), column) }}</span>
          </td>
          <td
            v-if="$slots.actions"
            class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
          >
            <slot name="actions" :record="item" />
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div
      v-if="showPagination && totalPages > 1"
      class="flex items-center justify-between border-t bg-white px-4 py-3"
    >
      <div class="flex flex-1 justify-between sm:hidden">
        <button
          :disabled="currentPage === 1"
          @click="currentPage--"
          class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Previous
        </button>
        <button
          :disabled="currentPage === totalPages"
          @click="currentPage++"
          class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Next
        </button>
      </div>
      <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-700">
            Showing
            <span class="font-medium">{{ startIndex + 1 }}</span>
            to
            <span class="font-medium">{{ Math.min(endIndex, totalItems) }}</span>
            of
            <span class="font-medium">{{ totalItems }}</span>
            results
          </p>
        </div>
        <div>
          <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
            <button
              :disabled="currentPage === 1"
              @click="currentPage--"
              class="relative inline-flex items-center rounded-l-md border border-gray-300 bg-white px-2 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Previous
            </button>
            <template v-for="page in visiblePages" :key="page">
              <button
                v-if="typeof page === 'number'"
                @click="currentPage = page"
                :class="[
                  'relative inline-flex items-center border px-4 py-2 text-sm font-medium',
                  currentPage === page
                    ? 'z-10 border-primary-500 bg-primary-50 text-primary-600'
                    : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50',
                ]"
              >
                {{ page }}
              </button>
              <span
                v-else
                class="relative inline-flex items-center border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700"
              >
                {{ page }}
              </span>
            </template>
            <button
              :disabled="currentPage === totalPages"
              @click="currentPage++"
              class="relative inline-flex items-center rounded-r-md border border-gray-300 bg-white px-2 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
              Next
            </button>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  data: {
    type: Array,
    default: () => [],
  },
  isLoading: Boolean,
  emptyMessage: {
    type: String,
    default: 'No data available',
  },
  itemsPerPage: {
    type: Number,
    default: 15,
  },
  showPagination: {
    type: Boolean,
    default: true,
  },
  sortable: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['sort'])

const currentPage = ref(1)
const sortKey = ref(null)
const sortDirection = ref('asc')

const totalItems = computed(() => props.data.length)
const totalPages = computed(() => Math.ceil(totalItems.value / props.itemsPerPage))

const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * props.itemsPerPage
  const end = start + props.itemsPerPage
  return sortedData.value.slice(start, end)
})

const startIndex = computed(() => (currentPage.value - 1) * props.itemsPerPage)
const endIndex = computed(() => startIndex.value + props.itemsPerPage)

const sortedData = computed(() => {
  if (!sortKey.value || !props.sortable) return props.data

  return [...props.data].sort((a, b) => {
    const aValue = getItemValue(a, sortKey.value)
    const bValue = getItemValue(b, sortKey.value)

    if (aValue === bValue) return 0
    const comparison = aValue < bValue ? -1 : 1
    return sortDirection.value === 'asc' ? comparison : -comparison
  })
})

const visiblePages = computed(() => {
  const pages = []
  const maxVisible = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let endPage = Math.min(totalPages.value, startPage + maxVisible - 1)

  if (endPage - startPage + 1 < maxVisible) {
    startPage = Math.max(1, endPage - maxVisible + 1)
  }

  if (startPage > 1) {
    pages.push(1)
    if (startPage > 2) pages.push('...')
  }

  for (let i = startPage; i <= endPage; i++) {
    pages.push(i)
  }

  if (endPage < totalPages.value) {
    if (endPage < totalPages.value - 1) pages.push('...')
    pages.push(totalPages.value)
  }

  return pages
})

const sortBy = key => {
  if (!props.sortable) return

  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDirection.value = 'asc'
  }

  emit('sort', { key, direction: sortDirection.value })
}

const getItemValue = (item, key) => {
  return key.split('.').reduce((obj, k) => obj?.[k], item)
}

const formatValue = (value, column) => {
  if (column.format && typeof column.format === 'function') {
    return column.format(value)
  }
  return value
}

// Reset to page 1 when data changes
watch(
  () => props.data.length,
  () => {
    currentPage.value = 1
  }
)
</script>
