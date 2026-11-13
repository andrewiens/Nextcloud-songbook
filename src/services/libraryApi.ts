// SPDX-License-Identifier: AGPL-3.0-or-later
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface Library { id: number; name: string; folderId: number; canImport: boolean }
export interface Folder { id: number; name: string; path: string }
export interface FolderListing { folder: Folder; folders: Folder[] }
export interface Song { id: number; fileId: number; filename: string; title: string; artist: string; key: string; version: string }
export interface SongContent extends Song { content: string }
export interface SongListing { songs: Song[]; warnings: string[] }
export type SongSort = 'title' | 'artist' | 'key'

export class LibraryApiError extends Error {
  constructor(public readonly status: number) { super('Songbook request failed') }
}

function object(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}
function positiveId(value: unknown): value is number { return Number.isSafeInteger(value) && Number(value) > 0 }
function isLibrary(value: unknown): value is Library {
  return object(value) && positiveId(value.id) && positiveId(value.folderId)
    && typeof value.name === 'string' && typeof value.canImport === 'boolean'
}
function isFolder(value: unknown): value is Folder {
  return object(value) && Number.isSafeInteger(value.id) && Number(value.id) >= 0
    && typeof value.name === 'string' && typeof value.path === 'string'
}
function isSong(value: unknown): value is Song {
  return object(value) && positiveId(value.id) && positiveId(value.fileId)
    && ['filename', 'title', 'artist', 'key', 'version'].every(key => typeof value[key] === 'string')
}
function songListing(value: unknown): value is SongListing {
  return object(value) && Array.isArray(value.songs) && value.songs.every(isSong)
    && Array.isArray(value.warnings) && value.warnings.every(item => typeof item === 'string')
}
function songContent(value: unknown): value is { song: SongContent } {
  return object(value) && isSong(value.song) && 'content' in value.song && typeof value.song.content === 'string'
}
async function call<T>(method: 'get' | 'post', path: string, validate: (value: unknown) => value is T, options: {
  signal?: AbortSignal; params?: Record<string, string | number>; body?: unknown
} = {}): Promise<T> {
  try {
    const config = { signal: options.signal, timeout: 30000, headers: { Accept: 'application/json' }, params: { format: 'json', ...options.params } }
    const response = method === 'get'
      ? await axios.get(generateOcsUrl(`/apps/songbook/api/v1/${path}`), config)
      : await axios.post(generateOcsUrl(`/apps/songbook/api/v1/${path}`), options.body, config)
    const data: unknown = response.data
    if (!object(data) || !object(data.ocs) || !object(data.ocs.meta) || data.ocs.meta.status !== 'ok' || !validate(data.ocs.data)) {
      throw new LibraryApiError(502)
    }
    return data.ocs.data
  } catch (error) {
    if (error instanceof LibraryApiError) throw error
    const status = object(error) && object(error.response) && typeof error.response.status === 'number' ? error.response.status : 0
    throw new LibraryApiError(status)
  }
}
export async function listLibraries(signal?: AbortSignal): Promise<Library[]> {
  const result = await call('get', 'libraries', (value): value is { libraries: Library[] } =>
    object(value) && Array.isArray(value.libraries) && value.libraries.every(isLibrary), { signal })
  return result.libraries
}
export function browseFolders(folderId = 0, signal?: AbortSignal): Promise<FolderListing> {
  return call('get', 'folders', (value): value is FolderListing => object(value) && isFolder(value.folder)
    && Array.isArray(value.folders) && value.folders.every(isFolder), { signal, params: { folderId } })
}
export async function connectLibrary(name: string, folderId: number, signal?: AbortSignal): Promise<Library> {
  const result = await call('post', 'libraries', (value): value is { library: Library } => object(value) && isLibrary(value.library), {
    signal, body: { name, folderId },
  })
  return result.library
}
export function listSongs(libraryId: number, search: string, sort: SongSort, signal?: AbortSignal): Promise<SongListing> {
  return call('get', 'songs', songListing, { signal, params: { libraryId, search, sort } })
}
export function syncLibrary(libraryId: number, signal?: AbortSignal): Promise<SongListing> {
  return call('post', `libraries/${libraryId}/sync`, songListing, { signal })
}
export async function openSong(id: number, signal?: AbortSignal): Promise<SongContent> {
  return (await call('get', `songs/${id}`, songContent, { signal })).song
}
export async function importSong(libraryId: number, filename: string, content: string, signal?: AbortSignal): Promise<SongContent> {
  return (await call('post', 'songs/import', songContent, { signal, body: { libraryId, filename, content } })).song
}
