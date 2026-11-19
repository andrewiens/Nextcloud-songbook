// SPDX-License-Identifier: AGPL-3.0-or-later
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from '@nextcloud/axios'
import { importSong, LibraryApiError, listLibraries, listSongs, openSong } from '../../src/services/libraryApi'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (path: string) => `/nextcloud/ocs/v2.php${path}` }))
const envelope = (data: unknown) => ({ data: { ocs: { meta: { status: 'ok' }, data } } })
const song = { id: 1, fileId: 20, title: 'Song', filename: 'song.cho', artist: 'Band', key: 'D', version: 'v1' }
beforeEach(() => { vi.mocked(axios.get).mockReset(); vi.mocked(axios.post).mockReset() })

describe('library API contract', () => {
  it('preserves search characters through query parameters and the installation prefix', async () => {
    vi.mocked(axios.get).mockResolvedValue(envelope({ songs: [song], warnings: [] }))
    const signal = new AbortController().signal
    expect((await listSongs(42, '100% & ü', 'artist', signal)).songs).toEqual([song])
    expect(axios.get).toHaveBeenCalledWith('/nextcloud/ocs/v2.php/apps/songbook/api/v1/songs', expect.objectContaining({
      signal, params: { format: 'json', libraryId: 42, search: '100% & ü', sort: 'artist' },
    }))
  })

  it('sends the original import source unmodified', async () => {
    const content = '\uFEFF{future: keep}\r\n[D]Text\r\n'
    vi.mocked(axios.post).mockResolvedValue(envelope({ song: { ...song, content } }))
    expect((await importSong(42, 'song.cho', content)).content).toBe(content)
    expect(axios.post).toHaveBeenCalledWith('/nextcloud/ocs/v2.php/apps/songbook/api/v1/songs/import',
      { libraryId: 42, filename: 'song.cho', content }, expect.any(Object))
  })

  it('rejects a successful transport containing an OCS error', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { meta: { status: 'failure' }, data: { libraries: [] } } } })
    await expect(listLibraries()).rejects.toMatchObject({ status: 502 })
  })

  it('rejects unsafe IDs and malformed song responses', async () => {
    vi.mocked(axios.get).mockResolvedValue(envelope({ libraries: [{ id: Number.MAX_SAFE_INTEGER + 1, folderId: 42, name: 'Band', canImport: true }] }))
    await expect(listLibraries()).rejects.toBeInstanceOf(LibraryApiError)
    vi.mocked(axios.get).mockResolvedValue(envelope({ song: { ...song, content: null } }))
    await expect(openSong(1)).rejects.toBeInstanceOf(LibraryApiError)
  })

  it('exposes the HTTP status without exposing server exception details', async () => {
    vi.mocked(axios.get).mockRejectedValue({ response: { status: 403, data: '/private/secret.cho' } })
    await expect(listLibraries()).rejects.toMatchObject({ status: 403, message: 'Songbook request failed' })
  })
})
