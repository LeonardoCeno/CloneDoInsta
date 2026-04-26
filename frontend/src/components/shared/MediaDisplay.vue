<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: '' },
  isVideo: { type: Boolean, default: false },
  thumbnail: { type: Boolean, default: false },
  autoplay: { type: Boolean, default: false },
  loop: { type: Boolean, default: false },
  muted: { type: Boolean, default: true },
  controls: { type: Boolean, default: false },
})

const videoEl = ref(null)

onMounted(() => {
  if (videoEl.value) videoEl.value.muted = props.muted
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
  background: #000;
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
