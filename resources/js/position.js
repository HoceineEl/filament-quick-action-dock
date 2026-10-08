const PRESETS = {
    'bottom-start': { x: 'start', xf: 0, y: 'bottom', yf: 0 },
    'bottom-center': { x: 'center', xf: 0, y: 'bottom', yf: 0 },
    'bottom-end': { x: 'end', xf: 0, y: 'bottom', yf: 0 },
}

const CENTER_TOLERANCE = 2

const round = (value) => Math.round(value * 10000) / 10000

function bounds(size, viewport, margins) {
    const minX = margins.left
    const minY = margins.top

    return {
        minX,
        minY,
        maxX: Math.max(minX, viewport.width - margins.right - size.width),
        maxY: Math.max(minY, viewport.height - margins.bottom - size.height),
    }
}

export function clamp(point, size, viewport, margins) {
    const { minX, minY, maxX, maxY } = bounds(size, viewport, margins)

    return {
        x: Math.min(Math.max(point.x, minX), maxX),
        y: Math.min(Math.max(point.y, minY), maxY),
    }
}

export function snap(point, size, viewport, margins, threshold) {
    const { minX, minY, maxX, maxY } = bounds(size, viewport, margins)
    const { x, y } = clamp(point, size, viewport, margins)

    return {
        x: x - minX <= threshold ? minX : maxX - x <= threshold ? maxX : x,
        y: y - minY <= threshold ? minY : maxY - y <= threshold ? maxY : y,
    }
}

export function toStored(point, size, viewport, dir) {
    const leftGap = point.x
    const rightGap = viewport.width - point.x - size.width
    const startGap = dir === 'rtl' ? rightGap : leftGap
    const endGap = dir === 'rtl' ? leftGap : rightGap
    const topGap = point.y
    const bottomGap = viewport.height - point.y - size.height

    const x = Math.abs(leftGap - rightGap) <= CENTER_TOLERANCE ? 'center' : startGap <= endGap ? 'start' : 'end'

    return {
        x,
        xf: x === 'center' ? 0 : round(Math.min(startGap, endGap) / viewport.width),
        y: topGap <= bottomGap ? 'top' : 'bottom',
        yf: round(Math.min(topGap, bottomGap) / viewport.height),
    }
}

export function resolve(stored, size, viewport, margins, dir) {
    const xGap = stored.xf * viewport.width
    const yGap = stored.yf * viewport.height
    const fromLeft = (stored.x === 'start') === (dir !== 'rtl')

    const x = stored.x === 'center'
        ? (viewport.width - size.width) / 2
        : fromLeft ? xGap : viewport.width - size.width - xGap

    const y = stored.y === 'top' ? yGap : viewport.height - size.height - yGap

    return clamp({ x, y }, size, viewport, margins)
}

export function presetToStored(position) {
    return { ...(PRESETS[position] ?? PRESETS['bottom-end']) }
}

export function cornerToStored(corner, dir) {
    const [y, side] = corner.split('-')

    return { x: (side === 'left') === (dir !== 'rtl') ? 'start' : 'end', xf: 0, y, yf: 0 }
}

export function cornerAt(point, size, viewport, margins) {
    const { minX, minY, maxX, maxY } = bounds(size, viewport, margins)
    const near = (value, edge) => Math.abs(value - edge) <= 1
    const x = near(point.x, minX) ? 'left' : near(point.x, maxX) ? 'right' : null
    const y = near(point.y, minY) ? 'top' : near(point.y, maxY) ? 'bottom' : null

    return x && y ? `${y}-${x}` : null
}

export function layoutFor(point, size, viewport, dir) {
    const leftRoom = point.x
    const rightRoom = viewport.width - point.x - size.width
    const startRoom = dir === 'rtl' ? rightRoom : leftRoom
    const endRoom = dir === 'rtl' ? leftRoom : rightRoom

    return {
        direction: point.y + size.height / 2 > viewport.height / 2 ? 'up' : 'down',
        chip: startRoom >= endRoom ? 'start' : 'end',
    }
}

export function nudge(point, key, step) {
    const delta = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    }[key]

    return delta ? { x: point.x + delta[0], y: point.y + delta[1] } : null
}

export function toTransform(point, size, viewport, dir) {
    const x = dir === 'rtl' ? point.x + size.width - viewport.width : point.x

    return `translate3d(${x}px, ${point.y}px, 0)`
}

export function isStored(value) {
    return Boolean(value)
        && ['start', 'end', 'center'].includes(value.x)
        && ['top', 'bottom'].includes(value.y)
        && Number.isFinite(value.xf)
        && Number.isFinite(value.yf)
}
