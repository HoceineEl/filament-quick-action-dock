import assert from 'node:assert/strict'
import { test } from 'node:test'
import { clamp, cornerAt, cornerToStored, layoutFor, nudge, presetToStored, resolve, snap, toStored, toTransform } from './position.js'

const viewport = { width: 1000, height: 800 }
const size = { width: 56, height: 56 }
const margins = { top: 24, right: 24, bottom: 24, left: 24 }

test('clamp keeps the dock inside the margins', () => {
    assert.deepEqual(clamp({ x: -50, y: 900 }, size, viewport, margins), { x: 24, y: 720 })
    assert.deepEqual(clamp({ x: 500, y: 300 }, size, viewport, margins), { x: 500, y: 300 })
})

test('clamp pins to the start margin when the dock is wider than the viewport', () => {
    assert.deepEqual(clamp({ x: 300, y: 10 }, { width: 2000, height: 56 }, viewport, margins), { x: 24, y: 24 })
})

test('snap pulls the dock to edges within the threshold', () => {
    assert.deepEqual(snap({ x: 50, y: 700 }, size, viewport, margins, 32), { x: 24, y: 720 })
    assert.deepEqual(snap({ x: 300, y: 300 }, size, viewport, margins, 32), { x: 300, y: 300 })
    assert.deepEqual(snap({ x: 900, y: 40 }, size, viewport, margins, 32), { x: 920, y: 24 })
})

test('toStored records the nearest logical edges as viewport fractions', () => {
    assert.deepEqual(toStored({ x: 100, y: 600 }, size, viewport, 'ltr'), { x: 'start', xf: 0.1, y: 'bottom', yf: 0.18 })
    assert.deepEqual(toStored({ x: 100, y: 600 }, size, viewport, 'rtl'), { x: 'end', xf: 0.1, y: 'bottom', yf: 0.18 })
    assert.deepEqual(toStored({ x: 844, y: 100 }, size, viewport, 'ltr'), { x: 'end', xf: 0.1, y: 'top', yf: 0.125 })
})

test('toStored remembers a centred dock as centred', () => {
    assert.equal(toStored({ x: 472, y: 720 }, size, viewport, 'ltr').x, 'center')
})

test('resolve maps a stored position back and survives a resize', () => {
    const stored = toStored({ x: 844, y: 100 }, size, viewport, 'ltr')

    assert.deepEqual(resolve(stored, size, viewport, margins, 'ltr'), { x: 844, y: 100 })
    assert.deepEqual(resolve(stored, size, { width: 400, height: 300 }, margins, 'ltr'), { x: 304, y: 37.5 })
    assert.deepEqual(resolve(stored, size, { width: 100, height: 100 }, margins, 'ltr'), { x: 24, y: 24 })
})

test('resolve mirrors logical edges in right-to-left layouts', () => {
    assert.deepEqual(resolve({ x: 'start', xf: 0, y: 'bottom', yf: 0 }, size, viewport, margins, 'rtl'), { x: 920, y: 720 })
    assert.deepEqual(resolve({ x: 'end', xf: 0, y: 'top', yf: 0 }, size, viewport, margins, 'rtl'), { x: 24, y: 24 })
    assert.deepEqual(resolve({ x: 'center', xf: 0, y: 'bottom', yf: 0 }, size, viewport, margins, 'rtl'), { x: 472, y: 720 })
})

test('presets and corners become stored positions', () => {
    assert.deepEqual(presetToStored('bottom-end'), { x: 'end', xf: 0, y: 'bottom', yf: 0 })
    assert.deepEqual(presetToStored('bottom-start'), { x: 'start', xf: 0, y: 'bottom', yf: 0 })
    assert.deepEqual(presetToStored('bottom-center'), { x: 'center', xf: 0, y: 'bottom', yf: 0 })
    assert.deepEqual(presetToStored('nonsense'), { x: 'end', xf: 0, y: 'bottom', yf: 0 })
})

test('corners are physical and map to logical edges at runtime', () => {
    assert.deepEqual(cornerToStored('top-left', 'ltr'), { x: 'start', xf: 0, y: 'top', yf: 0 })
    assert.deepEqual(cornerToStored('top-left', 'rtl'), { x: 'end', xf: 0, y: 'top', yf: 0 })
    assert.deepEqual(cornerToStored('bottom-right', 'ltr'), { x: 'end', xf: 0, y: 'bottom', yf: 0 })
    assert.deepEqual(cornerToStored('bottom-right', 'rtl'), { x: 'start', xf: 0, y: 'bottom', yf: 0 })
})

test('a corner stored in one direction lands in the same physical corner', () => {
    for (const dir of ['ltr', 'rtl']) {
        for (const corner of ['top-left', 'top-right', 'bottom-left', 'bottom-right']) {
            const point = resolve(cornerToStored(corner, dir), size, viewport, margins, dir)

            assert.equal(cornerAt(point, size, viewport, margins), corner, `${corner} in ${dir}`)
        }
    }
})

test('cornerAt reports only points pinned to two edges', () => {
    assert.equal(cornerAt({ x: 24, y: 720 }, size, viewport, margins), 'bottom-left')
    assert.equal(cornerAt({ x: 920, y: 24 }, size, viewport, margins), 'top-right')
    assert.equal(cornerAt({ x: 500, y: 720 }, size, viewport, margins), null)
})

test('layoutFor opens toward free space and puts chips on the roomier side', () => {
    assert.deepEqual(layoutFor({ x: 920, y: 720 }, size, viewport, 'ltr'), { direction: 'up', chip: 'start' })
    assert.deepEqual(layoutFor({ x: 24, y: 24 }, size, viewport, 'ltr'), { direction: 'down', chip: 'end' })
    assert.deepEqual(layoutFor({ x: 24, y: 24 }, size, viewport, 'rtl'), { direction: 'down', chip: 'start' })
})

test('nudge moves by a step with physical arrow keys', () => {
    assert.deepEqual(nudge({ x: 100, y: 100 }, 'ArrowLeft', 24), { x: 76, y: 100 })
    assert.deepEqual(nudge({ x: 100, y: 100 }, 'ArrowDown', 24), { x: 100, y: 124 })
    assert.equal(nudge({ x: 100, y: 100 }, 'Enter', 24), null)
})

test('toTransform offsets from the inline-start anchor', () => {
    assert.equal(toTransform({ x: 100, y: 50 }, size, viewport, 'ltr'), 'translate3d(100px, 50px, 0)')
    assert.equal(toTransform({ x: 100, y: 50 }, size, viewport, 'rtl'), 'translate3d(-844px, 50px, 0)')
})
