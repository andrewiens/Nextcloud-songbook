<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { browseFolders, connectLibrary, LibraryApiError, type FolderListing, type Library } from '../services/libraryApi'

const emit = defineEmits<{ connected: [library: Library] }>()
const listing = ref<FolderListing>()
const trail = ref<number[]>([0])
const name = ref('')
const busy = ref(false)
const error = ref('')
let request: AbortController | undefined
const canConnect = computed(() => !!listing.value && trail.value.length > 1 && name.value.trim().length > 0 && !busy.value)

async function navigate(nextTrail: number[]): Promise<void> {
  request?.abort()
  const current = request = new AbortController()
  busy.value = true
  error.value = ''
  try {
    const result = await browseFolders(nextTrail.at(-1) ?? 0, current.signal)
    if (current.signal.aborted) return
    listing.value = result
    trail.value = nextTrail
    if (!name.value) name.value = result.folder.name
  } catch {
    if (!current.signal.aborted) {
      listing.value = undefined
      error.value = t('songbook', 'Ordner konnten nicht geladen werden. Bitte prüfe deine Verbindung und Zugriffsrechte.')
    }
  } finally { if (!current.signal.aborted) busy.value = false }
}
async function connect(): Promise<void> {
  if (!canConnect.value || !listing.value) return
  request?.abort()
  const current = request = new AbortController()
  busy.value = true
  error.value = ''
  try {
    const library = await connectLibrary(name.value.trim(), listing.value.folder.id, current.signal)
    if (!current.signal.aborted) emit('connected', library)
  } catch (cause) {
    if (!current.signal.aborted) error.value = cause instanceof LibraryApiError && [403, 404].includes(cause.status)
      ? t('songbook', 'Dieser Ordner kann nicht verbunden werden. Prüfe deine Schreib- und Leserechte in Nextcloud Dateien.')
      : t('songbook', 'Die Bibliothek konnte nicht verbunden werden. Bitte lade die Bibliotheken erneut, bevor du es noch einmal versuchst.')
  } finally { if (!current.signal.aborted) busy.value = false }
}
onMounted(() => { void navigate([0]) })
onBeforeUnmount(() => request?.abort())
</script>

<template>
  <section
    class="songbook-settings"
    :aria-busy="busy"
  >
    <h2>{{ t('songbook', 'Gemeinsamen Ordner verbinden') }}</h2>
    <p>{{ t('songbook', 'Wähle den Ordner mit euren ChordPro-Dateien. Teile ihn vorher in Nextcloud Dateien mit der Band oder verwende einen Gruppenordner. Dessen Zugriffsrechte gelten auch hier.') }}</p>
    <p>{{ t('songbook', 'Es werden die Lieder direkt in diesem Ordner erfasst. Unterordner kannst du als eigene Bibliothek verbinden.') }}</p>
    <div
      v-if="error"
      class="songbook-error"
      role="alert"
    >
      {{ error }}
    </div>
    <div class="songbook-toolbar">
      <NcButton
        :disabled="busy || trail.length < 2"
        @click="navigate(trail.slice(0, -1))"
      >
        {{ t('songbook', 'Übergeordneter Ordner') }}
      </NcButton>
      <NcButton
        :disabled="busy"
        @click="navigate([0])"
      >
        {{ t('songbook', 'Meine Dateien') }}
      </NcButton>
    </div>
    <p class="songbook-folder-path">
      <strong>{{ t('songbook', 'Aktueller Ordner:') }}</strong> {{ listing?.folder.path || '/' }}
    </p>
    <p
      v-if="busy"
      role="status"
    >
      {{ t('songbook', 'Wird geladen …') }}
    </p>
    <ul
      v-else-if="listing?.folders.length"
      class="songbook-folders"
      :aria-label="t('songbook', 'Ordner')"
    >
      <li
        v-for="folder in listing.folders"
        :key="folder.id"
      >
        <NcButton @click="navigate([...trail, folder.id])">
          {{ folder.name }}
        </NcButton>
      </li>
    </ul>
    <p v-else-if="listing">
      {{ t('songbook', 'Keine weiteren Unterordner.') }}
    </p>
    <form
      class="songbook-connect-form"
      @submit.prevent="connect"
    >
      <label for="songbook-library-name">{{ t('songbook', 'Name der Bibliothek') }}</label>
      <input
        id="songbook-library-name"
        v-model="name"
        type="text"
        maxlength="100"
        required
        :disabled="busy"
      >
      <NcButton
        type="submit"
        :disabled="!canConnect"
      >
        {{ t('songbook', 'Diesen Ordner verbinden') }}
      </NcButton>
    </form>
  </section>
</template>
