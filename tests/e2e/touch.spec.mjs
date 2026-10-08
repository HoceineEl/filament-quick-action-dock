import { box, expect, settle, storedPosition, test, visit } from './helpers.mjs'

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true })

async function touch(page) {
    const cdp = await page.context().newCDPSession(page)
    const send = (type, points) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points.map(({ x, y }) => ({ x, y, id: 1 })) })

    return {
        async start(point) {
            await send('touchStart', [point])
        },
        async move(from, to, steps = 10) {
            for (let step = 1; step <= steps; step++) {
                await send('touchMove', [{ x: from.x + ((to.x - from.x) * step) / steps, y: from.y + ((to.y - from.y) * step) / steps }])
            }
        },
        async end() {
            await send('touchEnd', [])
        },
    }
}

async function triggerCenter(page) {
    const rect = await box(page.locator('.qad-trigger'))

    return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 }
}

test('the trigger and items set the right touch-action', async ({ page }) => {
    await visit(page)

    const actions = await page.evaluate(() => ({
        trigger: getComputedStyle(document.querySelector('.qad-trigger')).touchAction,
        item: getComputedStyle(document.querySelector('.qad-item .fi-icon-btn')).touchAction,
        body: getComputedStyle(document.body).touchAction,
    }))

    expect(actions).toEqual({ trigger: 'none', item: 'manipulation', body: 'auto' })
})

test('a tap opens the dock and shows the scrim', async ({ page }) => {
    const root = await visit(page)

    await page.locator('.qad-trigger').tap()

    await expect(root).toHaveAttribute('data-open', 'true')
    await expect(page.locator('.qad-scrim')).toHaveCSS('opacity', '1')

    await page.touchscreen.tap(40, 160)
    await expect(root).toHaveAttribute('data-open', 'false')
})

test('a touch drag moves the dock without opening it or scrolling the page', async ({ page }) => {
    const root = await visit(page)
    const finger = await touch(page)
    const from = await triggerCenter(page)

    await finger.start(from)
    await finger.move(from, { x: 120, y: 300 })
    await expect(root).toHaveAttribute('data-dragging', '')
    await finger.end()
    await settle(page)

    expect(await box(root)).toMatchObject({ x: 92, y: 272 })
    await expect(root).toHaveAttribute('data-open', 'false')
    expect(await page.evaluate(() => window.scrollY)).toBe(0)
    expect(await storedPosition(page)).toMatchObject({ x: 'start', y: 'top' })
})

test('a long press lifts the dock, and letting go without moving does not open it', async ({ page }) => {
    const root = await visit(page)
    const finger = await touch(page)
    const from = await triggerCenter(page)
    const start = await box(root)
    let contextMenus = 0

    await page.exposeFunction('countContextMenu', () => contextMenus++)
    await page.evaluate(() => document.addEventListener('contextmenu', (event) => !event.defaultPrevented && window.countContextMenu()))

    await finger.start(from)
    await page.waitForTimeout(650)
    await expect(root).toHaveAttribute('data-lifted', '')
    await finger.end()

    await expect(root).not.toHaveAttribute('data-lifted', '')
    await page.waitForTimeout(150)
    await expect(root).toHaveAttribute('data-open', 'false')
    expect(await box(root)).toMatchObject({ x: start.x, y: start.y })
    expect(contextMenus).toBe(0)
})

test('a long press followed by a small movement starts dragging at once', async ({ page }) => {
    const root = await visit(page)
    const finger = await touch(page)
    const from = await triggerCenter(page)
    const start = await box(root)

    await finger.start(from)
    await page.waitForTimeout(650)
    await finger.move(from, { x: from.x - 4, y: from.y - 3 }, 2)
    await expect(root).toHaveAttribute('data-dragging', '')
    await finger.move({ x: from.x - 4, y: from.y - 3 }, { x: 150, y: 400 })
    await finger.end()
    await settle(page)

    expect((await box(root)).x).toBeLessThan(start.x)
    await expect(root).toHaveAttribute('data-open', 'false')
})

test('swiping the page scrolls it and leaves the dock alone', async ({ page }) => {
    const root = await visit(page)
    const finger = await touch(page)
    const start = await box(root)

    await finger.start({ x: 200, y: 600 })
    await finger.move({ x: 200, y: 600 }, { x: 200, y: 200 }, 12)
    await finger.end()

    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100)
    await expect(root).not.toHaveAttribute('data-dragging', '')
    expect(await box(root)).toMatchObject({ x: start.x, y: start.y })
})

test('the dock keeps clear of the safe area insets', async ({ page }) => {
    await page.addInitScript(() => {
        const observer = new MutationObserver(() => {
            if (document.head) {
                observer.disconnect()
                document.head.insertAdjacentHTML('beforeend', '<style>.qad-probe { padding: 47px 0 34px 0 !important; }</style>')
            }
        })

        observer.observe(document, { childList: true, subtree: true })
    })

    const root = await visit(page)

    expect((await box(root)).bottom).toBe(844 - 24 - 34)

    const finger = await touch(page)
    const from = await triggerCenter(page)

    await finger.start(from)
    await finger.move(from, { x: 40, y: 10 })
    await finger.end()
    await settle(page)

    expect(await box(root)).toMatchObject({ x: 24, y: 24 + 47 })
})
