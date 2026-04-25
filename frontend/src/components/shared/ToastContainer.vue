<script setup>
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()
</script>

<template>
  <Teleport to="body">
    <div class="toast-container" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          class="toast-item"
          :class="`is-${toast.type}`"
          role="status"
          @click="toastStore.dismiss(toast.id)"
        >
          {{ toast.message }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  pointer-events: none;
}

.toast-item {
  padding: 0.7rem 1.25rem;
  border-radius: 2rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--app-text);
  background: var(--app-surface);
  border: 1px solid var(--app-border);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
  pointer-events: auto;
  cursor: pointer;
  white-space: nowrap;
  max-width: min(90vw, 26rem);
  overflow: hidden;
  text-overflow: ellipsis;
}

.toast-item.is-success {
  color: #9ff0c7;
  background: rgba(66, 211, 146, 0.12);
  border-color: rgba(66, 211, 146, 0.25);
}

.toast-item.is-error {
  color: #ffb4ba;
  background: rgba(255, 48, 64, 0.12);
  border-color: rgba(255, 48, 64, 0.25);
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(0.75rem) scale(0.96);
}
</style>
