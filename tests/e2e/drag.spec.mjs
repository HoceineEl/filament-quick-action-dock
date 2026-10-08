import { POSITION_KEY, box, expect, settle, storedPosition, test, visit } from './helpers.mjs'

const trigger = (page) => page.locator('.qad-trigger')

async function drag(page, to, { steps = 12, release = true } = {}) {
    const from = await box(trigger(page))

    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2)
    await page.mouse.down()
    await page.mouse.move(to.x, to.y, { steps })

    if (release) {
        await page.mouse.up()
        await settle(page)
    }
}

test('dragging moves the dock, keeps it closed and remembers the spot across reloads', async ({ page }) => {
    const root = await visit(page)

    await drag(page, { x: 500, y: 400 })

    const dropped = await box(root)

    expect(dropped.x).toBe(472)
    expect(dropped.y).toBe(372)
    await expect(root).toHaveAttribute('data-open', 'false')
    expect(await storedPosition(page)).toEqual({ x: 'start', xf: 0.3688, y: 'top', yf: 0.465 })

    await page.reload()
    await page.waitForFunction(() => document.querySelector('.qad-root')?._x_dataStack)

    expect(await box(page.locator('.qad-root'))).toMatchObject({ x: 472, y: 372 })
})

test('a drop near an edge snaps to it', async ({ page }) => {
    const root = await visit(page)

    await drag(page, { x: 70, y: 60 })

    expect(await box(root)).toMatchObject({ x: 24, y: 24 })
    await expect(root).toHaveAttribute('data-direction', 'down')
})

test('the dock stays on screen and keeps its edge when the window resizes', async ({ page }) => {
    const root = await visit(page)

    await drag(page, { x: 1100, y: 200 })
    const before = await box(root)

    await page.setViewportSize({ width: 700, height: 500 })
    await page.waitForTimeout(100)

    const after = await box(root)
    const rightGap = (width, rect) => width - rect.right

    expect(Math.abs(rightGap(700, after) / 700 - rightGap(1280, before) / 1280)).toBeLessThan(0.01)
    expect(after.bottom).toBeLessThanOrEqual(500 - 24)
})

test('a tiny movement still counts as a click', async ({ page }) => {
    const root = await visit(page)
    const from = await box(trigger(page))

    await page.mouse.move(from.x + 28, from.y + 28)
    await page.mouse.down()
    await page.mouse.move(from.x + 31, from.y + 30)
    await page.mouse.up()

    await expect(root).toHaveAttribute('data-open', 'true')
    expect(await box(root)).toMatchObject({ x: from.x, y: from.y })
})

test('Escape cancels a drag in progress', async ({ page }) => {
    const root = await visit(page)
    const start = await box(root)

    await drag(page, { x: 400, y: 300 }, { release: false })
    await page.keyboard.press('Escape')
    await page.mouse.up()
    await settle(page)

    expect(await box(root)).toMatchObject({ x: start.x, y: start.y })
    await expect(root).toHaveAttribute('data-open', 'false')
})

test('Alt and an arrow key nudge the focused trigger', async ({ page }) => {
    const root = await visit(page)
    const start = await box(root)

    await trigger(page).focus()
    await page.keyboard.press('Alt+ArrowLeft')
    await page.keyboard.press('Alt+ArrowUp')
    await settle(page)

    expect(await box(root)).toMatchObject({ x: start.x - 24, y: start.y - 24 })
    expect(await storedPosition(page)).not.toBeNull()
})

test('the Move panel sends the dock to a corner and resets it', async ({ page }) => {
    const root = await visit(page)

    await trigger(page).click()
    await page.locator('.qad-move-toggle').click()

    const panel = page.locator('.qad-move')

    await expect(panel).toBeVisible()
    await expect(panel.locator('[data-corner="bottom-right"]')).toHaveAttribute('aria-pressed', 'true')
    await expect(panel.locator('[data-corner="top-left"]')).toBeFocused()

    await page.keyboard.press('ArrowRight')
    await expect(panel.locator('[data-corner="top-right"]')).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(panel.locator('[data-corner="bottom-right"]')).toBeFocused()
    await page.keyboard.press('ArrowDown')
    await expect(panel.locator('.qad-move-reset')).toBeFocused()

    await panel.locator('[data-corner="top-left"]').click()
    await settle(page)

    expect(await box(root)).toMatchObject({ x: 24, y: 24 })
    await expect(panel).toBeHidden()
    await expect(trigger(page)).toBeFocused()

    await trigger(page).click()
    await page.locator('.qad-move-toggle').click()
    await expect(panel.locator('[data-corner="top-left"]')).toHaveAttribute('aria-pressed', 'true')
    await panel.locator('.qad-move-reset').click()
    await settle(page)

    expect(await box(root)).toMatchObject({ x: 1200, y: 720 })
    expect(await page.evaluate((key) => localStorage.getItem(key), POSITION_KEY)).toBeNull()
})

const directions = [
    { name: 'English, left to right', locale: 'en', dir: 'ltr', labels: { 'top-left': 'Top left', 'bottom-right': 'Bottom right' } },
    { name: 'English, right to left', locale: 'en', dir: 'rtl', labels: { 'top-left': 'Top left', 'bottom-right': 'Bottom right' } },
    { name: 'Arabic, right to left', locale: 'ar', dir: 'rtl', labels: { 'top-left': 'أعلى اليسار', 'bottom-right': 'أسفل اليمين' } },
    { name: 'Arabic, left to right', locale: 'ar', dir: 'ltr', labels: { 'top-left': 'أعلى اليسار', 'bottom-right': 'أسفل اليمين' } },
]

for (const { name, locale, dir, labels } of directions) {
    test(`corner buttons match the screen corners in ${name}`, async ({ page }) => {
        const root = await visit(page, { locale, dir })

        expect(await page.evaluate(() => getComputedStyle(document.querySelector('.qad-root')).direction)).toBe(dir)
        expect(await box(root)).toMatchObject(dir === 'rtl' ? { x: 24 } : { x: 1200 })

        await page.locator('.qad-trigger').click()
        await page.locator('.qad-move-toggle').click()

        const left = await box(page.getByRole('button', { name: labels['top-left'] }))
        const right = await box(page.getByRole('button', { name: labels['bottom-right'] }))

        expect(left.x).toBeLessThan(right.x)
        expect(left.y).toBeLessThan(right.y)

        const pressed = await page.locator('.qad-move-button[aria-pressed="true"]').getAttribute('data-corner')

        expect(pressed).toBe(dir === 'rtl' ? 'bottom-left' : 'bottom-right')

        await page.getByRole('button', { name: labels['top-left'] }).click()
        await settle(page)
        expect(await box(root)).toMatchObject({ x: 24, y: 24 })

        await page.locator('.qad-trigger').click()
        await page.locator('.qad-move-toggle').click()
        await page.getByRole('button', { name: labels['bottom-right'] }).click()
        await settle(page)
        expect(await box(root)).toMatchObject({ x: 1200, y: 720 })
    })
}
