import { expect, signedInUrl, test, visit } from './helpers.mjs'

test('the dock never appears on the login page', async ({ page }) => {
    await page.goto('/admin/login')

    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('.qad-root')).toHaveCount(0)
})

test('the dock is absent on an excluded path and present elsewhere', async ({ page }) => {
    await visit(page)
    await expect(page.locator('.qad-root')).toBeVisible()

    await page.goto('/admin/settings')
    await expect(page.locator('.fi-header-heading')).toHaveText('Settings')
    await expect(page.locator('.qad-root')).toHaveCount(0)

    await page.goto('/admin/customers')
    await expect(page.locator('.qad-root')).toBeVisible()
})

test.describe('phones', () => {
    test.use({ viewport: { width: 375, height: 740 }, hasTouch: true, isMobile: true })

    test('hiddenOnMobile hides the dock below the breakpoint only', async ({ page }) => {
        await page.goto(signedInUrl({ mobile: 'hidden' }))

        await expect(page.locator('.qad-root')).toBeHidden()

        await page.setViewportSize({ width: 1024, height: 740 })
        await expect(page.locator('.qad-root')).toBeVisible()
    })
})
