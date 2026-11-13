// SPDX-License-Identifier: AGPL-3.0-or-later
export const MAX_SONG_BYTES = 256 * 1024

export class ImportValidationError extends Error {
  constructor(public readonly reason: 'extension' | 'size' | 'encoding' | 'empty') { super(reason) }
}

/** Preserve original UTF-8 bytes, including a possible BOM, through JSON transport. */
export function decodeChordPro(filename: string, bytes: ArrayBuffer): string {
  if (!/\.(cho|pro|chordpro)$/i.test(filename) || /[/\\\x00-\x1f]/.test(filename)) throw new ImportValidationError('extension')
  if (bytes.byteLength > MAX_SONG_BYTES) throw new ImportValidationError('size')
  if (bytes.byteLength === 0) throw new ImportValidationError('empty')
  let text: string
  try { text = new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(bytes) } catch { throw new ImportValidationError('encoding') }
  if (text.includes('\0')) throw new ImportValidationError('encoding')
  return text
}
