<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
withDefaults(defineProps<{ kind: 'songs' | 'setlists' | 'error'; size?: number }>(), { size: 24 })
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <template v-if="kind === 'songs'">
      <path d="M9 18V5l11-2v13M9 9l11-2" />
      <ellipse
        cx="6"
        cy="18"
        rx="3"
        ry="2.5"
      />
      <ellipse
        cx="17"
        cy="16"
        rx="3"
        ry="2.5"
      />
    </template>
    <template v-else-if="kind === 'setlists'">
      <path d="M9 5h12M9 12h12M9 19h12M3 5h1M3 12h1M3 19h1" />
    </template>
    <template v-else>
      <circle
        cx="12"
        cy="12"
        r="9"
      />
      <path d="M12 7v6M12 17h.01" />
    </template>
  </svg>
</template>
