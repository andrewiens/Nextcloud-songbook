<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import SongbookIcon from './components/SongbookIcon.vue'
import LibrarySettings from './components/LibrarySettings.vue'
import SongLibrary from './components/SongLibrary.vue'
import { getAppStatus } from './services/api'
import { listLibraries, type Library } from './services/libraryApi'

type Section = 'songs' | 'setlists' | 'settings'
const section = ref<Section>('songs')
const connection = ref<'loading' | 'ready' | 'error'>('loading')
const libraries = ref<Library[]>([])
const selectedId = ref<number>()
const selectedLibrary = computed(() => libraries.value.find(library => library.id === selectedId.value))
let request: AbortController | undefined
const heading = computed(() => section.value === 'songs' ? t('songbook', 'Lieder')
  : section.value === 'setlists' ? t('songbook', 'Setlisten') : t('songbook', 'Bibliothekseinstellungen'))
function syncSection(): void {
  const hash = window.location.hash.slice(1)
  if (hash === 'songs' || hash === 'setlists' || hash === 'settings') section.value = hash
}
function go(value: Section): void { section.value = value; window.location.hash = value }
async function connect(preferredId?: number): Promise<void> {
  request?.abort()
  const current = request = new AbortController()
  connection.value = 'loading'
  const previousId = preferredId ?? selectedId.value
  libraries.value = []
  try {
    await getAppStatus(current.signal)
    const result = await listLibraries(current.signal)
    if (current.signal.aborted) return
    libraries.value = result
    selectedId.value = result.some(library => library.id === previousId) ? previousId : result[0]?.id
    connection.value = 'ready'
  } catch {
    if (!current.signal.aborted) connection.value = 'error'
  }
}
async function connected(library: Library): Promise<void> { await connect(library.id); go('songs') }
syncSection()
onMounted(() => { window.addEventListener('hashchange', syncSection); void connect() })
onBeforeUnmount(() => { request?.abort(); window.removeEventListener('hashchange', syncSection) })
</script>

<template>
  <NcContent app-name="songbook">
    <NcAppNavigation :aria-label="t('songbook', 'Songbook-Navigation')">
      <template #list>
        <NcAppNavigationItem
          :name="t('songbook', 'Lieder')"
          href="#songs"
          :active="section === 'songs'"
          @click="section = 'songs'"
        >
          <template #icon>
            <SongbookIcon
              kind="songs"
              :size="20"
            />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem
          :name="t('songbook', 'Setlisten')"
          href="#setlists"
          :active="section === 'setlists'"
          @click="section = 'setlists'"
        >
          <template #icon>
            <SongbookIcon
              kind="setlists"
              :size="20"
            />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem
          :name="t('songbook', 'Bibliothekseinstellungen')"
          href="#settings"
          :active="section === 'settings'"
          @click="section = 'settings'"
        />
      </template>
      <template #footer>
        <p class="songbook-nav-footer">
          Songbook
        </p>
      </template>
    </NcAppNavigation>
    <NcAppContent>
      <div class="songbook-page">
        <header class="songbook-heading">
          <h1>{{ heading }}</h1><p>{{ t('songbook', 'Eure Musik. Gemeinsam an einem Ort.') }}</p>
        </header>
        <div
          aria-live="polite"
          :aria-busy="connection === 'loading'"
        >
          <div
            v-if="connection === 'loading'"
            class="songbook-state"
          >
            <span
              class="songbook-loading"
              aria-hidden="true"
            /><p>{{ t('songbook', 'Songbook wird geladen …') }}</p>
          </div>
          <div
            v-else-if="connection === 'error'"
            class="songbook-state"
          >
            <h2>{{ t('songbook', 'Verbindung nicht möglich') }}</h2>
            <p>{{ t('songbook', 'Bitte prüfe deine Verbindung und ob du noch bei Nextcloud angemeldet bist.') }}</p>
            <NcButton @click="connect()">
              {{ t('songbook', 'Erneut versuchen') }}
            </NcButton>
          </div>
          <template v-else>
            <LibrarySettings
              v-if="section === 'settings'"
              @connected="connected"
            />
            <div
              v-else-if="section === 'setlists'"
              class="songbook-state"
            >
              <SongbookIcon
                kind="setlists"
                :size="64"
              /><h2>{{ t('songbook', 'Noch keine Setlisten') }}</h2><p>{{ t('songbook', 'Hier ist Platz für die Liedfolgen eurer Proben und Auftritte.') }}</p>
            </div>
            <template v-else>
              <div class="songbook-toolbar songbook-library-selector">
                <label
                  v-if="libraries.length"
                  class="songbook-field"
                  for="songbook-library"
                >{{ t('songbook', 'Bibliothek') }}
                  <select
                    id="songbook-library"
                    v-model="selectedId"
                  ><option
                    v-for="library in libraries"
                    :key="library.id"
                    :value="library.id"
                  >{{ library.name }}</option></select>
                </label>
                <NcButton @click="connect()">
                  {{ t('songbook', 'Bibliotheken neu laden') }}
                </NcButton>
              </div>
              <SongLibrary
                v-if="selectedLibrary"
                :key="selectedLibrary.id"
                :library="selectedLibrary"
                @unavailable="connect()"
              />
              <div
                v-else
                class="songbook-state"
              >
                <SongbookIcon
                  kind="songs"
                  :size="64"
                /><h2>{{ t('songbook', 'Noch keine Bibliothek verbunden') }}</h2><p>{{ t('songbook', 'Verbinde einen gemeinsamen Ordner mit euren ChordPro-Dateien.') }}</p><NcButton @click="go('settings')">
                  {{ t('songbook', 'Bibliothek verbinden') }}
                </NcButton>
              </div>
            </template>
          </template>
        </div>
      </div>
    </NcAppContent>
  </NcContent>
</template>
