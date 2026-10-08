import { POSITION_KEY, box, expect, modifier, test, visit } from './helpers.mjs'

const trigger = (page) => page.locator('.qad-trigger')
const item = (page, label) => page.locator(`.qad-menu [role="menuitem"][aria-label="${label}"]`)

test('opens with a click and moves focus to the item nearest the trigger', async ({ page }) => {
    const root = await visit(page)

    await trigger(page).click()

    await expect(root).toHaveAttribute('data-open', 'true')
    await expect(trigger(page)).toHaveAttribute('aria-expanded', 'true')
    await expect(trigger(page)).toHaveAttribute('aria-label', 'Close')
    await expect(page.locator('.qad-menu')).toBeVisible()
    await expect(item(page, 'Export the quarterly revenue summary for every region')).toBeFocused()
})

test('Escape closes the menu and returns focus to the trigger', async ({ page }) => {
    const root = await visit(page)

    await trigger(page).click()
    await page.keyboard.press('Escape')

    await expect(root).toHaveAttribute('data-open', 'false')
    await expect(trigger(page)).toBeFocused()
    await expect(page.locator('.qad-menu')).toBeHidden()
})

test('an outside click closes the menu', async ({ page }) => {
    const root = await visit(page)

    await trigger(page).click()
    await page.mouse.click(600, 120)

    await expect(root).toHaveAttribute('data-open', 'false')
})

test('the toggle shortcut opens and closes the dock', async ({ page }) => {
    const root = await visit(page)
    const mod = await modifier(page)

    await page.keyboard.press(`${mod}+Period`)
    await expect(root).toHaveAttribute('data-open', 'true')

    await page.keyboard.press(`${mod}+Period`)
    await expect(root).toHaveAttribute('data-open', 'false')
})

test('arrow keys, Home and End move between items and skip disabled ones', async ({ page }) => {
    await visit(page)
    await trigger(page).click()

    const focusedLabel = () => page.evaluate(() => document.activeElement.getAttribute('aria-label'))

    await expect(item(page, 'Export the quarterly revenue summary for every region')).toBeFocused()

    await page.keyboard.press('ArrowUp')
    expect(await focusedLabel()).toBe('Customers')

    await page.keyboard.press('Home')
    expect(await focusedLabel()).toBe('Move dock')

    await page.keyboard.press('ArrowUp')
    expect(await focusedLabel()).toBe('Export the quarterly revenue summary for every region')

    await page.keyboard.press('ArrowDown')
    expect(await focusedLabel()).toBe('Move dock')

    await page.keyboard.press('End')
    expect(await focusedLabel()).toBe('Export the quarterly revenue summary for every region')

    await expect(item(page, 'Archive old customers')).toBeDisabled()
})

test('a modal action opens its form, submits and closes the dock', async ({ page }) => {
    const root = await visit(page)
    const name = `E2E ${Date.now()}`

    await trigger(page).click()
    await item(page, 'New customer').click()

    const modal = page.locator('.fi-modal-window').filter({ hasText: 'New customer' })

    await expect(modal).toBeVisible()
    await expect(root).toHaveAttribute('data-open', 'false')

    await modal.locator('input').first().fill(name)
    await modal.getByRole('button', { name: 'Submit' }).click()

    await expect(page.locator('.fi-no-notification').filter({ hasText: 'Customer created' })).toBeVisible()
    await expect(modal).toBeHidden()
})

test('an item key binding works while the dock is closed', async ({ page }) => {
    await visit(page)

    await page.keyboard.press(`${await modifier(page)}+Shift+KeyC`)

    await expect(page.locator('.fi-modal-window').filter({ hasText: 'New customer' })).toBeVisible()
})

test('a URL action navigates, also from its label chip', async ({ page }) => {
    await visit(page)

    await trigger(page).click()
    await page.locator('.qad-label-text').getByText('Customers', { exact: true }).click()

    await expect(page).toHaveURL(/\/admin\/customers/)
})

test('badges refresh while polling', async ({ page, browser }) => {
    await visit(page, { poll: '1s' })

    const badge = page.locator('.qad-item .fi-badge')
    const before = Number(await badge.textContent())

    const other = await browser.newPage()
    await visit(other)
    await other.locator('.qad-trigger').click()
    await other.locator('.qad-menu [aria-label="New customer"]').click()
    await other.locator('.fi-modal-window input').first().fill('Polled customer')
    await other.locator('.fi-modal-window').getByRole('button', { name: 'Submit' }).click()
    await expect(other.locator('.fi-no-notification')).toBeVisible()
    await other.close()

    await expect.poll(async () => Number(await badge.textContent()), { timeout: 8_000 }).toBeGreaterThan(before)
})

test('the menu opens downward when the dock sits near the top', async ({ page }) => {
    await page.addInitScript((key) => localStorage.setItem(key, JSON.stringify({ x: 'end', xf: 0, y: 'top', yf: 0 })), POSITION_KEY)

    const root = await visit(page)

    await trigger(page).click()

    await expect(root).toHaveAttribute('data-direction', 'down')

    const triggerBox = await box(trigger(page))
    const menuBox = await box(page.locator('.qad-menu'))

    expect(menuBox.y).toBeGreaterThan(triggerBox.bottom)
    expect(menuBox.bottom).toBeLessThanOrEqual(800)
})
