import fs from 'node:fs'
import path from 'node:path'
import { box, expect, settle, test } from './helpers.mjs'

const OUT = path.resolve(import.meta.dirname, '../../docs/images')
const STYLES = ['native', 'outline', 'contrast', 'tinted']

test.skip(!process.env.QAD_SCREENSHOTS, 'Set QAD_SCREENSHOTS=1 to regenerate the README screenshots.')
test.use({ deviceScaleFactor: 2, colorScheme: 'light' })
test.setTimeout(120_000)

async function open(page, options = {}, to = '/admin/customers') {
    const query = new URLSearchParams({
        'qad-demo': '1',
        'qad-style': options.style ?? 'native',
        'qad-layout': options.layout ?? 'speed-dial',
        'qad-labels': options.labels ? '1' : '0',
        'qad-locale': options.locale ?? 'en',
        'qad-dir': options.dir ?? 'ltr',
        'qad-poll': '30s',
        'qad-mobile': '',
    })

    await page.goto(`/_workbench/login?${new URLSearchParams({ to: `${to}?${query}` })}`)
    await page.waitForFunction(() => document.querySelector('.qad-root')?._x_dataStack)
    await page.addStyleTag({ content: '*, *::before, *::after { caret-color: transparent !important; } .fi-no { display: none !important; }' })
    if (options.plain) {
        await page.addStyleTag({ content: '.fi-main { visibility: hidden !important; }' })
    }

    await settle(page)

    return page.locator('.qad-root')
}

async function expand(page) {
    await page.locator('.qad-trigger').click()
    await expect(page.locator('.qad-root')).toHaveAttribute('data-open', 'true')
    await page.locator('.qad-trigger').focus()
    await page.locator('.qad-trigger').blur()
    await page.mouse.move(5, 5)
    await page.waitForTimeout(400)
}

async function regionAround(page, selectors, padding = 32, paddingY = padding) {
    const rects = []

    for (const selector of selectors) {
        for (const element of await page.locator(selector).all()) {
            if (await element.isVisible()) {
                rects.push(await box(element))
            }
        }
    }

    const viewport = page.viewportSize()
    const x = Math.max(0, Math.min(...rects.map((rect) => rect.x)) - padding)
    const y = Math.max(0, Math.min(...rects.map((rect) => rect.y)) - paddingY)
    const right = Math.min(viewport.width, Math.max(...rects.map((rect) => rect.right)) + padding)
    const bottom = Math.min(viewport.height, Math.max(...rects.map((rect) => rect.bottom)) + paddingY)

    return { x, y, width: right - x, height: bottom - y }
}

async function shoot(page, file, clip) {
    fs.mkdirSync(OUT, { recursive: true })
    await page.screenshot({ path: path.join(OUT, file), clip, animations: 'disabled' })
}

const DOCK = ['.qad-trigger', '.qad-menu .qad-item', '.qad-label', '.qad-group-label']

async function compose(page, file, cells, columns) {
    const images = cells.map(({ label, buffer }) => `<figure><img src="data:image/png;base64,${buffer.toString('base64')}"><figcaption>${label}</figcaption></figure>`).join('')

    await page.setViewportSize({ width: 2400, height: 800 })
    await page.setContent(`<style>
        body { margin: 0; background: #fff; font: 500 15px/1.4 ui-sans-serif, system-ui, sans-serif; color: #52525b; }
        main { display: inline-grid; grid-template-columns: repeat(${columns}, auto); gap: 20px; padding: 20px; }
        figure { margin: 0; display: grid; gap: 8px; justify-items: center; }
        img { display: block; zoom: 0.5; border-radius: 24px; border: 2px solid #e4e4e7; }
    </style><main>${images}</main>`)
    await page.waitForFunction(() => [...document.images].every((image) => image.complete))
    await page.locator('main').screenshot({ path: path.join(OUT, file) })
}

for (const scheme of ['light', 'dark']) {
    test(`speed dial, closed and open, ${scheme}`, async ({ page }) => {
        await page.emulateMedia({ colorScheme: scheme })
        await open(page)

        if (scheme === 'light') {
            await shoot(page, 'closed.png', { x: 560, y: 180, width: 720, height: 620 })
        }

        await expand(page)
        await shoot(page, scheme === 'light' ? 'speed-dial.png' : 'speed-dial-dark.png')
    })
}

test('badges and shortcut hints', async ({ page }) => {
    await open(page, { plain: true })
    await expand(page)
    await shoot(page, 'badges-and-shortcuts.png', await regionAround(page, ['.qad-item:has([aria-label="New customer"])', '.qad-item:has([aria-label="Inbox"])'], 24, 6))
})

