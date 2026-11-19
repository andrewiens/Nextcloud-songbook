// SPDX-License-Identifier: AGPL-3.0-or-later
import { describe, expect, it } from 'vitest'
import { decodeChordPro, ImportValidationError, MAX_SONG_BYTES } from '../../src/services/chordProImport'

function bytes(text: string): ArrayBuffer { return new TextEncoder().encode(text).buffer as ArrayBuffer }

describe('lossless ChordPro import', () => {
  it('preserves BOM, CRLF, Unicode, chords and unknown directives exactly', () => {
    const source = '\uFEFF{t: Grüße}\r\n{future: unknown}\r\n[G]Text <script>\r\n'
    expect(decodeChordPro('Lied.CHORDPRO', bytes(source))).toBe(source)
  })

  it.each(['../file.cho', 'sub/file.pro', 'sub\\file.cho', '.cho', 'file.txt', ' file.cho', 'file.cho ', 'bad\x7f.cho', `${'ä'.repeat(126)}.cho`])('rejects unsafe filename %s', (name) => {
    expect(() => decodeChordPro(name, bytes('test'))).toThrow(ImportValidationError)
  })

  it('rejects malformed UTF-8 instead of replacing bytes', () => {
    expect(() => decodeChordPro('file.cho', new Uint8Array([0xff, 0xfe]).buffer)).toThrow('encoding')
    expect(() => decodeChordPro('file.cho', bytes('bad\0text'))).toThrow('encoding')
  })

  it('rejects empty or oversized files and accepts the exact byte limit', () => {
    expect(() => decodeChordPro('file.cho', bytes(''))).toThrow('empty')
    expect(() => decodeChordPro('file.cho', bytes('ä'.repeat(MAX_SONG_BYTES / 2 + 1)))).toThrow('size')
    expect(decodeChordPro('file.cho', bytes('x'.repeat(MAX_SONG_BYTES)))).toHaveLength(MAX_SONG_BYTES)
  })
})
