import { test as base, expect } from '@playwright/test'
import { createHmac } from 'node:crypto'

export { expect }

const APP_KEY = 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10='
const USER_HASH = createHmac('sha256', APP_KEY).update('1').digest('hex').slice(0, 16)

export const POSITION_KEY = `qad:admin:${USER_HASH}:position`
export const COLLAPSED_KEY = `qad:admin:${USER_HASH}:collapsed`

const IGNORED_ERRORS = [/favicon\.ico/]

export const test = base.extend({
    consoleErrors: [
        async ({ page }, use) => {
            const errors = []

            page.on('console', (message) => {
                if (message.type() === 'error' && !IGNORED_ERRORS.some((pattern) => pattern.test(message.text()))) {
                    errors.push(message.text())
                }
            })
            page.on('pageerror', (error) => errors.push(error.message))

            await use(errors)

            expect(errors, 'console errors').toEqual([])
        },
        { auto: true },
    ],
})

export function dockUrl(options = {}, path = '/admin') {
    const query = new URLSearchParams({
        'qad-style': options.style ?? 'native',
        'qad-layout': options.layout ?? 'speed-dial',
        'qad-dir': options.dir ?? '',
        'qad-locale': options.locale ?? 'en',
        'qad-poll': options.poll ?? '30s',
        'qad-mobile': options.mobile ?? '',
    })

    return `${path}?${query}`
}

export function signedInUrl(options = {}, path = '/admin') {
    return `/_workbench/login?${new URLSearchParams({ to: dockUrl(options, path) })}`
}

export async function visit(page, options = {}, path = '/admin') {
    await page.goto(signedInUrl(options, path))
    await page.waitForFunction(() => document.querySelector('.qad-root')?._x_dataStack)

    return page.locator('.qad-root')
}

export async function box(locator) {
    const rect = await locator.evaluate((element) => element.getBoundingClientRect().toJSON())

    return { x: Math.round(rect.x), y: Math.round(rect.y), width: Math.round(rect.width), height: Math.round(rect.height), right: Math.round(rect.right), bottom: Math.round(rect.bottom) }
}

export async function modifier(page) {
    return (await page.evaluate(() => /Mac|iPhone|iPad/.test(navigator.platform))) ? 'Meta' : 'Control'
}

export async function storedPosition(page) {
    return page.evaluate((key) => JSON.parse(localStorage.getItem(key) ?? 'null'), POSITION_KEY)
}

export async function settle(page) {
    await page.waitForFunction(() => !document.querySelector('.qad-root[data-settling]'))
}
