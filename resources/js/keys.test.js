import assert from 'node:assert/strict'
import { test } from 'node:test'
import { formatBinding, matchesBinding, parseBinding } from './keys.js'

test('mod means command on Apple platforms and control elsewhere', () => {
    assert.deepEqual(parseBinding('mod+.', true), { key: '.', meta: true, ctrl: false, alt: false, shift: false })
    assert.deepEqual(parseBinding('mod+.', false), { key: '.', meta: false, ctrl: true, alt: false, shift: false })
})

test('aliases and a bare plus are understood', () => {
    assert.equal(parseBinding('ctrl+period', false).key, '.')
    assert.equal(parseBinding('shift++', false).key, '+')
    assert.equal(parseBinding('esc', false).key, 'escape')
})

test('a keyboard event matches only its exact modifiers', () => {
    const binding = parseBinding('mod+shift+c', true)

    assert.equal(matchesBinding({ key: 'C', metaKey: true, ctrlKey: false, altKey: false, shiftKey: true }, binding), true)
    assert.equal(matchesBinding({ key: 'c', metaKey: true, ctrlKey: false, altKey: false, shiftKey: false }, binding), false)
})

test('bindings are formatted the way each platform writes shortcuts', () => {
    assert.equal(formatBinding('mod+shift+c', true), '⇧⌘C')
    assert.equal(formatBinding('mod+shift+c', false), 'Ctrl+Shift+C')
    assert.equal(formatBinding('alt+space', false), 'Alt+Space')
    assert.equal(formatBinding('mod+.', true), '⌘.')
})
