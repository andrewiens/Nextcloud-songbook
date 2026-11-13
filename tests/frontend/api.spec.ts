// SPDX-License-Identifier: AGPL-3.0-or-later
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { getAppStatus } from '../../src/services/api'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
  generateOcsUrl: vi.fn((path: string) => `/nextcloud/ocs/v2.php${path}`),
}))

const status = {
  app: 'songbook',
  version: '0.1.0-dev',
  apiVersion: 1,
  capabilities: { songLibrary: false, setlists: false },
}

beforeEach(() => {
  vi.mocked(axios.get).mockReset()
})

describe('Songbook OCS client', () => {
  it('uses the Nextcloud URL helper and authenticated client, including subdirectory installs', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { meta: { status: 'ok', statuscode: 200 }, data: status } } })
    const controller = new AbortController()

    await expect(getAppStatus(controller.signal)).resolves.toEqual(status)
    expect(generateOcsUrl).toHaveBeenCalledWith('/apps/songbook/api/v1/status')
    expect(axios.get).toHaveBeenCalledWith('/nextcloud/ocs/v2.php/apps/songbook/api/v1/status', {
      headers: { Accept: 'application/json' },
      params: { format: 'json' },
      timeout: 15000,
      signal: controller.signal,
    })
  })

  it.each([
    ['missing OCS envelope', {}],
    ['OCS failure', { ocs: { meta: { status: 'failure', statuscode: 997 }, data: status } }],
    ['wrong API version', { ocs: { meta: { status: 'ok' }, data: { ...status, apiVersion: 2 } } }],
    ['wrong application', { ocs: { meta: { status: 'ok' }, data: { ...status, app: 'other' } } }],
    ['missing capabilities', { ocs: { meta: { status: 'ok' }, data: { app: 'songbook', version: '0.1.0', apiVersion: 1 } } }],
    ['invalid capabilities', { ocs: { meta: { status: 'ok' }, data: { ...status, capabilities: { songLibrary: 'false', setlists: false } } } }],
  ])('rejects %s rather than reporting a successful connection', async (_name, data) => {
    vi.mocked(axios.get).mockResolvedValue({ data })
    await expect(getAppStatus()).rejects.toThrow('Invalid Songbook OCS response')
  })

  it('propagates authentication and network errors to the UI', async () => {
    const error = new Error('HTTP 401')
    vi.mocked(axios.get).mockRejectedValue(error)
    await expect(getAppStatus()).rejects.toThrow('HTTP 401')
  })
})
