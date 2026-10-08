import { box, expect, modifier, test, visit } from './helpers.mjs'

const focusedLabel = (page) => page.evaluate(() => document.activeElement.getAttribute('aria-label'))

test('the bar collapses to its handle and remembers it across reloads', async ({ page }) => {
    const root = await visit(page, { layout: 'bar' })
    const expanded = await box(root)

    await expect(root).toHaveAttribute('data-mode', 'bar')
    await expect(page.locator('.qad-trigger')).toHaveAttribute('aria-label', 'Hide quick actions')

    await page.locator('.qad-trigger').click()
    await expect(root).toHaveAttribute('data-open', 'false')
    expect((await box(root)).width).toBeLessThan(expanded.width / 3)

    await page.reload()
    await page.waitForFunction(() => document.querySelector('.qad-root')?._x_dataStack)
    await expect(root).toHaveAttribute('data-open', 'false')
    await expect(page.locator('.qad-trigger')).toHaveAttribute('aria-label', 'Show quick actions')

    await page.keyboard.press(`${await modifier(page)}+Period`)
    await expect(root).toHaveAttribute('data-open', 'true')
    expect(await focusedLabel(page)).toBe('Export the quarterly revenue summary for every region')
})

for (const dir of ['ltr', 'rtl']) {
    test(`bar arrow keys follow the visual order in ${dir}`, async ({ page }) => {
        await visit(page, { layout: 'bar', dir, locale: dir === 'rtl' ? 'ar' : 'en' })

        await page.locator('.qad-menu [aria-label="Filament docs"]').focus()

        const next = dir === 'rtl' ? 'ArrowLeft' : 'ArrowRight'
        const docs = await box(page.locator('.qad-menu [aria-label="Filament docs"]'))

        await page.keyboard.press(next)
        expect(await focusedLabel(page)).toBe('Customers')

        const customers = await box(page.locator('.qad-menu [aria-label="Customers"]'))

        expect(dir === 'rtl' ? customers.x < docs.x : customers.x > docs.x).toBe(true)
    })
}

test('the bar shows a tooltip with the shortcut on hover', async ({ page }) => {
    await visit(page, { layout: 'bar' })

    await page.locator('.qad-menu [aria-label="New customer"]').hover()

    const chip = page.locator('.qad-item', { has: page.locator('[aria-label="New customer"]') }).locator('.qad-label')

    await expect(chip).toHaveCSS('opacity', '1')
    await expect(chip.locator('.qad-kbd')).not.toBeEmpty()
    expect((await box(chip)).bottom).toBeLessThan((await box(page.locator('.qad-root'))).y)
})

test('right-to-left panels mirror the dock', async ({ page }) => {
    const root = await visit(page, { dir: 'rtl', locale: 'ar' })

    expect(await box(root)).toMatchObject({ x: 24, y: 720 })

    await page.locator('.qad-trigger').click()

    const trigger = await box(page.locator('.qad-trigger'))
    const chip = await box(page.locator('.qad-label').nth(1))

    expect(chip.x).toBeGreaterThan(trigger.right)
    await expect(page.locator('.qad-trigger')).toHaveAttribute('aria-label', 'إغلاق')
})

test.describe('dark mode', () => {
    test.use({ colorScheme: 'dark' })

    test('uses dark surfaces', async ({ page }) => {
        await visit(page)
        await page.locator('.qad-trigger').click()

        expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true)
        await expect(page.locator('.qad-label').first()).toHaveCSS('color', 'rgb(255, 255, 255)')
    })
})

test.describe('phones', () => {
    test.use({ viewport: { width: 375, height: 740 }, hasTouch: true, isMobile: true })

    test('the bar falls back to the speed dial with a scrim', async ({ page }) => {
        const root = await visit(page, { layout: 'bar' })

        await expect(root).toHaveAttribute('data-mode', 'speed-dial')
        await expect(root).toHaveAttribute('data-open', 'false')

        await page.locator('.qad-trigger').tap()

        await expect(page.locator('.qad-scrim')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0.3)')
        await expect(page.locator('.qad-scrim')).toHaveCSS('opacity', '1')

        for (const label of await page.locator('.qad-menu .qad-label').all()) {
            const rect = await box(label)

            expect(rect.x).toBeGreaterThanOrEqual(0)
            expect(rect.right).toBeLessThanOrEqual(375)
        }

        const target = await box(page.locator('.qad-menu [role="menuitem"]').first())

        expect(target.width).toBeGreaterThanOrEqual(44)
        expect(target.height).toBeGreaterThanOrEqual(44)
    })
})

test.describe('reduced motion', () => {
    test.use({ reducedMotion: 'reduce' })

    test('drops movement and keeps a short fade', async ({ page }) => {
        await visit(page)
        await page.locator('.qad-trigger').click()

        const item = page.locator('.qad-menu .qad-item').first()

        await expect(item).toHaveCSS('transform', 'none')
        expect(parseFloat(await item.evaluate((element) => getComputedStyle(element).transitionDuration))).toBeLessThanOrEqual(0.1)
        await expect(item).toHaveCSS('transition-delay', '0s')
        await expect(page.locator('.qad-trigger-icon')).not.toHaveCSS('transform', 'none')
    })
})

for (const style of ['native', 'outline', 'contrast', 'tinted']) {
    for (const layout of ['speed-dial', 'bar']) {
        test(`${style} ${layout} renders and runs cleanly`, async ({ page }) => {
            const root = await visit(page, { style, layout })

            await expect(root).toHaveAttribute('data-qad-style', style)

            if (layout === 'speed-dial') {
                await page.locator('.qad-trigger').click()
                await page.locator('.qad-move-toggle').click()
                await expect(page.locator('.qad-move')).toBeVisible()
                await page.keyboard.press('Escape')
            } else {
                await page.locator('.qad-menu [aria-label="Customers"]').hover()
            }

            const chip = page.locator('.qad-label').first()

            await expect(chip).toHaveCSS('backdrop-filter', 'none')

            const gradients = await root.evaluate((element) => [element, ...element.querySelectorAll('*')]
                .flatMap((node) => ['', '::before', '::after'].map((pseudo) => getComputedStyle(node, pseudo || null).backgroundImage))
                .filter((image) => image.includes('gradient')))

            expect(gradients).toEqual([])
        })
    }
}
