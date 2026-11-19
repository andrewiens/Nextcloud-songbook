// SPDX-License-Identifier: AGPL-3.0-or-later
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import SongLibrary from '../../src/components/SongLibrary.vue'
import { importSong, listSongs, openSong, LibraryApiError, type SongContent } from '../../src/services/libraryApi'

vi.mock('../../src/services/libraryApi', async (original) => ({ ...await original<object>(), importSong: vi.fn(), listSongs: vi.fn(), openSong: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { template: '<button><slot /></button>' } }))
const library = { id: 1, name: 'Band', folderId: 42, canImport: true }
const song = { id: 7, fileId: 70, filename: 'Test.cho', title: 'Test song', artist: 'Band', key: 'D', version: 'v1' }
let wrapper: VueWrapper | undefined

beforeEach(() => {
  vi.mocked(listSongs).mockReset().mockResolvedValue({ songs: [song], warnings: [] })
  vi.mocked(openSong).mockReset().mockResolvedValue({ ...song, content: '[D]Test' })
  vi.mocked(importSong).mockReset().mockResolvedValue({ ...song, content: '[D]Test' })
})
afterEach(() => { wrapper?.unmount(); wrapper = undefined; vi.useRealTimers() })

async function chooseFile(name: string, content: string): Promise<void> {
  const input = wrapper!.get('input[type="file"]')
  const file = { name, size: new TextEncoder().encode(content).byteLength, arrayBuffer: async () => new TextEncoder().encode(content).buffer }
  Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
  await input.trigger('change')
  await flushPromises()
}

describe('song library interactions', () => {
  it('shows read-only access without an import control and renders source as text', async () => {
    const content = '<img src=x onerror=alert(1)>\n{title: <script>alert(2)</script>}'
    vi.mocked(openSong).mockResolvedValue({ ...song, content })
    wrapper = mount(SongLibrary, { props: { library: { ...library, canImport: false } } })
    await flushPromises()
    expect(wrapper.find('input[type="file"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Lesezugriff')
    await wrapper.get('.songbook-song').trigger('click')
    await flushPromises()
    expect(wrapper.get('pre').text()).toBe(content)
    expect(wrapper.find('pre img, pre script').exists()).toBe(false)
  })

  it('ignores an older song response after the user opens another song', async () => {
    vi.mocked(listSongs).mockResolvedValue({ songs: [song, { ...song, id: 8, title: 'Second' }], warnings: [] })
    let finish!: (value: SongContent) => void
    vi.mocked(openSong).mockReturnValueOnce(new Promise(resolve => { finish = resolve }))
      .mockResolvedValueOnce({ ...song, id: 8, content: 'Current source' })
    wrapper = mount(SongLibrary, { props: { library } })
    await flushPromises()
    await wrapper.findAll('.songbook-song')[0]!.trigger('click')
    await wrapper.findAll('.songbook-song')[1]!.trigger('click')
    await flushPromises()
    finish({ ...song, content: 'Stale source' })
    await flushPromises()
    expect(wrapper.get('pre').text()).toBe('Current source')
    expect(vi.mocked(openSong).mock.calls[0]?.[1]?.aborted).toBe(true)
  })

  it('clears source and metadata immediately after the server denies access', async () => {
    wrapper = mount(SongLibrary, { props: { library } })
    await flushPromises()
    await wrapper.get('.songbook-song').trigger('click')
    await flushPromises()
    expect(wrapper.find('pre').exists()).toBe(true)
    vi.mocked(openSong).mockRejectedValue(new LibraryApiError(404))
    await wrapper.get('.songbook-song').trigger('click')
    await flushPromises()
    expect(wrapper.find('pre').exists()).toBe(false)
    expect(wrapper.find('.songbook-song').exists()).toBe(false)
    expect(wrapper.emitted('unavailable')).toHaveLength(1)
  })

  it('debounces searching and clears results from the previous query', async () => {
    vi.useFakeTimers()
    wrapper = mount(SongLibrary, { props: { library } })
    await flushPromises()
    await wrapper.get('input[type="search"]').setValue('Grüße')
    expect(wrapper.find('.songbook-song').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(250)
    await flushPromises()
    expect(listSongs).toHaveBeenLastCalledWith(1, 'Grüße', 'title', expect.any(AbortSignal))
  })

  it('imports the exact source and opens the new song', async () => {
    wrapper = mount(SongLibrary, { props: { library } })
    await flushPromises()
    const original = '\uFEFF{future: unchanged}\r\n[D]Grüße\r\n'
    await chooseFile('Test.cho', original)
    expect(importSong).toHaveBeenCalledWith(1, 'Test.cho', original, expect.any(AbortSignal))
    expect(wrapper.text()).toContain('Die Datei wurde importiert.')
    expect(wrapper.get('pre').text()).toBe('[D]Test')
  })

  it('rejects invalid local files before uploading and reports collisions', async () => {
    wrapper = mount(SongLibrary, { props: { library } })
    await flushPromises()
    await chooseFile('bad.txt', 'text')
    expect(importSong).not.toHaveBeenCalled()
    expect(wrapper.get('[role="alert"]').text()).toContain('Endung')
    vi.mocked(importSong).mockRejectedValue(new LibraryApiError(409))
    await chooseFile('duplicate.cho', 'text')
    expect(wrapper.get('[role="alert"]').text()).toContain('nicht überschrieben')
    expect(wrapper.text()).not.toContain('Die Datei wurde importiert.')
  })

  it('aborts in-flight requests when switching libraries', async () => {
    vi.mocked(listSongs).mockReturnValueOnce(new Promise(() => {}))
    wrapper = mount(SongLibrary, { props: { library } })
    const oldSignal = vi.mocked(listSongs).mock.calls[0]?.[3]
    await wrapper.setProps({ library: { ...library, id: 2 } })
    await flushPromises()
    expect(oldSignal?.aborted).toBe(true)
    expect(listSongs).toHaveBeenLastCalledWith(2, '', 'title', expect.any(AbortSignal))
  })
})
