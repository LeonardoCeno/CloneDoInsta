<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    default: '',
  },
  username: {
    type: String,
    default: '',
  },
  avatarUrl: {
    type: String,
    default: '',
  },
  colors: {
    type: Array,
    default: () => [],
  },
  size: {
    type: String,
    default: 'md',
  },
})

const sizeMap = {
  sm: '2.2rem',
  md: '2.85rem',
  lg: '5.25rem',
  xl: '8rem',
}

const initials = computed(() => {
  const source = props.name || props.username

  return (
    source
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0])
      .join('')
      .toUpperCase() || '@'
  )
})

const avatarStyle = computed(() => {
  const [start = '#f05a28', end = '#ff9f59'] = props.colors ?? []
  const size = sizeMap[props.size] ?? props.size

  return {
    '--avatar-size': size,
    background: `linear-gradient(135deg, ${start} 0%, ${end} 100%)`,
  }
})
</script>

<template>
  <span class="profile-avatar" :style="avatarStyle" aria-hidden="true">
    <img v-if="avatarUrl" :src="avatarUrl" alt="" />
    <span v-else>{{ initials }}</span>
  </span>
</template>

<style scoped>
.profile-avatar {
  display: grid;
  place-items: center;
  width: var(--avatar-size);
  height: var(--avatar-size);
  overflow: hidden;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #fff;
  font-weight: 800;
  letter-spacing: 0.05em;
  flex-shrink: 0;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.profile-avatar img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
