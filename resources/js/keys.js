const KEY_ALIASES = { space: ' ', esc: 'escape', plus: '+', period: '.', slash: '/' }
const APPLE_SYMBOLS = { meta: '⌘', ctrl: '⌃', alt: '⌥', shift: '⇧' }
const KEY_NAMES = { ' ': 'Space', escape: 'Esc', arrowup: '↑', arrowdown: '↓', arrowleft: '←', arrowright: '→', enter: '↵' }

export function parseBinding(binding, isApple) {
    const parts = binding.toLowerCase().split('+')
    const key = parts.pop() || '+'

    return {
        key: KEY_ALIASES[key] ?? key,
        meta: parts.includes('meta') || parts.includes('command') || parts.includes('cmd') || (parts.includes('mod') && isApple),
        ctrl: parts.includes('ctrl') || (parts.includes('mod') && !isApple),
        alt: parts.includes('alt') || parts.includes('option'),
        shift: parts.includes('shift'),
    }
}

export function matchesBinding(event, binding) {
    return event.key.toLowerCase() === binding.key
        && event.metaKey === binding.meta
        && event.ctrlKey === binding.ctrl
        && event.altKey === binding.alt
        && event.shiftKey === binding.shift
}

export function formatBinding(binding, isApple) {
    const parsed = parseBinding(binding, isApple)
    const key = KEY_NAMES[parsed.key] ?? (parsed.key.length === 1 ? parsed.key.toUpperCase() : parsed.key[0].toUpperCase() + parsed.key.slice(1))
    const modifiers = ['ctrl', 'alt', 'shift', 'meta'].filter((modifier) => parsed[modifier])

    if (isApple) {
        return modifiers.map((modifier) => APPLE_SYMBOLS[modifier]).join('') + key
    }

    const names = { ctrl: 'Ctrl', alt: 'Alt', shift: 'Shift', meta: 'Win' }

    return [...modifiers.map((modifier) => names[modifier]), key].join('+')
}
