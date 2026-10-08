import { COLLAPSED_KEY, POSITION_KEY, expect, signedInUrl, test } from './helpers.mjs'

async function recordFrames(page, seed = {}) {
    await page.addInitScript((storage) => {
        for (const [key, value] of Object.entries(storage)) {
            localStorage.setItem(key, value)
        }

        window.qadFrames = []

        const sample = () => {
            const root = document.querySelector('.qad-root')

            if (root && root.querySelector('.qad-trigger') && getComputedStyle(root.querySelector('.qad-trigger')).visibility === 'visible') {
                const rect = root.getBoundingClientRect()

                window.qadFrames.push({
                    x: Math.round(rect.x),
                    y: Math.round(rect.y),
                    width: Math.round(rect.width),
                    open: root.dataset.open,
                    menu: getComputedStyle(root.querySelector('.qad-menu')).visibility,
                    display: getComputedStyle(root.querySelector('.qad-menu')).display,
                })
            }

            if (window.qadFrames.length < 90) {
                requestAnimationFrame(sample)
            }
        }

        requestAnimationFrame(sample)
    }, seed)

    const cdp = await page.context().newCDPSession(page)
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 6 })
}

async function framesAfterLoad(page, url) {
    await page.goto(url)
    await page.waitForFunction(() => window.qadFrames.length >= 90, null, { timeout: 20_000 })

    return page.evaluate(() => window.qadFrames)
}

test('the closed speed dial paints in its final spot from the first frame', async ({ page }) => {
    await recordFrames(page)

    const frames = await framesAfterLoad(page, signedInUrl())
    const last = frames.at(-1)

    expect(frames.length).toBeGreaterThan(10)
    expect(frames.every((frame) => frame.x === last.x && frame.y === last.y), JSON.stringify(frames.slice(0, 4))).toBe(true)
    expect(frames.every((frame) => frame.menu === 'hidden')).toBe(true)
})

test('a remembered position is applied before the first paint, also on reload', async ({ page }) => {
    await recordFrames(page, { [POSITION_KEY]: JSON.stringify({ x: 'start', xf: 0.3, y: 'top', yf: 0.25 }) })

    for (const load of [() => page.goto(signedInUrl()), () => page.reload()]) {
        await load()
        await page.waitForFunction(() => window.qadFrames.length >= 90, null, { timeout: 20_000 })

        const frames = await page.evaluate(() => window.qadFrames)

        expect(frames.every((frame) => frame.x === 384 && frame.y === 200), JSON.stringify(frames.slice(0, 3))).toBe(true)
    }
})

test('a collapsed bar stays collapsed from the first frame', async ({ page }) => {
    await recordFrames(page, { [COLLAPSED_KEY]: '1' })

    const frames = await framesAfterLoad(page, signedInUrl({ layout: 'bar' }))
    const last = frames.at(-1)

    expect(last.open).toBe('false')
    expect(frames.every((frame) => frame.open === 'false' && frame.display === 'none' && frame.width === last.width), JSON.stringify(frames.slice(0, 4))).toBe(true)
})
