<template>
  <div class="dashboard p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-gray-600">Welcome back, {{ authStore.userName }}</p>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="rounded-lg border-l-4 bg-base-200 p-6 shadow"
        :class="stat.borderColor"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600">{{ stat.label }}</p>
            <p class="mt-1 text-2xl font-bold" :class="stat.textColor">{{ stat.value }}</p>
          </div>
          <i :class="stat.icon" class="h-8 w-8 text-3xl"></i>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="rounded-lg bg-base-200 p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold text-gray-900">Recent Activity</h2>
      <p class="text-gray-500">Loading recent activity...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores'

const authStore = useAuthStore()

const stats = ref([
  {
    label: 'Active Vehicles',
    value: '24',
    icon: 'bi bi-truck text-blue-500',
    textColor: 'text-blue-600',
    iconColor: 'text-blue-500',
    borderColor: 'border-blue-500',
  },
  {
    label: "Today's Trips",
    value: '12',
    icon: 'bi bi-map text-green-500',
    textColor: 'text-green-600',
    iconColor: 'text-green-500',
    borderColor: 'border-green-500',
  },
  {
    label: 'Pending Requests',
    value: '5',
    icon: 'bi bi-clock text-yellow-500',
    textColor: 'text-yellow-600',
    iconColor: 'text-yellow-500',
    borderColor: 'border-yellow-500',
  },
  {
    label: 'Active Drivers',
    value: '18',
    icon: 'bi bi-person text-purple-500',
    textColor: 'text-purple-600',
    iconColor: 'text-purple-500',
    borderColor: 'border-purple-500',
  },
])

onMounted(() => {
  // Fetch dashboard data
})
</script>
