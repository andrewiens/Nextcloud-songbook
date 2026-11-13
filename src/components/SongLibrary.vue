<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { importSong, listSongs, openSong, LibraryApiError, type Library, type Song, type SongContent, type SongSort } from '../services/libraryApi'
import { decodeChordPro, ImportValidationError, MAX_SONG_BYTES } from '../services/chordProImport'

const props = defineProps<{ library: Library }>()
const emit = defineEmits<{ unavailable: [] }>()
const search = ref('')
const sort = ref<SongSort>('title')
const songs = ref<Song[]>([])
const warnings = ref<string[]>([])
const selected = ref<SongContent>()
const loading = ref(false)
const opening = ref(false)
const importing = ref(false)
const error = ref('')
const notice = ref('')
let listRequest: AbortController | undefined
let songRequest: AbortController | undefined
let importRequest: AbortController | undefined
let debounce: ReturnType<typeof setTimeout> | undefined

function message(cause: unknown): string {
  if (cause instanceof LibraryApiError) {
    if ([403, 404].includes(cause.status)) return t('songbook', 'Der Zugriff ist nicht mehr möglich. Prüfe die Freigabe in Nextcloud Dateien und lade die Bibliotheken neu.')
    if (cause.status === 409) return t('songbook', 'Eine Datei mit diesem Namen existiert bereits. Benenne die Importdatei um; vorhandene Lieder werden nicht überschrieben.')
    if (cause.status === 413) return t('songbook', 'Die Datei oder der Ordner ist zu groß. Erlaubt sind höchstens 256 KiB pro Lied und 1000 Einträge im Bibliotheksordner.')
    if ([400, 422].includes(cause.status)) return t('songbook', 'Die Datei konnte nicht verarbeitet werden. Verwende eine gültige ChordPro-Datei mit UTF-8-Kodierung.')
  }
  return t('songbook', 'Die Anfrage ist fehlgeschlagen. Prüfe deine Verbindung und lade erneut.')
}
function clearSong(): void { songRequest?.abort(); selected.value = undefined; opening.value = false }
async function load(): Promise<void> {
  listRequest?.abort()
  clearSong()
  const current = listRequest = new AbortController()
  loading.value = true
  songs.value = []
  warnings.value = []
  error.value = ''
  try {
    const result = await listSongs(props.library.id, search.value, sort.value, current.signal)
    if (current.signal.aborted) return
    songs.value = result.songs
    warnings.value = result.warnings
  } catch (cause) {
    if (!current.signal.aborted) {
      error.value = message(cause)
      if (cause instanceof LibraryApiError && [403, 404].includes(cause.status)) emit('unavailable')
    }
  } finally { if (!current.signal.aborted) loading.value = false }
}
async function selectSong(id: number): Promise<void> {
  clearSong()
  const current = songRequest = new AbortController()
  opening.value = true
  error.value = ''
  try {
    const song = await openSong(id, current.signal)
    if (!current.signal.aborted) selected.value = song
  } catch (cause) {
    if (!current.signal.aborted) {
      error.value = message(cause)
      // Remove stale metadata as well as source after a permission change.
      if (cause instanceof LibraryApiError && [403, 404].includes(cause.status)) { songs.value = []; emit('unavailable') }
    }
  } finally { if (!current.signal.aborted) opening.value = false }
}
async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || importing.value || !props.library.canImport) return
  const current = importRequest = new AbortController()
  const libraryId = props.library.id
  importing.value = true
  error.value = ''
  notice.value = ''
  try {
    if (file.size > MAX_SONG_BYTES) throw new ImportValidationError('size')
    const content = decodeChordPro(file.name, await file.arrayBuffer())
    if (current.signal.aborted) return
    const created = await importSong(libraryId, file.name, content, current.signal)
    if (current.signal.aborted || libraryId !== props.library.id) return
    notice.value = t('songbook', 'Die Datei wurde importiert.')
    await load()
    if (!current.signal.aborted && libraryId === props.library.id && !error.value) await selectSong(created.id)
  } catch (cause) {
    if (!current.signal.aborted) {
      error.value = cause instanceof ImportValidationError
        ? cause.reason === 'size' ? t('songbook', 'Die Datei ist größer als 256 KiB.')
          : cause.reason === 'extension' ? t('songbook', 'Wähle eine Datei mit der Endung .cho, .pro oder .chordpro.')
            : t('songbook', 'Die Datei muss nicht-leeren UTF-8-Text ohne Nullzeichen enthalten.')
        : message(cause)
    }
  } finally { if (!current.signal.aborted) importing.value = false }
}
watch(() => props.library.id, () => {
  importRequest?.abort()
  importing.value = false
  notice.value = ''
  search.value = ''
  if (debounce) clearTimeout(debounce)
  void load()
}, { immediate: true })
watch([search, sort], () => {
  listRequest?.abort()
  clearSong()
  songs.value = []
  if (debounce) clearTimeout(debounce)
  debounce = setTimeout(() => { void load() }, 250)
})
onBeforeUnmount(() => {
  listRequest?.abort(); songRequest?.abort(); importRequest?.abort()
  if (debounce) clearTimeout(debounce)
})
</script>

