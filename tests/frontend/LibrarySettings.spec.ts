// SPDX-License-Identifier: AGPL-3.0-or-later
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import LibrarySettings from '../../src/components/LibrarySettings.vue'
import { browseFolders, connectLibrary, LibraryApiError } from '../../src/services/libraryApi'

vi.mock('../../src/services/libraryApi', async (original) => ({ ...await original<object>(), browseFolders: vi.fn(), connectLibrary: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { template: '<button><slot /></button>' } }))
const root = { id: 10, name: 'files', path: '/' }
const band = { id: 42, name: 'Band', path: '/Band' }
let wrapper: VueWrapper | undefined
beforeEach(() => {
  vi.mocked(browseFolders).mockReset().mockImplementation(async id => id === 0
    ? { folder: root, folders: [band] } : { folder: band, folders: [] })
  vi.mocked(connectLibrary).mockReset().mockResolvedValue({ id: 1, folderId: 42, name: 'Band', canImport: true })
})
afterEach(() => { wrapper?.unmount(); wrapper = undefined })

describe('explicit library folder selection', () => {
  it('cannot connect the personal root and suggests the selected folder name', async () => {
    wrapper = mount(LibrarySettings)
    await flushPromises()
    expect(wrapper.get('input').element.value).toBe('')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    await wrapper.findAll('button').find(button => button.text() === 'Band')!.trigger('click')
    await flushPromises()
    expect(wrapper.get('input').element.value).toBe('Band')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(connectLibrary).toHaveBeenCalledWith('Band', 42, expect.any(AbortSignal))
    expect(wrapper.emitted('connected')?.[0]?.[0]).toMatchObject({ id: 1, folderId: 42 })
  })

  it('keeps a custom name and explains insufficient permission without reporting success', async () => {
    wrapper = mount(LibrarySettings)
    await flushPromises()
    await wrapper.get('input').setValue('Unsere Band')
    await wrapper.findAll('button').find(button => button.text() === 'Band')!.trigger('click')
    await flushPromises()
    expect(wrapper.get('input').element.value).toBe('Unsere Band')
    vi.mocked(connectLibrary).mockRejectedValue(new LibraryApiError(403))
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Schreib- und Leserechte')
    expect(wrapper.emitted('connected')).toBeUndefined()
  })
})
