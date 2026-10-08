import { formatBinding, matchesBinding, parseBinding } from './keys'
import { clamp, cornerAt, cornerToStored, isStored, layoutFor, nudge, presetToStored, resolve, snap, toStored, toTransform } from './position'

const FIELD_SELECTOR = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])'
const DRAG_THRESHOLD = 6
const LIFTED_THRESHOLD = 2
const LONG_PRESS_MS = 450
const SNAP_THRESHOLD = 32
const NUDGE_STEP = 24
const SETTLE_MS = 200
const isApple = /Mac|iPhone|iPad|iPod/i.test(navigator.userAgentData?.platform ?? navigator.platform ?? '')

function openFilamentModal() {
    return [...document.querySelectorAll('[aria-modal="true"]')].find((element) => getComputedStyle(element).display !== 'none')
}

function storage(callback, fallback = null) {
    try {
        return callback(window.localStorage)
    } catch {
        return fallback
    }
}

function keyPrefix(config) {
    return `qad:${config.panel ?? 'default'}:${config.user ?? 'guest'}`
}

function readStored(config) {
    if (config.draggable && config.remember) {
        const saved = storage((store) => JSON.parse(store.getItem(`${keyPrefix(config)}:position`) ?? 'null'))

        if (isStored(saved)) {
            return saved
        }
    }

    return presetToStored(config.defaultPosition)
}

function readCollapsed(config) {
    return storage((store) => store.getItem(`${keyPrefix(config)}:collapsed`) === '1', false)
}

function mobileQuery(config) {
    return `(max-width: ${(config.mobileBreakpoint ?? 640) - 0.02}px)`
}

function modeFor(config, isMobile) {
    return config.layout === 'bar' && !isMobile ? 'bar' : 'speed-dial'
}

function environment(root, config) {
    const probe = root.querySelector('.qad-probe')
    const insets = probe ? getComputedStyle(probe) : null
    const inset = (side) => (insets ? parseFloat(insets[`padding${side}`]) || 0 : 0)

    return {
        dir: getComputedStyle(root).direction === 'rtl' ? 'rtl' : 'ltr',
        viewport: { width: document.documentElement.clientWidth || window.innerWidth, height: window.innerHeight },
        size: { width: root.offsetWidth, height: root.offsetHeight },
        margins: {
            top: config.offset + inset('Top'),
            right: config.offset + inset('Right'),
            bottom: config.offset + inset('Bottom'),
            left: config.offset + inset('Left'),
        },
    }
}

function moveTarget(current, key) {
    const [y, x] = current === 'reset' ? ['reset', 'left'] : current.split('-')
    const flip = { left: 'right', right: 'left' }
    const rows = ['top', 'bottom', 'reset']
    const step = { ArrowUp: -1, ArrowDown: 1 }[key]

    if (key === 'ArrowLeft' || key === 'ArrowRight') {
        return y === 'reset' ? null : `${y}-${flip[x]}`
    }

    if (!step) {
        return null
    }

    const row = rows[(rows.indexOf(y) + step + rows.length) % rows.length]

    return row === 'reset' ? 'reset' : `${row}-${x}`
}

function formatKeys(root) {
    for (const kbd of root.querySelectorAll('.qad-kbd[data-binding]')) {
        kbd.textContent = formatBinding(kbd.dataset.binding, isApple)
    }
}

function applyPoint(root, point) {
    const { dir, viewport, size } = environment(root, { offset: 0 })
    const layout = layoutFor(point, size, viewport, dir)

    root.style.setProperty('--qad-transform', toTransform(point, size, viewport, dir))
    root.setAttribute('data-placed', '')
    root.setAttribute('data-direction', layout.direction)
    root.setAttribute('data-chip', layout.chip)

    return layout
}

export function prePlace(root) {
    if (!root?.classList?.contains('qad-root')) {
        return
    }

    const config = JSON.parse(root.dataset.config ?? '{}')

    const isMobile = window.matchMedia(mobileQuery(config)).matches
    const mode = modeFor(config, isMobile)

    root.setAttribute('data-mode', mode)
    root.toggleAttribute('data-compact', isMobile)

    if (mode === 'bar') {
        root.setAttribute('data-open', readCollapsed(config) ? 'false' : 'true')
    }

    formatKeys(root)

    const { dir, viewport, size, margins } = environment(root, config)

    applyPoint(root, resolve(readStored(config), size, viewport, margins, dir))
}