<template>
  <section class="songbook-library">
    <div class="songbook-toolbar">
      <label
        class="songbook-field"
        for="songbook-search"
      >{{ t('songbook', 'Lieder suchen') }}
        <input
          id="songbook-search"
          v-model="search"
          type="search"
          maxlength="200"
          :placeholder="t('songbook', 'Titel, Interpret oder Tonart')"
        >
      </label>
      <label
        class="songbook-field"
        for="songbook-sort"
      >{{ t('songbook', 'Sortieren nach') }}
        <select
          id="songbook-sort"
          v-model="sort"
        ><option value="title">{{ t('songbook', 'Titel') }}</option><option value="artist">{{ t('songbook', 'Interpret') }}</option><option value="key">{{ t('songbook', 'Tonart') }}</option></select>
      </label>
      <NcButton
        :disabled="loading || importing"
        @click="load"
      >
        {{ t('songbook', 'Lieder aktualisieren') }}
      </NcButton>
    </div>
    <label
      v-if="library.canImport"
      class="songbook-import"
      for="songbook-import"
    >{{ t('songbook', 'ChordPro-Datei importieren (max. 256 KiB)') }}
      <input
        id="songbook-import"
        type="file"
        accept=".cho,.pro,.chordpro"
        :disabled="importing"
        @change="importFile"
      >
    </label>
    <p
      v-else
      class="songbook-muted"
    >
      {{ t('songbook', 'Du hast Lesezugriff auf diese Bibliothek.') }}
    </p>
    <p
      v-if="importing"
      role="status"
    >
      {{ t('songbook', 'Datei wird importiert …') }}
    </p>
    <p
      v-if="notice"
      role="status"
    >
      {{ notice }}
    </p>
    <p
      v-if="error"
      class="songbook-error"
      role="alert"
    >
      {{ error }}
    </p>
    <ul
      v-if="warnings.length"
      class="songbook-warnings"
      :aria-label="t('songbook', 'Hinweise zum Abgleich')"
    >
      <li
        v-for="(warning, index) in warnings"
        :key="index"
      >
        {{ warning }}
      </li>
    </ul>
    <div class="songbook-library-layout">
      <div
        :aria-busy="loading"
        aria-live="polite"
      >
        <p v-if="loading">
          {{ t('songbook', 'Lieder werden geladen …') }}
        </p>
        <p v-else-if="!songs.length && !error">
          {{ search ? t('songbook', 'Keine passenden Lieder.') : t('songbook', 'Noch keine Lieder. Importiere eine ChordPro-Datei oder lege sie in den verbundenen Ordner.') }}
        </p>
        <ul
          v-else
          class="songbook-songs"
          :aria-label="t('songbook', 'Lieder')"
        >
          <li
            v-for="song in songs"
            :key="song.id"
          >
            <button
              type="button"
              class="songbook-song"
              :aria-pressed="selected?.id === song.id"
              @click="selectSong(song.id)"
            >
              <strong>{{ song.title || song.filename }}</strong>
              <span>{{ song.artist || t('songbook', 'Kein Interpret') }} · {{ song.key || t('songbook', 'Keine Tonart') }}</span>
              <small>{{ song.filename }}</small>
            </button>
          </li>
        </ul>
      </div>
      <section
        v-if="selected || opening"
        class="songbook-source"
        :aria-busy="opening"
        :aria-label="t('songbook', 'Lied anzeigen')"
      >
        <p
          v-if="opening"
          role="status"
        >
          {{ t('songbook', 'Lied wird geöffnet …') }}
        </p>
        <template v-else-if="selected">
          <div class="songbook-toolbar">
            <h2>{{ selected.title || selected.filename }}</h2><NcButton @click="clearSong">
              {{ t('songbook', 'Lied schließen') }}
            </NcButton>
          </div>
          <p>{{ selected.artist }} <span v-if="selected.key">· {{ selected.key }}</span></p>
          <p class="songbook-muted">
            {{ t('songbook', 'ChordPro-Quelltext') }}
          </p>
          <pre tabindex="0">{{ selected.content }}</pre>
        </template>
      </section>
    </div>
  </section>
</template>
