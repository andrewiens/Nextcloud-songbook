// SPDX-License-Identifier: AGPL-3.0-or-later
import { fileURLToPath, URL } from 'node:url'
import { cpSync, existsSync, mkdirSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const packageInfo = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8')) as { name: string; version: string }

export default defineConfig({
  plugins: [vue(), {
    name: 'install-nextcloud-assets',
    generateBundle(_options, bundle) {
      const packages = new Map<string, string>()
      for (const chunk of Object.values(bundle)) {
        if (chunk.type !== 'chunk') continue
        for (const moduleId of Object.keys(chunk.modules)) {
          if (!moduleId.includes('/node_modules/') || moduleId.startsWith('\0')) continue
          let directory = dirname(moduleId.split('?')[0]!)
          while (directory.includes('/node_modules/')) {
            const metadataFile = join(directory, 'package.json')
            if (existsSync(metadataFile)) {
              const metadata = JSON.parse(readFileSync(metadataFile, 'utf8')) as { name?: string; version?: string; license?: string }
              if (metadata.name && metadata.version) {
                let licenses = readdirSync(directory, { withFileTypes: true })
                  .filter(entry => entry.isFile() && /^(licen[cs]e|copying)([.-]|$)/i.test(entry.name))
                  .map(entry => readFileSync(join(directory, entry.name), 'utf8')).join('\n\n')
                // Some Nextcloud npm distributions omit the license file. The
                // exact SPDX license is the same AGPL text shipped by this app;
                // their per-file copyright notices remain in the JS bundle.
                if (!licenses && metadata.license === 'AGPL-3.0-or-later') {
                  licenses = readFileSync(new URL('./LICENSE', import.meta.url), 'utf8')
                }
                if (!licenses && metadata.license === 'GPL-3.0-or-later') {
                  licenses = readFileSync(new URL('./LICENSES/GPL-3.0.txt', import.meta.url), 'utf8')
                }
                if (!metadata.license || !licenses) throw new Error(`Missing bundled license: ${metadata.name}`)
                const key = `${metadata.name}@${metadata.version}`
                packages.set(key, `${key}\nLicense: ${metadata.license}\n\n${licenses}`)
                break
              }
            }
            directory = dirname(directory)
          }
        }
      }
      this.emitFile({
        type: 'asset',
        fileName: 'THIRD_PARTY_LICENSES.txt',
        source: 'Third-party packages included in the Songbook browser bundle.\n\n'
          + [...packages.entries()].sort(([left], [right]) => left.localeCompare(right)).map(([, text]) => text).join('\n\n' + '='.repeat(80) + '\n\n'),
      })
    },
    writeBundle() {
      for (const directory of ['js', 'css', 'img/build']) {
        const source = fileURLToPath(new URL(`./.build/${directory}`, import.meta.url))
        const destination = fileURLToPath(new URL(`./${directory}`, import.meta.url))
        if (existsSync(source)) {
          mkdirSync(destination, { recursive: true })
          cpSync(source, destination, { recursive: true })
        }
      }
      cpSync(new URL('./.build/THIRD_PARTY_LICENSES.txt', import.meta.url), new URL('./THIRD_PARTY_LICENSES.txt', import.meta.url))
    },
  }],
  publicDir: false,
  define: {
    appName: JSON.stringify(packageInfo.name),
    appVersion: JSON.stringify(packageInfo.version),
    'process.env.NODE_ENV': JSON.stringify('production'),
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_DEVTOOLS__: false,
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
  },
  build: {
    outDir: '.build',
    emptyOutDir: true,
    target: 'es2022',
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('./src/main.ts', import.meta.url)),
      name: 'Songbook',
      formats: ['iife'],
      fileName: () => 'js/songbook-main.js',
      cssFileName: 'songbook-main',
    },
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
        assetFileNames: (asset) => asset.names.some((name) => name.endsWith('.css'))
          ? 'css/songbook-main.css'
          : 'img/build/[name]-[hash][extname]',
      },
    },
  },
})