export default function quickActionDock(config) {
    const prefix = keyPrefix(config)

    return {
        layout: config.layout,
        isOpen: false,
        isCollapsed: false,
        isMobile: false,
        isMoving: false,
        isDragging: false,
        isLifted: false,
        corner: null,
        direction: 'up',
        chip: 'start',
        point: null,
        stored: readStored(config),
        drag: null,
        suppressClick: false,
        bindings: config.keyBindings.map((binding) => parseBinding(binding, isApple)),

        get mode() {
            return this.layout === 'bar' && !this.isMobile ? 'bar' : 'speed-dial'
        },

        get isExpanded() {
            return this.mode === 'bar' ? !this.isCollapsed : this.isOpen
        },

        init() {
            this.isCollapsed = readCollapsed(config)
            this.media = window.matchMedia(mobileQuery(config))
            this.isMobile = this.media.matches
            this.place()

            this.listeners = [
                [this.media, 'change', (event) => {
                    this.isMobile = event.matches
                    this.isOpen = false
                    this.isMoving = false
                }],
                [document, 'keydown', (event) => this.handleGlobalKeydown(event)],
                [window, 'resize', () => this.schedulePlace()],
                [window, 'orientationchange', () => this.schedulePlace()],
                [document, 'livewire:navigated', () => this.schedulePlace()],
                [window, 'pointermove', (event) => this.onPointerMove(event)],
                [window, 'pointerup', (event) => this.onPointerUp(event)],
                [window, 'pointercancel', (event) => this.onPointerUp(event, true)],
            ]

            for (const [target, name, handler] of this.listeners) {
                target.addEventListener(name, handler)
            }

            this.$watch('mode', () => this.$nextTick(() => {
                this.syncTabStops()
                this.place()
            }))
            this.$watch('isCollapsed', () => this.$nextTick(() => this.place()))

            if (window.Alpine?.store('sidebar')) {
                this.$watch('$store.sidebar.isOpen', () => this.schedulePlace())
            }

            this.removeCommitHook = window.Livewire?.hook?.('commit', ({ succeed }) => {
                succeed(() => queueMicrotask(() => {
                    formatKeys(this.$root)
                    this.syncTabStops()
                    this.place()
                }))
            })

            formatKeys(this.$root)
            this.syncTabStops()
        },

        destroy() {
            for (const [target, name, handler] of this.listeners ?? []) {
                target.removeEventListener(name, handler)
            }

            this.removeCommitHook?.()
            cancelAnimationFrame(this.frame)
            clearTimeout(this.pressTimer)
        },

        env() {
            return environment(this.$root, config)
        },

        dir() {
            return this.env().dir
        },

        viewport() {
            return this.env().viewport
        },

        size() {
            return this.env().size
        },

        margins() {
            return this.env().margins
        },

        schedulePlace() {
            cancelAnimationFrame(this.frame)
            this.frame = requestAnimationFrame(() => this.place())
        },

        place(animate = false) {
            if (this.isDragging) {
                return
            }

            this.apply(resolve(this.stored, this.size(), this.viewport(), this.margins(), this.dir()), animate)
        },

        apply(point, animate = false) {
            const root = this.$root

            if (animate) {
                root.setAttribute('data-settling', '')
                clearTimeout(this.settleTimer)
                this.settleTimer = setTimeout(() => root.removeAttribute('data-settling'), SETTLE_MS)
            }

            this.point = point

            const { direction, chip } = applyPoint(root, point)

            this.direction = direction
            this.chip = chip
            this.corner = cornerAt(point, this.size(), this.viewport(), this.margins())
        },

        updateLayout() {
            if (!this.point) {
                return
            }

            const { direction, chip } = layoutFor(this.point, this.size(), this.viewport(), this.dir())

            this.direction = direction
            this.chip = chip
        },

        commit(point, animate = true) {
            this.apply(point, animate)
            this.stored = toStored(point, this.size(), this.viewport(), this.dir())
            this.save()
        },

        save() {
            if (config.remember) {
                storage((store) => store.setItem(`${prefix}:position`, JSON.stringify(this.stored)))
            }
        },

        isDragHandle(target) {
            if (target.closest('.qad-trigger')) {
                return true
            }

            return this.mode === 'bar' && !target.closest('.qad-item, .qad-move')
        },

        onPointerDown(event) {
            if (!config.draggable || !event.isPrimary || event.button !== 0 || !this.point || !this.isDragHandle(event.target)) {
                return
            }

            this.drag = { id: event.pointerId, x: event.clientX, y: event.clientY, origin: { ...this.point }, target: event.target, moved: false }

            if (event.pointerType !== 'mouse') {
                clearTimeout(this.pressTimer)
                this.pressTimer = setTimeout(() => this.lift(), LONG_PRESS_MS)
            }
        },

        lift() {
            if (!this.drag || this.drag.moved) {
                return
            }

            this.isLifted = true
            navigator.vibrate?.(8)
        },

        onContextMenu(event) {
            if (this.drag || this.isLifted) {
                event.preventDefault()
            }
        },

        onPointerMove(event) {
            const drag = this.drag

            if (!drag || event.pointerId !== drag.id) {
                return
            }

            const dx = event.clientX - drag.x
            const dy = event.clientY - drag.y

            if (!drag.moved) {
                if (Math.hypot(dx, dy) < (this.isLifted ? LIFTED_THRESHOLD : DRAG_THRESHOLD)) {
                    return
                }

                clearTimeout(this.pressTimer)
                drag.moved = true
                this.isDragging = true
                this.isOpen = false
                this.isMoving = false
                this.bodyUserSelect = document.body.style.userSelect
                document.body.style.userSelect = 'none'

                try {
                    drag.target.setPointerCapture(event.pointerId)
                } catch {}
            }

            event.preventDefault()
            this.apply(clamp({ x: drag.origin.x + dx, y: drag.origin.y + dy }, this.size(), this.viewport(), this.margins()))
        },

        onPointerUp(event, cancelled = false) {
            const drag = this.drag

            if (!drag && event.pointerId === this.cancelledPointer) {
                this.cancelledPointer = null
                this.suppressNextClick()

                return
            }

            if (!drag || event.pointerId !== drag.id) {
                return
            }

            this.drag = null
            clearTimeout(this.pressTimer)

            const wasLifted = this.isLifted

            this.isLifted = false

            if (!drag.moved) {
                if (wasLifted) {
                    this.suppressNextClick()
                }

                return
            }

            this.isDragging = false
            document.body.style.userSelect = this.bodyUserSelect ?? ''
            this.suppressNextClick()

            const point = cancelled ? drag.origin : this.point
            const settle = config.snap ? snap : clamp

            this.commit(settle(point, this.size(), this.viewport(), this.margins(), SNAP_THRESHOLD))
        },

        suppressNextClick() {
            this.suppressClick = true
            setTimeout(() => (this.suppressClick = false), 0)
        },

        moveTo(corner) {
            this.stored = cornerToStored(corner, this.dir())
            this.save()
            this.place(true)
            this.close()
        },

        resetPosition() {
            this.stored = presetToStored(config.defaultPosition)
            storage((store) => store.removeItem(`${prefix}:position`))
            this.place(true)
            this.close()
        },

        items() {
            return [...(this.$refs.menu?.querySelectorAll('[role="menuitem"]:not(:disabled)') ?? [])]
        },

        syncTabStops(active = null) {
            const items = this.items()
            const current = active ?? items.find((item) => item.tabIndex === 0) ?? items.at(-1)

            for (const item of items) {
                item.tabIndex = this.mode === 'bar' && item === current ? 0 : -1
            }
        },

        focusItem(item) {
            if (!item) {
                return
            }

            this.syncTabStops(item)
            item.focus()
        },

        handleGlobalKeydown(event) {
            if (event.key === 'Escape' && this.drag?.moved) {
                event.preventDefault()
                this.cancelledPointer = this.drag.id
                this.onPointerUp({ pointerId: this.drag.id }, true)

                return
            }

            if (event.defaultPrevented || event.repeat || !this.bindings.some((binding) => matchesBinding(event, binding))) {
                return
            }

            const hasModifier = event.metaKey || event.ctrlKey || event.altKey

            if ((!hasModifier && event.target.closest?.(FIELD_SELECTOR)) || openFilamentModal()) {
                return
            }

            event.preventDefault()

            if (this.mode === 'bar') {
                this.toggle()
                this.$nextTick(() => this.isExpanded && this.focusItem(this.items().at(-1)))

                return
            }

            this.isOpen ? this.close() : this.open()
        },

        onTriggerClick(event) {
            if (this.suppressClick) {
                event.preventDefault()
                this.suppressClick = false

                return
            }

            if (this.isMoving && this.mode === 'speed-dial') {
                this.close()

                return
            }

            this.toggle()
        },

        toggle() {
            if (this.mode === 'bar') {
                this.isCollapsed = !this.isCollapsed
                this.isMoving = false
                storage((store) => store.setItem(`${prefix}:collapsed`, this.isCollapsed ? '1' : '0'))

                return
            }

            this.isOpen ? this.close() : this.open()
        },

        open() {
            this.updateLayout()
            this.isOpen = true
            this.$nextTick(() => this.isOpen && this.focusItem(this.items().at(-1)))
        },

        close(restoreFocus = true) {
            const wasOpen = this.isMoving || (this.mode === 'speed-dial' && this.isOpen)

            this.isMoving = false

            if (this.mode === 'speed-dial') {
                this.isOpen = false
            }

            if (wasOpen && restoreFocus) {
                this.$refs.trigger?.focus()
            }
        },

        openMove() {
            this.updateLayout()
            this.isMoving = true
            this.$nextTick(() => this.isMoving && this.$refs.move?.querySelector('button')?.focus())
        },

        closeMove() {
            this.isMoving = false
            this.$nextTick(() => this.focusItem(this.$refs.menu?.querySelector('.qad-move-toggle')))
        },

        onMoveKeydown(event) {
            if (event.key === 'Escape') {
                event.stopPropagation()
                this.mode === 'speed-dial' ? this.close() : this.closeMove()

                return
            }

            const target = moveTarget(event.target.dataset.corner ?? 'reset', event.key)

            if (target) {
                event.preventDefault()
                event.stopPropagation()
                this.$refs.move.querySelector(target === 'reset' ? '.qad-move-reset' : `[data-corner="${target}"]`)?.focus()
            }
        },

        onKeydown(event) {
            if (event.target === this.$refs.trigger && event.altKey && config.draggable && this.point) {
                const moved = nudge(this.point, event.key, NUDGE_STEP)

                if (moved) {
                    event.preventDefault()
                    this.commit(clamp(moved, this.size(), this.viewport(), this.margins()))

                    return
                }
            }

            const items = this.items()
            const index = items.indexOf(event.target)
            const isRtl = this.dir() === 'rtl'

            if (event.key === 'Escape') {
                if (this.isMoving || (this.mode === 'speed-dial' && this.isOpen)) {
                    event.stopPropagation()
                    this.close()
                } else if (index !== -1) {
                    this.$refs.trigger?.focus()
                }

                return
            }

            if (event.key === 'Tab' && index !== -1 && this.mode === 'speed-dial') {
                this.close(false)

                return
            }

            const forward = this.mode === 'bar' ? (isRtl ? 'ArrowLeft' : 'ArrowRight') : this.direction === 'down' ? 'ArrowUp' : 'ArrowDown'
            const backward = this.mode === 'bar' ? (isRtl ? 'ArrowRight' : 'ArrowLeft') : this.direction === 'down' ? 'ArrowDown' : 'ArrowUp'

            if (event.target === this.$refs.trigger) {
                if (event.key === backward && this.isExpanded) {
                    event.preventDefault()
                    this.focusItem(items.at(-1))
                } else if (event.key === backward && this.mode === 'speed-dial') {
                    event.preventDefault()
                    this.open()
                }

                return
            }

            if (index === -1) {
                return
            }

            const target = {
                [forward]: items[(index + 1) % items.length],
                [backward]: items[(index - 1 + items.length) % items.length],
                Home: items[0],
                End: items.at(-1),
            }[event.key]

            if (target) {
                event.preventDefault()
                this.focusItem(target)
            }
        },

        onClick(event) {
            const label = event.target.closest('.qad-label')

            if (label) {
                label.closest('.qad-item')?.querySelector('[role="menuitem"]')?.click()

                return
            }

            const item = event.target.closest('[role="menuitem"]')

            if (item && !item.classList.contains('qad-move-toggle') && this.mode === 'speed-dial') {
                queueMicrotask(() => this.close())
            }
        },

        onOutsideClick() {
            if (this.isDragging) {
                return
            }

            this.close(false)
        },

        onFocusOut(event) {
            if ((this.isMoving || (this.mode === 'speed-dial' && this.isOpen)) && event.relatedTarget && !this.$root.contains(event.relatedTarget)) {
                this.close(false)
            }
        },
    }
}

window.QuickActionDock = { prePlace }

document.addEventListener('alpine:init', () => window.Alpine.data('quickActionDock', quickActionDock))
