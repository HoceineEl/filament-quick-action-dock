# Changelog

## 0.2.0 - 2026-10-08

### Changed

- Guests no longer see the dock unless you call `->authenticatedOnly(false)`.

- All styles are now flat and solid: no gradients, blur, rims or inner highlights, just solid fills, a 1px hairline and a small neutral drop shadow. `DockStyle::Glass` was replaced by `DockStyle::Outline`, which fills surfaces with the page background and uses a crisp border with no shadow.
- Every style was reworked as a deliberate design rather than a recolour: Tinted uses rounded squares and squarer chips, and all four share one elevation token, one focus ring and touch-friendly hover handling.
- The **Move dock** panel is a small map of the screen with the four corners, the current corner highlighted, and a labelled reset button.
- Corner buttons are physical (top left, top right, …) and map to the layout direction at runtime, so labels, arrows and the resulting position agree in LTR and RTL whatever the locale. Translation keys `corner_top_start` … `corner_bottom_end` were replaced by `corner_top_left`, `corner_top_right`, `corner_bottom_left` and `corner_bottom_right`; update any published translations.
- `pollBadges()` also accepts a closure.

### Added

- Where and for whom: `authenticatedOnly()` (on by default, so the dock never shows to guests or on the login page), `guard()`, `visibleFor()`, `can()` / `authorize()` with Gate abilities, `roles()` for users with `hasRole()`, `visibleOn()` allow lists, and `hiddenOn()` deny lists that now take route name patterns, URL path patterns, Resource classes, `[Resource::class, ['edit']]` pairs and Page classes. The deny list wins. `hiddenOnAuthPages()` is on by default.
- Devices: `hiddenOnMobile()`, `visibleOnMobile()`, `hiddenOnDesktop()` and `mobileBreakpoint()`, driven by CSS media queries. The breakpoint also controls the bar fallback and the scrim.
- `DockContext` remembers the page's URL path and adds `pathIs()` and `getPageClass()`.

- Label chips and bar tooltips show each action's key binding (`⇧⌘C` on Apple devices, `Ctrl+Shift+C` elsewhere), and items announce it with `aria-keyshortcuts`.
- Touch: a long press lifts the dock so the next small movement drags it, releasing a long press no longer opens the menu, and the system context menu is suppressed while dragging.
- `Escape` cancels a drag in progress and puts the dock back.
- Disabled actions look disabled and are skipped by arrow-key navigation.
- A Playwright browser suite (`npm run test:e2e`) and JavaScript and browser jobs in CI.
- `SECURITY.md` and Dependabot with a cooldown; GitHub Actions are pinned to commit SHAs.
- Translations for 20 more languages, so the dock now ships in `ar`, `cs`, `de`, `en`, `es`, `fa`, `fr`, `he`, `hi`, `id`, `it`, `ja`, `ko`, `nl`, `pl`, `pt`, `pt_BR`, `ru`, `tr`, `uk`, `vi`, `zh_CN` and `zh_TW`, reusing Filament's own terms. A test keeps every locale's keys and placeholders in sync with English.

### Fixed

- The dock stays hidden until it is placed, so a page painted mid-download never shows it at the default corner before it moves to the remembered spot.
- Long labels in right-to-left layouts are truncated at their end, and key hints keep their order.
- Badges no longer show a white box behind the pill on coloured surfaces.
- Hover styles no longer stick after a tap on touch screens.


## 0.1.0 - 2026-10-08

- Floating Quick Action Dock rendered on every page of a panel, built from real Filament actions (forms, modals, confirmation, authorization, URLs, badges, key bindings).
- Speed dial and bar layouts, three default positions, labelled groups, optional badge polling.
- Drag anywhere with Pointer Events, magnetic edges, a position remembered per panel and user, `Alt` + arrow nudging and a Move dock panel with corners and reset.
- Four styles: Native, Glass, Contrast and Tinted, themable with CSS custom properties.
- `DockContext` helpers: `routeIs()`, `resource()` and `record()`, preserved across Livewire update requests.
- Keyboard support: toggle shortcut, roving focus, Escape, Home and End.
- Light and dark, RTL, mobile scrim and safe areas, reduced motion, print hidden.
- English, French and Arabic translations.
