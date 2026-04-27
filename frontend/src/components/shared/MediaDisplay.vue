<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: '' },
  isVideo: { type: Boolean, default: false },
  thumbnail: { type: Boolean, default: false },
  autoplay: { type: Boolean, default: false },
  loop: { type: Boolean, default: false },
  muted: { type: Boolean, default: true },
  controls: { type: Boolean, default: false },
  autoplayThreshold: { type: Number, default: 0.2 },
  pauseThreshold: { type: Number, default: null },
})

const emit = defineEmits(['ended'])

const videoEl = ref(null)
let observer = null
let observerPausing = false
let userPaused = false

function onVideoPause() {
  if (!observerPausing) userPaused = true
}

function onVideoPlay() {
  userPaused = false
}

onMounted(() => {
  if (!videoEl.value) return
  videoEl.value.muted = props.muted
  videoEl.value.volume = 0.5

  if (props.autoplay) {
    videoEl.value.addEventListener('pause', onVideoPause)
    videoEl.value.addEventListener('play', onVideoPlay)

    const playAt = props.autoplayThreshold
    const pauseAt = props.pauseThreshold ?? props.autoplayThreshold
    const thresholds = pauseAt !== playAt ? [pauseAt, playAt] : [playAt]

    observer = new IntersectionObserver(
      ([entry]) => {
        const ratio = entry.intersectionRatio
        if (ratio >= playAt) {
          if (!userPaused) videoEl.value?.play().catch(() => {})
        } else if (ratio < pauseAt) {
          observerPausing = true
          videoEl.value?.pause()
          observerPausing = false
        }
        // between pauseAt and playAt: do nothing, keep current state
      },
      { threshold: thresholds },
    )
    observer.observe(videoEl.value)
  }
})

onUnmounted(() => {
  observer?.disconnect()
  videoEl.value?.removeEventListener('pause', onVideoPause)
  videoEl.value?.removeEventListener('play', onVideoPlay)
})

watch(() => props.muted, (val) => {
  if (videoEl.value) videoEl.value.muted = val
})
</script>

<template>
  <div class="media-wrap" :class="{ 'media-wrap--video': isVideo }">
    <video
      v-if="isVideo"
      ref="videoEl"
      :src="src"
      :autoplay="autoplay"
      :loop="loop"
      :controls="controls && !thumbnail"
      playsinline
      preload="metadata"
      class="media-wrap__el"
      @ended="emit('ended')"
    />
    <img
      v-else
      :src="src"
      :alt="alt"
      class="media-wrap__el"
    />
    <span v-if="isVideo && thumbnail" class="media-wrap__play" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
        <path d="M8 5.14v14l11-7-11-7z" />
      </svg>
    </span>
  </div>
</template>

<style scoped>
.media-wrap {
  position: relative;
  width: 100%;
  height: 100%;
  background: #111;
}

.media-wrap__el {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.media-wrap__play {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  pointer-events: none;
}
</style>
