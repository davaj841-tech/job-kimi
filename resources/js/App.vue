<template>
  <MainLayout>
    <RouterView />
  </MainLayout>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/api'
import MainLayout from './components/layout/MainLayout.vue'

const route = useRoute()
const SESSION_KEY = 'ja_sid'

function sessionId(): string {
  let id = localStorage.getItem(SESSION_KEY)
  if (!id) {
    id = Math.random().toString(36).slice(2) + Date.now().toString(36)
    localStorage.setItem(SESSION_KEY, id)
  }
  return id
}

function track(path: string): void {
  if (!path || path.startsWith('/admin') || path.startsWith('/api')) return
  api
    .post('/page-views', {
      page_url: path,
      route_name: (route.name as string) || null,
      session_id: sessionId(),
      referrer: document.referrer || null,
    })
    .catch(() => {})
}

onMounted(() => track(route.fullPath))
watch(
  () => route.fullPath,
  (path) => track(path)
)
</script>
