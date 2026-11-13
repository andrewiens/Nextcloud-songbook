// SPDX-License-Identifier: AGPL-3.0-or-later
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import App from '../../src/App.vue'
import { getAppStatus, type AppStatus } from '../../src/services/api'

vi.mock('../../src/services/api', () => ({ getAppStatus: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (_app: string, text: string) => text }))

// Isolate Songbook behavior; actual Nextcloud component integration is checked
// by the browser smoke test against an installed server, not these test doubles.
vi.mock('@nextcloud/vue/components/NcContent', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('@nextcloud/vue/components/NcAppContent', () => ({ default: { template: '<main><slot /></main>' } }))
vi.mock('@nextcloud/vue/components/NcAppNavigation', () => ({ default: { template: '<nav><slot name="list" /><slot name="footer" /></nav>' } }))
vi.mock('@nextcloud/vue/components/NcAppNavigationItem', () => ({ default: {
  props: ['name', 'href', 'active'],
  template: '<a :href="href" :aria-current="active ? \'page\' : undefined"><slot name="icon" />{{ name }}</a>',
} }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { template: '<button><slot /></button>' } }))

const status: AppStatus = {
  app: 'songbook',
  version: '0.1.0-dev',
  apiVersion: 1,
  capabilities: { songLibrary: false, setlists: false },
}
let wrapper: VueWrapper | undefined

beforeEach(() => {
  window.history.replaceState(null, '', '/')
  vi.mocked(getAppStatus).mockReset().mockResolvedValue(status)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
})

describe('Songbook start page', () => {
  it('waits for the real connection before showing the empty library', async () => {
    let complete!: (value: AppStatus) => void
    vi.mocked(getAppStatus).mockReturnValue(new Promise((resolve) => { complete = resolve }))
    wrapper = mount(App)

    expect(wrapper.text()).toContain('Songbook wird geladen')
    expect(wrapper.text()).not.toContain('Noch keine Lieder')
    expect(wrapper.get('[aria-live="polite"]').attributes('aria-busy')).toBe('true')
    complete(status)
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Lieder')
    expect(wrapper.get('h2').text()).toBe('Noch keine Lieder')
    expect(wrapper.get('[aria-live="polite"]').attributes('aria-busy')).toBe('false')
    expect(getAppStatus).toHaveBeenCalledOnce()
  })

  it('switches to setlists and back without fetching invented song data', async () => {
    wrapper = mount(App)
    await flushPromises()

    await wrapper.get('a[href="#setlists"]').trigger('click')
    expect(wrapper.get('h1').text()).toBe('Setlisten')
    expect(wrapper.get('h2').text()).toBe('Noch keine Setlisten')
    expect(wrapper.get('a[href="#setlists"]').attributes('aria-current')).toBe('page')

    await wrapper.get('a[href="#songs"]').trigger('click')
    expect(wrapper.get('h1').text()).toBe('Lieder')
    expect(getAppStatus).toHaveBeenCalledOnce()
  })

  it('opens bookmarked setlists and follows browser history hash changes', async () => {
    window.history.replaceState(null, '', '#setlists')
    wrapper = mount(App)
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe('Setlisten')

    window.history.replaceState(null, '', '#songs')
    window.dispatchEvent(new HashChangeEvent('hashchange'))
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe('Lieder')
  })

  it('shows a retry action on connection failure and recovers', async () => {
    vi.mocked(getAppStatus).mockRejectedValueOnce(new Error('HTTP 401')).mockResolvedValueOnce(status)
    wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('Verbindung nicht möglich')
    expect(wrapper.text()).not.toContain('Noch keine Lieder')
    expect(wrapper.text()).not.toContain('HTTP 401')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('h2').text()).toBe('Noch keine Lieder')
    expect(getAppStatus).toHaveBeenCalledTimes(2)
  })

  it('cancels an outstanding request when the app is unmounted', () => {
    vi.mocked(getAppStatus).mockReturnValue(new Promise(() => {}))
    wrapper = mount(App)
    const signal = vi.mocked(getAppStatus).mock.calls[0]?.[0]
    expect(signal?.aborted).toBe(false)
    wrapper.unmount()
    wrapper = undefined
    expect(signal?.aborted).toBe(true)
  })
})
