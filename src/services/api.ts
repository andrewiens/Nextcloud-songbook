// SPDX-License-Identifier: AGPL-3.0-or-later
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface AppStatus {
  app: 'songbook'
  version: string
  apiVersion: 1
  capabilities: {
    songLibrary: boolean
    setlists: boolean
  }
}

interface OcsResponse {
  ocs?: {
    meta?: { status?: string; statuscode?: number }
    data?: unknown
  }
}

function isAppStatus(value: unknown): value is AppStatus {
  if (typeof value !== 'object' || value === null) return false
  const status = value as Partial<AppStatus>
  return status.app === 'songbook'
    && typeof status.version === 'string'
    && status.apiVersion === 1
    && typeof status.capabilities?.songLibrary === 'boolean'
    && typeof status.capabilities?.setlists === 'boolean'
}

/** Nextcloud's axios instance supplies the session request token. */
export async function getAppStatus(signal?: AbortSignal): Promise<AppStatus> {
  const response = await axios.get<OcsResponse>(generateOcsUrl('/apps/songbook/api/v1/status'), {
    headers: { Accept: 'application/json' },
    params: { format: 'json' },
    timeout: 15000,
    signal,
  })
  const envelope = response.data.ocs
  if (envelope?.meta?.status !== 'ok' || !isAppStatus(envelope.data)) {
    throw new Error('Invalid Songbook OCS response')
  }
  return envelope.data
}
