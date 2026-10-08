import { chromium, defineConfig } from '@playwright/test'
import fs from 'node:fs'
import net from 'node:net'
import os from 'node:os'
import path from 'node:path'

function freePort() {
    return new Promise((resolve, reject) => {
        const server = net.createServer().once('error', reject).listen(0, '127.0.0.1', () => {
            const { port } = server.address()
            server.close(() => resolve(port))
        })
    })
}

function cachedHeadlessShell() {
    const roots = [path.join(os.homedir(), 'Library/Caches/ms-playwright'), path.join(os.homedir(), '.cache/ms-playwright')]

    for (const root of roots.filter((dir) => fs.existsSync(dir))) {
        const builds = fs.readdirSync(root).filter((name) => name.startsWith('chromium_headless_shell-')).sort().reverse()

        for (const build of builds) {
            const binary = fs.readdirSync(path.join(root, build))
                .map((platform) => path.join(root, build, platform, 'chrome-headless-shell'))
                .find((file) => fs.existsSync(file))

            if (binary) {
                return binary
            }
        }
    }

    return undefined
}

function executablePath() {
    if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE) {
        return process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE
    }

    return fs.existsSync(chromium.executablePath()) ? undefined : cachedHeadlessShell()
}

process.env.QAD_E2E_PORT ??= String(await freePort())

const port = Number(process.env.QAD_E2E_PORT)
const root = path.resolve(import.meta.dirname, '../..')

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.mjs',
    fullyParallel: true,
    workers: process.env.CI ? 2 : 4,
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: { timeout: 5_000 },
    reporter: process.env.CI ? 'github' : 'list',
    outputDir: path.join(root, 'test-results'),
    globalSetup: './global-setup.mjs',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        browserName: 'chromium',
        viewport: { width: 1280, height: 800 },
        launchOptions: { executablePath: executablePath() },
        trace: 'retain-on-failure',
    },
    webServer: {
        command: `php vendor/bin/testbench serve --host=127.0.0.1 --port=${port} --no-reload`,
        cwd: root,
        url: `http://127.0.0.1:${port}/admin`,
        env: { PHP_CLI_SERVER_WORKERS: '6' },
        reuseExistingServer: false,
        timeout: 60_000,
    },
})