test('context aware group on a record page', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 1000 })
    await open(page, {}, '/admin/customers/1/edit')
    await expand(page)
    await shoot(page, 'context.png')
})

test('bar layout', async ({ page }) => {
    await open(page, { layout: 'bar' })
    const region = await regionAround(page, ['.qad-root'], 0)
    const clip = { x: region.x - 40, y: region.y - 96, width: region.width + 40 + 24, height: region.height + 96 + 24 }

    await page.locator('.qad-menu [aria-label="New customer"]').hover()
    await page.waitForTimeout(400)
    await shoot(page, 'bar.png', clip)
})

test('bar layout with labels', async ({ page }) => {
    await open(page, { layout: 'bar', labels: true })
    const region = await regionAround(page, ['.qad-root'], 0)

    await shoot(page, 'bar-labels.png', { x: region.x - 40, y: region.y - 40, width: region.width + 40 + 24, height: region.height + 40 + 24 })
})

test('styles, speed dial', async ({ page }) => {
    const cells = { light: [], dark: [] }

    for (const scheme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme: scheme })

        for (const style of STYLES) {
            await open(page, { style, plain: true })
            await expand(page)
            const region = await regionAround(page, DOCK, 24)
            const clip = { x: region.x, y: region.y, width: page.viewportSize().width - region.x, height: page.viewportSize().height - region.y }

            cells[scheme].push({ label: style[0].toUpperCase() + style.slice(1), buffer: await page.screenshot({ clip, animations: 'disabled' }) })
        }
    }

    await page.emulateMedia({ colorScheme: 'light' })
    await compose(page, 'styles-speed-dial-light.png', cells.light, 4)
    await compose(page, 'styles-speed-dial-dark.png', cells.dark, 4)
})

test('styles, bar', async ({ page }) => {
    const cells = []

    for (const style of STYLES) {
        for (const scheme of ['light', 'dark']) {
            await page.emulateMedia({ colorScheme: scheme })
            await open(page, { style, layout: 'bar' })
            const region = await regionAround(page, ['.qad-root'], 0)
            const clip = { x: region.x - 40, y: region.y - 40, width: region.width + 40 + 24, height: region.height + 40 + 24 }

            cells.push({ label: `${style[0].toUpperCase() + style.slice(1)}, ${scheme}`, buffer: await page.screenshot({ clip, animations: 'disabled' }) })
        }
    }

    await page.emulateMedia({ colorScheme: 'light' })
    await compose(page, 'styles-bar.png', cells, 2)
})

test('confirmation modal', async ({ page }) => {
    await open(page)
    await expand(page)
    await page.locator('.qad-menu [aria-label="Send newsletter"]').click()
    await expect(page.locator('.fi-modal-window').filter({ hasText: 'Send the newsletter?' })).toBeVisible()
    await page.waitForTimeout(500)
    await shoot(page, 'confirmation.png')
})

test('form modal', async ({ page }) => {
    await open(page)
    await expand(page)
    await page.locator('.qad-menu [aria-label="New customer"]').click()
    await expect(page.locator('.fi-modal-window').filter({ hasText: 'New customer' })).toBeVisible()
    await page.waitForTimeout(500)
    await page.mouse.move(5, 5)
    await shoot(page, 'form-modal.png')
})

test('move dock panel', async ({ page }) => {
    await open(page)
    await expand(page)
    await page.locator('.qad-move-toggle').click()
    await expect(page.locator('.qad-root')).toHaveAttribute('data-moving', '')
    await page.waitForTimeout(400)
    await shoot(page, 'move-panel.png', await regionAround(page, ['.qad-move', '.qad-trigger'], 20))
})

test('drag in progress', async ({ page }) => {
    await open(page)
    const from = await box(page.locator('.qad-trigger'))

    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2)
    await page.mouse.down()
    await page.mouse.move(900, 540, { steps: 20 })
    await expect(page.locator('.qad-root')).toHaveAttribute('data-dragging', '')
    await page.waitForTimeout(300)
    await shoot(page, 'drag.png', { x: 600, y: 340, width: 600, height: 320 })
    await page.mouse.up()
})

test('right to left', async ({ page }) => {
    await open(page, { locale: 'ar', dir: 'rtl' })
    await expand(page)
    await shoot(page, 'rtl.png')
})

test.describe('phone', () => {
    test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 })

    test('mobile speed dial with scrim', async ({ page }) => {
        await open(page)
        await page.locator('.qad-trigger').tap()
        await expect(page.locator('.qad-root')).toHaveAttribute('data-open', 'true')
        await page.waitForTimeout(400)
        await shoot(page, 'mobile.png')
    })
})
