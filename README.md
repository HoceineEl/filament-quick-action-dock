<p class="filament-hidden"><img src="https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/cover.jpg" alt="Quick Action Dock"></p>

# Quick Action Dock for Filament

A floating launcher for the actions people reach for most, available on every page of a Filament 4 or 5 panel.

Admin panels collect a handful of actions that people need all the time and that never have a natural home: "new customer", "new invoice", "open the inbox", "export this month". Putting them in the navigation buries them; putting them in every page header repeats them. Quick Action Dock puts them in one small button in the corner of the screen. Click it, or press `⌘.` / `Ctrl+.`, pick an action, and you are back to what you were doing.

Every item in the dock is a real Filament `Action`. Forms, modals, `requiresConfirmation()`, `authorize()`, `url()`, `badge()`, `color()`, `keyBindings()` and notifications behave exactly as they do anywhere else in Filament, so there is no new action API to learn.

![The speed dial open on a Filament customer list](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/speed-dial.png)

What you get:

- Two layouts: a speed dial (default) and a horizontal bar with tooltips or permanent labels.
- Four flat styles (Native, Outline, Contrast, Tinted), each designed for light and dark mode, all built on CSS custom properties.
- Labelled groups, live badges with optional polling, and key binding hints next to every label.
- Drag and drop with mouse, touch or pen, magnetic edges, and a position remembered per panel and per user.
- Rules for who sees the dock and where: guards, Gate abilities, roles, closures, route names, URL paths, resources, pages, auth pages and device size.
- `DockContext`, so actions can know which page and which record the user is looking at.
- Keyboard support, screen reader semantics, right-to-left layouts, reduced motion and print hiding.
- Translations for 23 languages.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [Actions](#actions)
  - [Defining actions](#defining-actions)
  - [Groups](#groups)
  - [URL actions and modal actions](#url-actions-and-modal-actions)
  - [Confirmation](#confirmation)
  - [Badges](#badges)
  - [Colors and icons](#colors-and-icons)
  - [Key bindings on actions](#key-bindings-on-actions)
  - [Hidden, disabled and authorized actions](#hidden-disabled-and-authorized-actions)
  - [Context-aware actions with DockContext](#context-aware-actions-with-dockcontext)
- [Layouts](#layouts)
- [Styles](#styles)
- [Position, dragging and the remembered spot](#position-dragging-and-the-remembered-spot)
- [Visibility: who and where](#visibility-who-and-where)
  - [For whom](#for-whom)
  - [On which pages](#on-which-pages)
  - [On which devices](#on-which-devices)
  - [How the rules combine](#how-the-rules-combine)
- [Keyboard](#keyboard)
- [Accessibility](#accessibility)
- [Mobile](#mobile)
- [Right-to-left](#right-to-left)
- [Translations](#translations)
- [Theming](#theming)
- [Multiple panels and tenants](#multiple-panels-and-tenants)
- [Plugin API reference](#plugin-api-reference)
- [FAQ and troubleshooting](#faq-and-troubleshooting)
- [Testing your dock](#testing-your-dock)
- [Contributing](#contributing)
- [Works well with](#works-well-with)
- [Changelog](#changelog)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Requirements

| Dependency | Version |
| --- | --- |
| PHP | 8.3 or newer |
| Laravel | 11, 12 or 13 (CI runs against 12 and 13) |
| Filament | 4.x or 5.x |

These come from the package's `composer.json`. The test suite runs on PHP 8.3 and 8.4 against every combination of Laravel 12 and 13 with Filament 4 and 5.

## Installation

Install the package with Composer:

```bash
composer require hoceineel/filament-quick-action-dock
```

Publish the dock's compiled JavaScript and CSS to `public/`:

```bash
php artisan filament:assets
```

The dock ships its own pre-built CSS and JavaScript and registers them with Filament's asset manager, so you do not need a custom Filament theme, a Tailwind `@source` entry or a Vite build step. If your application's `composer.json` runs `@php artisan filament:upgrade` in `post-autoload-dump` (the default for Filament apps), the assets are republished on every `composer update`, and you only run `filament:assets` by hand once. If you deploy without that hook, add `php artisan filament:assets` to your deploy script.

Then register the plugin on a panel, as shown in the next section. The plugin does nothing until it is added to a panel.

## Quick start

Add the plugin to your panel provider and give it some actions:

```php
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            QuickActionDockPlugin::make()
                ->actions([
                    Action::make('newCustomer')
                        ->label('New customer')
                        ->icon(Heroicon::UserPlus)
                        ->keyBindings(['mod+shift+c'])
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('email')->email(),
                        ])
                        ->action(fn (array $data) => Customer::create($data)),

                    Action::make('customers')
                        ->label('Customers')
                        ->icon(Heroicon::Users)
                        ->url(fn (): string => CustomerResource::getUrl()),

                    Action::make('docs')
                        ->label('Documentation')
                        ->icon(Heroicon::BookOpen)
                        ->url('https://filamentphp.com/docs', shouldOpenInNewTab: true),
                ]),
        );
}
```

Sign in to the panel. A round button appears in the bottom corner of every page. Click it or press `⌘.` on macOS (`Ctrl+.` elsewhere) to open the dock.

![The closed dock in the corner of a Filament page](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/closed.png)

## How it works

- The plugin adds a render hook at `PanelsRenderHook::BODY_END` of the panel it is registered on. The hook mounts a Livewire component (`quick-action-dock`) that renders the dock and Filament's action modals.
- The component implements Filament's `HasActions` and `HasSchemas`, so actions are mounted, validated and run by Filament itself.
- When the dock first renders, it records the page it was rendered on (route name, route parameters and URL path) in locked Livewire properties. On every later Livewire request it restores that page into `DockContext` before building actions, which is what makes page and record awareness work on `livewire/update` requests.
- Visibility rules are evaluated on the server on every render and every request. When the dock is hidden, its actions are not registered at all, so they cannot be called.
- A tiny inline script places the dock at its remembered position before the page paints, so the dock never flashes in the default corner and then jumps.
- If no action is visible for the current user and page, nothing is rendered.

## Actions

### Defining actions

`actions()` takes an array of `Filament\Actions\Action` objects, or a closure that returns one:

```php
QuickActionDockPlugin::make()
    ->actions([
        Action::make('newInvoice')->icon(Heroicon::DocumentPlus)->url(fn (): string => InvoiceResource::getUrl('create')),
    ]);

QuickActionDockPlugin::make()
    ->actions(fn (): array => [
        Action::make('newInvoice')->icon(Heroicon::DocumentPlus)->url(fn (): string => InvoiceResource::getUrl('create')),
    ]);
```

The closure form is evaluated on each request, so the list can depend on the user, the tenant or the page. Use it whenever you call something that needs the panel to be booted (for example `Resource::getUrl()`), or wrap those calls in closures on the action itself as above.

Things to know:

- Action names must be unique across the whole dock, including across groups. Filament resolves mounted actions by name, and the dock keys its items by name, so a duplicate replaces the earlier one.
- Only `Action` instances are supported. `ActionGroup` is not.
- An action without an icon gets `Heroicon::Bolt`, because the dock is icon-first.
- The action's label is used for the label chip, the tooltip, `aria-label` and `title`.
- Each call to `actions()` or `group()` adds a section; sections appear in the order you add them.

### Groups

`group()` adds a labelled section. The label is a string or a closure, and the actions are an array or a closure:

```php
QuickActionDockPlugin::make()
    ->actions([
        Action::make('newCustomer')->icon(Heroicon::UserPlus)->schema([/* ... */])->action(/* ... */),
        Action::make('newInvoice')->icon(Heroicon::DocumentPlus)->url(/* ... */),
    ])
    ->group('Tools', [
        Action::make('sendNewsletter')->icon(Heroicon::PaperAirplane)->requiresConfirmation()->action(/* ... */),
        Action::make('exportCustomers')->icon(Heroicon::ArrowDownTray)->action(/* ... */),
    ])
    ->group(fn (): string => __('Help'), fn (): array => [
        Action::make('documentation')->icon(Heroicon::BookOpen)->url('https://filamentphp.com/docs', shouldOpenInNewTab: true),
    ]);
```

In the speed dial a labelled group shows a small heading chip. In the bar, groups are separated by a thin divider. A group whose actions are all hidden is left out entirely, heading included.

### URL actions and modal actions

Both kinds work the same way as in any Filament page:

```php
// Navigates in the same tab
Action::make('customers')->url(fn (): string => CustomerResource::getUrl());

// Opens a new tab
Action::make('docs')->url('https://filamentphp.com/docs', shouldOpenInNewTab: true);

// Opens a modal with a form, then runs the closure
Action::make('newCustomer')
    ->schema([TextInput::make('name')->required()])
    ->action(fn (array $data) => Customer::create($data));

// Runs immediately, no modal
Action::make('clearCache')
    ->action(function (): void {
        cache()->flush();
        Notification::make()->title('Cache cleared')->success()->send();
    });
```

Modal actions use Filament's normal modal, with validation, `modalHeading()`, `modalWidth()`, `slideOver()`, `successNotificationTitle()` and the rest. The speed dial closes when an item runs and when a Filament modal opens.

![A form modal opened from the dock](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/form-modal.png)

### Confirmation

```php
Action::make('sendNewsletter')
    ->label('Send newsletter')
    ->icon(Heroicon::PaperAirplane)
    ->requiresConfirmation()
    ->modalHeading('Send the newsletter?')
    ->modalDescription('It goes to 1,284 subscribers.')
    ->action(fn () => Newsletter::send());
```

![A confirmation modal opened from the dock](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/confirmation.png)

### Badges

Use Filament's own `badge()` and `badgeColor()`. On `Action`, `badge()` takes a string or a closure, so return a string (or `null` to hide it) from the closure:

```php
Action::make('inbox')
    ->icon(Heroicon::Inbox)
    ->url(fn (): string => TicketResource::getUrl())
    ->badge(fn (): ?string => ($count = Ticket::open()->count()) ? (string) $count : null)
    ->badgeColor('danger');
```

Badges are recalculated whenever the dock re-renders, which happens after any dock action runs. To keep them fresh while the user stays on a page, turn on polling:

```php
QuickActionDockPlugin::make()->pollBadges();           // every 30 seconds
QuickActionDockPlugin::make()->pollBadges('10s');      // any Livewire poll interval
QuickActionDockPlugin::make()->pollBadges(fn (): ?string => auth()->user()->isSupport() ? '15s' : null);
```

Polling is off by default. Passing `null` turns it off. The interval is passed to Livewire's `wire:poll`, so each poll is one small request that re-renders only the dock.

![Badges and key binding hints next to dock items](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/badges-and-shortcuts.png)

### Colors and icons

`->icon()` accepts anything Filament accepts: a `Heroicon` case, another `BackedEnum` icon, or an icon name string. `->color()` works too:

```php
Action::make('delete')->icon(Heroicon::Trash)->color('danger');
```

In the Native and Outline styles, items without a colour are drawn in a neutral gray that turns primary on hover, and items with their own colour keep it. Contrast and Tinted draw every icon in the style's own ink colour (set by `--qad-item-fg`), so the dock stays monochrome; see [Theming](#theming) to change that.

### Key bindings on actions

Any action's `->keyBindings()` works while the dock is open or closed, and while it is hidden by a device rule, because the dock is still on the page:

```php
Action::make('newCustomer')->keyBindings(['mod+shift+c']);
```

The first binding is shown as a hint in the label chip and the bar tooltip, formatted for the user's platform (`⇧⌘C` on Apple devices, `Ctrl+Shift+C` elsewhere), and exposed to assistive technology with `aria-keyshortcuts`. The [Keyboard](#keyboard) section covers the dock's own toggle shortcut.

### Hidden, disabled and authorized actions

The dock respects Filament's visibility and authorization on each action:

```php
Action::make('reports')->visible(fn (): bool => auth()->user()->isManager());
Action::make('billing')->hidden(fn (): bool => ! Feature::active('billing'));
Action::make('newCustomer')->authorize('create', Customer::class);
Action::make('archive')->disabled();
```

- Hidden actions, including actions the user is not authorized to run, are left out of the dock. Use Filament's `authorizationTooltip()` if you prefer an unauthorized action to stay visible and disabled.
- Disabled actions stay in the dock, look inert and are skipped by arrow-key navigation.
- Authorization is enforced again on the server when the action runs, as everywhere in Filament.

### Context-aware actions with DockContext

The dock is a Livewire component, so after the first page load its requests go to `livewire/update`, not to the page's own route. Inside dock closures, `request()->routeIs(...)` and `request()->route('record')` therefore describe the Livewire endpoint, not the page. `DockContext` remembers the page the dock was rendered on and gives you the same information on every request:

```php
use HoceineEl\QuickActionDock\Support\DockContext;

Action::make('reports')
    ->visible(fn (): bool => DockContext::routeIs('filament.admin.pages.*'));

Action::make('settingsHelp')
    ->visible(fn (): bool => DockContext::pathIs('admin/settings/*'));

// Only on the customer resource, any page
Action::make('importCustomers')
    ->visible(DockContext::resource(CustomerResource::class));

// Only on its view and edit pages, acting on the open record
Action::make('emailCustomer')
    ->label('Email customer')
    ->icon(Heroicon::Envelope)
    ->visible(DockContext::resource(CustomerResource::class, pages: ['view', 'edit']))
    ->action(fn () => Mail::to(DockContext::record())->send(new Hello()));
```

![A record group that appears only on the customer edit page](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/context.png)

| Helper | Returns |
| --- | --- |
| `DockContext::routeIs(string ...$patterns)` | `bool`. `Str::is()` wildcard match against the page's route name. |
| `DockContext::pathIs(string ...$patterns)` | `bool`. Wildcard match against the page's URL path; leading and trailing slashes are ignored. |
| `DockContext::resource(string $resource, ?array $pages = null)` | A `Closure(): bool` for `->visible()`. `$pages` are the keys from the resource's `getPages()` (`'index'`, `'create'`, `'edit'`, `'view'`, ...). |
| `DockContext::record()` | The current `Model` on resource pages that use `InteractsWithRecord` (`EditRecord`, `ViewRecord`, ...), or `null`. Resolved once per request through the resource's own route binding. |
| `DockContext::current()` | The context object, with `getRouteName()`, `getRouteParameters()`, `getPath()` and `getPageClass()`. |

## Layouts

```php
use HoceineEl\QuickActionDock\Enums\DockLayout;

QuickActionDockPlugin::make()->layout(DockLayout::SpeedDial); // default
QuickActionDockPlugin::make()->layout(DockLayout::Bar);
```

**Speed dial.** A round trigger opens a vertical stack of round buttons, each with a label chip. In the lower half of the screen the stack opens upward, in the upper half downward, and the chips sit on whichever side has more room. It closes on `Escape`, an outside click, after an item runs, and when a Filament modal opens. The trigger icon turns into a close icon while open.

| Light | Dark |
| --- | --- |
| ![Speed dial in light mode](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/speed-dial.png) | ![Speed dial in dark mode](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/speed-dial-dark.png) |

**Bar.** A horizontal pill of icon buttons, always open, with a tooltip above each item on hover or focus. The handle at the end collapses the bar to a single button; the collapsed state is remembered per panel and user in `localStorage`.

![The bar layout with a tooltip showing a key binding](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/bar.png)

**Labels.** `showLabels()` replaces the bar's tooltips with permanent labels under each icon. It has no effect on the speed dial, which always shows labels.

```php
QuickActionDockPlugin::make()
    ->layout(DockLayout::Bar)
    ->showLabels();
```

![The bar layout with permanent labels](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/bar-labels.png)

Below the mobile breakpoint (640px by default) the bar falls back to the speed dial, because a row of icons does not fit a phone screen.

The trigger can be customised:

```php
QuickActionDockPlugin::make()
    ->triggerIcon(Heroicon::Bolt)        // default Heroicon::Plus
    ->triggerLabel(__('Shortcuts'));     // default "Quick actions", translated
```

`triggerIcon()` accepts a string, a `BackedEnum` icon, an `Htmlable` or a closure. `triggerLabel()` sets the trigger's accessible name and tooltip.

## Styles

```php
use HoceineEl\QuickActionDock\Enums\DockStyle;

QuickActionDockPlugin::make()->style(DockStyle::Outline);
```

All four styles are flat and solid, with no gradients or blur, and each has its own light and dark variant that follows Filament's dark mode.

| Style | Look |
| --- | --- |
| `DockStyle::Native` (default) | Matches the panel: white or gray-900 surfaces, a hairline border and a small neutral drop shadow. |
| `DockStyle::Outline` | Surfaces filled with the page background (gray-50 or gray-950) and a crisp 1px gray border, with no shadow. |
| `DockStyle::Contrast` | An inverted command-bar look: gray-950 in light mode, white in dark mode, monochrome icons. |
| `DockStyle::Tinted` | Primary-50 or primary-950 surfaces, primary icons, rounded-square buttons and squarer chips. |

Speed dial, light mode (Native, Outline, Contrast, Tinted):

![The four styles of the speed dial in light mode](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/styles-speed-dial-light.png)

Speed dial, dark mode:

![The four styles of the speed dial in dark mode](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/styles-speed-dial-dark.png)

Bar, light and dark:

![The four styles of the bar in light and dark mode](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/styles-bar.png)

The trigger always uses the panel's primary colour. Every colour, radius and shadow is a CSS custom property; see [Theming](#theming).

## Position, dragging and the remembered spot

### Starting position

```php
use HoceineEl\QuickActionDock\Enums\DockPosition;

QuickActionDockPlugin::make()
    ->position(DockPosition::BottomEnd)   // default
    ->offset(24);                         // distance from the viewport edges in px, default 24
```

| Case | Where |
| --- | --- |
| `DockPosition::BottomEnd` | Bottom right in left-to-right panels, bottom left in right-to-left panels. |
| `DockPosition::BottomStart` | Bottom left in left-to-right panels, bottom right in right-to-left panels. |
| `DockPosition::BottomCenter` | Bottom centre. |

`defaultPosition()` is an alias of `position()`. The position you set is where the dock starts for a user who has never moved it, and where **Reset position** puts it back.

### Dragging

`draggable()` is on by default. Users can drag the dock anywhere:

- With a mouse or pen, drag the trigger in the speed dial, or the bar's background or handle. A movement shorter than a few pixels still counts as a click.
- On touch screens, press and hold for about half a second. The dock lifts (with a short vibration where the device supports it), and the next movement drags it. A normal swipe scrolls the page as usual, and releasing a long press without moving does not open the menu.
- `Escape` cancels a drag in progress and puts the dock back.
- The dock is kept inside the viewport and away from the notch and home indicator.

![The dock being dragged across the page](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/drag.png)

`snapToEdges()` is also on by default: dropping the dock within 32px of an edge or corner snaps it there.

### Moving without a pointer

- Focus the trigger and press `Alt` with an arrow key to move the dock in 24px steps.
- Open the menu and choose **Move dock**. A small map of the screen shows the four corners, the current corner highlighted, and a **Reset position** button. Arrow keys move between the corners as they are laid out on screen.
- In the bar, double-clicking the handle resets the position.

![The Move dock panel with four corners and a reset button](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/move-panel.png)

The corners are physical (top left, top right, and so on) and are translated to the page direction when you pick one, so the label, the arrow and where the dock lands always agree in both directions and in every locale.

### The remembered position

`rememberPosition()` is on by default. The position is saved in the browser's `localStorage` under:

```
qad:{panel id}:{user hash}:position
```

The bar's collapsed state is saved under `qad:{panel id}:{user hash}:collapsed`. The user hash is the first 16 characters of an HMAC-SHA256 of the user id keyed with your `APP_KEY`, so the raw id is never written to the browser; guests use `guest`. Each panel and each user on a shared browser therefore get their own spot.

The position is stored relative to the nearest horizontal edge (start or end) and vertical edge (top or bottom), as a fraction of the viewport, so it survives window resizes, phone rotation and switching between left-to-right and right-to-left.

To reset it, use **Move dock** then **Reset position**, double-click the bar handle, or remove the key from `localStorage`. To stop remembering it, or to stop dragging entirely:

```php
QuickActionDockPlugin::make()
    ->rememberPosition(false)   // dragging still works, but every page load starts at position()
    ->snapToEdges(false)
    ->draggable(false);         // fixed at position(); the Move dock item is removed
```

## Visibility: who and where

Every method in this section accepts a closure, and every rule is checked on the server for the page the dock was rendered on, also during Livewire update requests. When a rule hides the dock, nothing is rendered and no action is registered, so a hidden dock's actions cannot be called.

### For whom

```php
use Illuminate\Contracts\Auth\Authenticatable;

QuickActionDockPlugin::make()
    ->authenticatedOnly()   // default
    ->guard('admin')
    ->visibleFor(fn (?Authenticatable $user): bool => $user?->is_staff === true)
    ->can('useQuickActions')
    ->roles(['admin', 'support'])
    ->visible(fn (): bool => ! app()->isDownForMaintenance());
```

**`authenticatedOnly(bool|Closure $condition = true)`**. On by default: the dock is never rendered for guests. Pass `false` to show it to guests too, for example on a public panel.

**`guard(string|Closure|null $guard)`**. The auth guard used to find the user for all the rules below. By default it is the panel's own guard (`Filament::auth()`).

**`visibleFor(Closure $callback)`**. A closure that receives the user (or `null`) and returns a `bool`. The user is injected by the name `$user` or by the `Authenticatable` type:

```php
->visibleFor(fn (User $user): bool => $user->team->hasFeature('dock'))
```

**`can(string|Closure $ability, mixed $arguments = [])`** and **`authorize(string|bool|Closure|null $ability, mixed $arguments = [])`**. `can()` is an alias of `authorize()`. A string is a Gate ability checked with `Gate::forUser($user)->denies($ability, $arguments)`; the arguments may be a closure too. A closure may return an ability name or a `bool`, and `false` hides the dock:

```php
->can('viewAny', Customer::class)
->authorize(fn (?User $user): bool => $user?->email_verified_at !== null)
```

**`roles(array|string|Closure|null $roles)`**. Shows the dock only when `$user->hasRole($roles)` returns true. This works with `spatie/laravel-permission` and any user model that has a `hasRole()` method, without a hard dependency. When the user model has no such method the dock is hidden.

**`visible(bool|Closure $condition = true)`**. A plain on/off switch for any other condition.

### On which pages

`visibleOn()` is an allow list and `hiddenOn()` is a deny list. Each takes a single rule, an array of rules, or a closure that returns them. A rule is any of:

| Rule | Example | Matches |
| --- | --- | --- |
| Route name pattern | `'filament.admin.resources.orders.*'` | `Str::is()` on the page's route name. |
| URL path pattern (contains `/`) | `'/admin/reports/*'` | `Str::is()` on the URL path; leading and trailing slashes are ignored. |
| Resource class | `OrderResource::class` | Every page of the resource. |
| Resource and page keys | `[OrderResource::class, ['create', 'edit']]` | Only those pages of the resource. |
| Page class | `Settings::class`, `EditOrder::class` | That page and its subclasses. |

```php
QuickActionDockPlugin::make()
    ->visibleOn([OrderResource::class, CustomerResource::class, '/admin/reports/*'])
    ->hiddenOn([
        [OrderResource::class, ['create']],
        Settings::class,
        'filament.admin.pages.billing',
    ]);
```

Rules of thumb:

- An empty allow list (the default) means "every page".
- The deny list wins over the allow list.
- `'admin/reports/*'` does not match `admin/reports` itself. List both if you need both.
- A single resource rule can be passed without the outer array: `->hiddenOn([OrderResource::class, ['create']])`.

**`hiddenOnAuthPages(bool|Closure $condition = true)`** is on by default. It hides the dock on every route of the panel matching `{panel route prefix}.auth.*`: login, registration, password reset and email verification. Combined with `authenticatedOnly()` this means the dock never appears on Filament's sign-in screens, even if you forget to exclude them.

### On which devices

```php
QuickActionDockPlugin::make()
    ->hiddenOnMobile()           // or ->visibleOnMobile(false)
    ->hiddenOnDesktop()
    ->mobileBreakpoint(768);     // default 640
```

- "Mobile" means a viewport narrower than `mobileBreakpoint()` pixels; "desktop" means that width or wider.
- These rules are rendered as CSS media queries, so the browser decides. Nothing is guessed from the user agent, and the dock appears or disappears as the window is resized.
- Because the dock is still in the page, its actions' own key bindings keep working while it is hidden by a device rule.
- The same breakpoint decides when the bar falls back to the speed dial and when the scrim appears.

### How the rules combine

The dock renders only when all of these pass:

1. `visible()` is true.
2. There is a user, unless `authenticatedOnly(false)`.
3. `visibleFor()`, `can()` / `authorize()` and `roles()` pass, in that order.
4. The page is not an auth page (unless `hiddenOnAuthPages(false)`), does not match `hiddenOn()`, and matches `visibleOn()` when one is set.
5. At least one action is visible.

Device rules are applied afterwards in the browser.

## Keyboard

| Key | Does |
| --- | --- |
| `⌘.` / `Ctrl+.` | Opens or closes the dock. In the bar it collapses or expands it. |
| `↑` `↓` (speed dial), `←` `→` (bar) | Move between items. Mirrored in right-to-left layouts. |
| `Home` / `End` | First and last item. |
| `Escape` | Closes the menu and returns focus to the trigger, or cancels a drag. |
| `Tab` | Leaves the menu; the speed dial closes. |
| `Alt` + arrow keys on the trigger | Move the dock 24px. |
| Arrow keys in the Move dock panel | Move between the corners and the reset button as laid out on screen. |
| Each action's own key binding | Runs the action, whether the dock is open or closed. |

Change or remove the toggle shortcut with `keyBindings()`:

```php
QuickActionDockPlugin::make()->keyBindings(['mod+j']);
QuickActionDockPlugin::make()->keyBindings('mod+k');
QuickActionDockPlugin::make()->keyBindings(['mod+j', 'alt+q']);
QuickActionDockPlugin::make()->keyBindings([]);     // no toggle shortcut
```

Bindings use Filament's format: modifiers joined with `+`, followed by a key. `mod` means `⌘` on Apple devices and `Ctrl` elsewhere; `meta` (or `command`, `cmd`), `ctrl`, `alt` (or `option`) and `shift` are also understood, and `space`, `esc`, `plus`, `period` and `slash` name those keys. The toggle is ignored while a Filament modal is open, and bindings without a modifier are ignored while the user types in a field.

## Accessibility

- The trigger is a `<button>` with `aria-haspopup="menu"`, `aria-expanded`, `aria-controls` and `aria-keyshortcuts`. Its label changes to "Close" while the speed dial is open, and to "Hide quick actions" / "Show quick actions" in the bar.
- The menu is `role="menu"` with `role="menuitem"` items, grouped with `role="group"` and labelled by a visually hidden heading.
- Items are icon buttons with an `aria-label` and `title` from the action label, plus `aria-keyshortcuts` when the action has key bindings.
- Focus moves into the menu when it opens and returns to the trigger when it closes. Roving focus keeps the whole dock one `Tab` stop.
- Focus rings are two-tone so they stay visible over any background. Targets are at least 44 by 44 pixels.
- Labels, hints and icons meet WCAG AA contrast in every style, in light and dark mode.
- Dragging always has keyboard alternatives.
- With `prefers-reduced-motion: reduce`, movement is replaced by a short fade.
- The dock is hidden when printing.

## Mobile

Below the mobile breakpoint the dock uses the speed dial whatever the layout, a 30% scrim covers the page while the menu is open, the dock respects safe-area insets, and it hides while a Filament modal is open so it never covers the modal's buttons.

<img src="https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/mobile.png" alt="The speed dial on a phone with a scrim behind it" width="320">

## Right-to-left

The dock follows the page's `dir`. In right-to-left locales (`ar`, `fa`, `he`) it moves to the opposite corner, label chips and tooltips swap sides, arrow keys follow the visual order, and long labels are truncated at their end. Nothing needs configuring.

![The speed dial in an Arabic right-to-left panel](https://raw.githubusercontent.com/HoceineEl/filament-quick-action-dock/main/docs/images/rtl.png)

## Translations

The dock's own strings (trigger label, "Move dock", corner names, "Reset position" and so on) ship in 23 languages:

Arabic (`ar`), Chinese Simplified (`zh_CN`), Chinese Traditional (`zh_TW`), Czech (`cs`), Dutch (`nl`), English (`en`), French (`fr`), German (`de`), Hebrew (`he`), Hindi (`hi`), Indonesian (`id`), Italian (`it`), Japanese (`ja`), Korean (`ko`), Persian (`fa`), Polish (`pl`), Portuguese (`pt`), Portuguese Brazil (`pt_BR`), Russian (`ru`), Spanish (`es`), Turkish (`tr`), Ukrainian (`uk`) and Vietnamese (`vi`).

The locale codes match Filament's, and common words such as "Close" and "Reset" reuse Filament's own wording, so the dock reads like the rest of the panel. The dock follows the application locale. Your action labels are yours to translate, as with any Filament action.

To change the wording, publish the translation files:

```bash
php artisan vendor:publish --tag=quick-action-dock-translations
```

and edit the copies in `lang/vendor/quick-action-dock/{locale}/dock.php`. You only need to keep the keys you change. To rename the trigger on a single panel, `triggerLabel()` is quicker.

The Blade view can also be published with `--tag=quick-action-dock-views`, but a published view no longer receives fixes from package updates, so prefer CSS custom properties and translations where they are enough.

## Theming

The dock uses your panel's `primary` and `gray` palettes and font, so it picks up your brand colours without configuration. Each style is a set of CSS custom properties on `.qad-root`, selected by `[data-qad-style]`. Override them in your panel's theme or any stylesheet loaded after Filament's:

```css
/* every style */
.qad-root {
    --qad-trigger-bg: var(--success-600);
    --qad-trigger-bg-hover: var(--success-700);
}

/* one style */
.qad-root[data-qad-style='tinted'] {
    --qad-radius: 0.75rem;
}

/* dark mode */
.dark .qad-root {
    --qad-surface: var(--gray-800);
}
```

| Property | Controls |
| --- | --- |
| `--qad-surface`, `--qad-fill`, `--qad-border` | Base surface colour, the painted surface colour, hairline border. |
| `--qad-ink`, `--qad-ink-muted` | Main and secondary text colour. |
| `--qad-trigger-bg`, `--qad-trigger-bg-hover`, `--qad-trigger-fg` | The trigger button. |
| `--qad-item-bg`, `--qad-item-fg` | Item buttons. In Contrast and Tinted `--qad-item-fg` replaces every action colour; in Outline it colours actions that have no colour of their own. |
| `--qad-chip-bg`, `--qad-chip-fg` | Label chips and bar tooltips. |
| `--qad-kbd-bg`, `--qad-kbd-fg` | Key binding hints. |
| `--qad-tint`, `--qad-press` | Hover and pressed backgrounds. |
| `--qad-ring` | Focus ring. |
| `--qad-badge-ring` | The surface-coloured gap around badges and focus rings. |
| `--qad-well`, `--qad-selected-bg`, `--qad-selected-fg` | The Move dock map and its selected corner. |
| `--qad-shadow`, `--qad-shadow-lifted`, `--qad-chip-shadow` | Resting, dragging and label chip shadows. |
| `--qad-radius`, `--qad-chip-radius`, `--qad-trigger-radius`, `--qad-bar-radius`, `--qad-panel-radius` | Corner radii of items, chips, trigger, bar and Move dock panel. |
| `--qad-trigger-size`, `--qad-item-size`, `--qad-gap` | Sizes and spacing. Defaults: 3.5rem, 2.75rem, 0.5rem. |
| `--qad-offset` | Distance from the viewport edges. Set from `offset()`. |
| `--qad-scrim` | The mobile scrim colour. |
| `--qad-enter`, `--qad-exit`, `--qad-ease`, `--qad-stagger`, `--qad-shift` | Motion timing and distance. |
| `--qad-z` | Stacking order. 30 by default, so Filament modals stay on top. |

Useful hooks for more specific CSS: `.qad-root[data-mode='speed-dial' | 'bar']`, `[data-open='true']`, `[data-position]`, `[data-dragging]`, `[data-moving]`, `.qad-has-labels`, `.qad-trigger`, `.qad-item`, `.qad-label`, `.qad-group-label` and `.qad-move`.

## Multiple panels and tenants

A plugin instance belongs to one panel. Register the plugin on each panel that should have a dock, with its own actions, rules, layout and style; leave it off a panel to have no dock there. The dock checks that the plugin instance rendering it is the one registered on the current panel, so one panel's dock never appears on another.

```php
// AdminPanelProvider
->plugin(QuickActionDockPlugin::make()->actions([...])->style(DockStyle::Native))

// SupportPanelProvider
->plugin(QuickActionDockPlugin::make()->layout(DockLayout::Bar)->actions([...]))
```

`QuickActionDockPlugin::get()` returns the instance registered on the current panel, which is handy in tests and when reading configuration.

For multi-tenant panels, any rule can use the tenant:

```php
QuickActionDockPlugin::make()
    ->visible(fn (): bool => filament()->getTenant()?->plan === 'pro')
    ->actions(fn (): array => filament()->getTenant()?->quickActions() ?? []);
```

## Plugin API reference

Every setter returns the plugin, so calls chain. Every setter that takes a closure evaluates it with Filament's closure evaluation, on every request.

| Method | Default | Description |
| --- | --- | --- |
| `make()` | | Creates the plugin. |
| `get()` | | The instance registered on the current panel. |
| `actions(array\|Closure $actions)` | | Adds an unlabelled section of actions. |
| `group(string\|Closure $label, array\|Closure $actions)` | | Adds a labelled section of actions. |
| `layout(DockLayout\|Closure $layout)` | `DockLayout::SpeedDial` | Speed dial or bar. |
| `style(DockStyle\|Closure $style)` | `DockStyle::Native` | Native, Outline, Contrast or Tinted. |
| `position(DockPosition\|Closure $position)` | `DockPosition::BottomEnd` | Starting and reset position. |
| `defaultPosition(DockPosition\|Closure $position)` | | Alias of `position()`. |
| `offset(int\|Closure $pixels)` | `24` | Distance from the viewport edges. |
| `draggable(bool\|Closure $condition = true)` | `true` | Allow moving the dock. |
| `snapToEdges(bool\|Closure $condition = true)` | `true` | Snap to an edge or corner within 32px. |
| `rememberPosition(bool\|Closure $condition = true)` | `true` | Save the position in `localStorage`. |
| `triggerIcon(string\|BackedEnum\|Htmlable\|Closure\|null $icon)` | `Heroicon::Plus` | Trigger icon. |
| `triggerLabel(string\|Closure\|null $label)` | "Quick actions" (translated) | Trigger accessible name and tooltip. |
| `keyBindings(array\|string\|Closure\|null $bindings)` | `['mod+.']` | Toggle shortcut(s). `[]` or `null` disables it. |
| `showLabels(bool\|Closure $condition = true)` | `false` | Permanent labels in the bar. |
| `pollBadges(string\|Closure\|null $interval = '30s')` | off | Re-render the dock on an interval. |
| `visible(bool\|Closure $condition = true)` | `true` | General on/off switch. |
| `authenticatedOnly(bool\|Closure $condition = true)` | `true` | Hide from guests. |
| `guard(string\|Closure\|null $guard)` | panel guard | Guard used to resolve the user. |
| `visibleFor(Closure $callback)` | | `fn (?Authenticatable $user): bool`. |
| `authorize(string\|bool\|Closure\|null $ability, mixed $arguments = [])` | | Gate ability or boolean. |
| `can(string\|Closure $ability, mixed $arguments = [])` | | Alias of `authorize()`. |
| `roles(array\|string\|Closure\|null $roles)` | | Require `hasRole()`. |
| `visibleOn(array\|string\|Closure $pages)` | every page | Allow list. |
| `hiddenOn(array\|string\|Closure $pages)` | `[]` | Deny list. Wins over `visibleOn()`. |
| `hiddenOnAuthPages(bool\|Closure $condition = true)` | `true` | Hide on the panel's auth routes. |
| `hiddenOnMobile(bool\|Closure $condition = true)` | `false` | Hide below the breakpoint. |
| `visibleOnMobile(bool\|Closure $condition = true)` | | Inverse of `hiddenOnMobile()`. |
| `hiddenOnDesktop(bool\|Closure $condition = true)` | `false` | Hide at or above the breakpoint. |
| `mobileBreakpoint(int\|Closure $pixels)` | `640` | Width that separates mobile from desktop. |

Getters, mostly useful in tests: `getLayout()`, `getStyle()`, `getPosition()`, `getOffset()`, `isDraggable()`, `shouldSnapToEdges()`, `shouldRememberPosition()`, `getTriggerIcon()`, `getTriggerLabel()`, `getKeyBindings()`, `hasLabels()`, `getBadgePollingInterval()`, `getGroups()`, `getActions()`, `getHiddenOn()`, `getVisibleOn()`, `getGuard()`, `getUser()`, `getRoles()`, `isVisible()`, `isAuthenticatedOnly()`, `isHiddenOnAuthPages()`, `isHiddenOnMobile()`, `isHiddenOnDesktop()`, `getMobileBreakpoint()`, `isVisibleForCurrentUser()`, `isHiddenOnCurrentPage()` and `shouldRender()`.

Enums live in `HoceineEl\QuickActionDock\Enums`:

| Enum | Cases |
| --- | --- |
| `DockLayout` | `SpeedDial`, `Bar` |
| `DockStyle` | `Native`, `Outline`, `Contrast`, `Tinted` |
| `DockPosition` | `BottomEnd`, `BottomStart`, `BottomCenter` |

## FAQ and troubleshooting

**The dock does not appear.** Check, in order:

1. The plugin is registered on the panel you are looking at.
2. You ran `php artisan filament:assets` after installing or updating. Without it the browser gets a 404 for `quick-action-dock.js` and `.css`.
3. You are signed in (`authenticatedOnly()` is on by default) and not on a login or password page (`hiddenOnAuthPages()`).
4. No `visible()`, `visibleFor()`, `can()`, `roles()`, `visibleOn()` or `hiddenOn()` rule excludes you on this page, and no device rule hides it at this window width.
5. At least one action is visible to you. If every action is hidden or unauthorized, nothing renders.

**My `->visible(fn () => request()->routeIs(...))` works on page load but breaks after an action.** Livewire update requests have their own route. Use `DockContext::routeIs()`, `DockContext::pathIs()`, `DockContext::resource()` and `DockContext::record()` in dock closures.

**Two actions with the same name.** Names must be unique across the dock. Give each one a distinct `Action::make()` name.

**Resetting a user's position.** The user can use **Move dock** then **Reset position**, or double-click the bar's handle. To reset it programmatically, remove the `qad:{panel}:{hash}:position` key from `localStorage`. Changing `position()` does not move docks that users have already moved; it changes the starting and reset position.

**The dock covers something on a page.** Users can move it, you can raise `offset()`, hide it on that page with `hiddenOn()`, or switch to `DockPosition::BottomStart` or `BottomCenter`.

**The dock appears above or below another floating widget.** Adjust `--qad-z` (30 by default).

**Content Security Policy.** The dock renders a small inline `<script>` (to place itself before first paint) and, when a device rule is used, an inline `<style>`. Both carry the nonce from `Vite::cspNonce()` when one is set.

**Badges do not update.** Badges update when the dock re-renders. Enable `pollBadges()` for periodic updates.

## Testing your dock

Dock actions are tested like any Filament action, with Livewire's test helpers against the `QuickActionDock` component. The panel that has the plugin must be the current panel, and the user must be signed in:

```php
use Filament\Facades\Filament;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use HoceineEl\QuickActionDock\Support\DockContext;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('creates a customer from the dock', function () {
    Livewire::test(QuickActionDock::class)
        ->callAction('newCustomer', data: ['name' => 'Ada'])
        ->assertHasNoErrors();

    expect(Customer::where('name', 'Ada')->exists())->toBeTrue();
});

it('validates the form', function () {
    Livewire::test(QuickActionDock::class)
        ->callAction('newCustomer', data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);
});

it('shows record actions on the edit page', function () {
    $customer = Customer::factory()->create();

    DockContext::current()->set('filament.admin.resources.customers.edit', ['record' => (string) $customer->getKey()]);

    Livewire::test(QuickActionDock::class)->assertSee('Email customer');
});
```

`DockContext::current()->set($routeName, $parameters, $path)` stands in for the page the dock would have been rendered on.

## Contributing

Pull requests are welcome. To work on the package:

```bash
git clone https://github.com/HoceineEl/filament-quick-action-dock.git
cd filament-quick-action-dock
composer install
npm install
```

| Command | Runs |
| --- | --- |
| `composer test` | The Pest suite (PHP). `vendor/bin/pest --parallel` is faster. |
| `composer analyse` | PHPStan via Larastan. |
| `composer format` | Laravel Pint. |
| `npm run build` | Builds `dist/quick-action-dock.js` and `.css` from `resources/`. |
| `npm test` | JavaScript unit tests for key parsing and positioning. |
| `npm run test:e2e` | The Playwright browser suite. |
| `composer serve` | A Testbench workbench panel to try the dock in a browser. |

**Browser tests.** `npm run test:e2e` builds the assets, prepares the workbench, starts `vendor/bin/testbench serve` on a free port and drives Chromium: first paint without flashes, the menu and keyboard, modal and URL actions, badge polling, mouse and touch dragging, corners in both directions, the bar, mobile, dark mode, reduced motion and all four styles. It uses Playwright's Chromium when installed (`npx playwright install chromium`), otherwise the newest headless shell in the Playwright cache; set `PLAYWRIGHT_CHROMIUM_EXECUTABLE` to use another build.

**Workbench.** `composer serve` starts a panel with sample actions. Sign in as `admin@example.com` with `password`. The Settings page demonstrates `hiddenOn()`. Query parameters switch options and are remembered for the session: `qad-style` (`native`, `outline`, `contrast`, `tinted`), `qad-layout` (`speed-dial`, `bar`), `qad-position` (`bottom-end`, `bottom-start`, `bottom-center`), `qad-locale` (any shipped locale), `qad-dir` (`ltr`, `rtl`), `qad-labels=1`, `qad-poll=5s`, `qad-mobile=hidden` and `qad-demo=1` (the action set used for the screenshots in this README).

**Screenshots.** The images in this README are generated by `tests/e2e/screenshots.spec.mjs`, which is skipped unless `QAD_SCREENSHOTS` is set:

```bash
QAD_SCREENSHOTS=1 npx playwright test --config tests/e2e/playwright.config.mjs screenshots
```

**Translations.** To add a language, copy `resources/lang/en/dock.php` to a folder named after Filament's locale code (the folder names in `vendor/filament/filament/resources/lang`), translate the values and run `composer test`. A test checks that every locale has the same keys and placeholders as English and nothing left untranslated.

## Works well with

- [Undo Toast for Filament](https://github.com/HoceineEl/filament-undo-toast): Gmail-style "Deleted 12 customers, Undo" after destructive actions, including the ones run from the dock.
- [Keyboard Shortcuts for Filament](https://github.com/HoceineEl/filament-keyboard-shortcuts): a `?` cheat sheet that lists every dock item's key binding next to the rest of the page's shortcuts.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what changed in each release.

## Security

Please report vulnerabilities privately, as described in [SECURITY.md](SECURITY.md).

## Credits

- [Hoceine El Idrissi](https://github.com/HoceineEl)
- [All contributors](https://github.com/HoceineEl/filament-quick-action-dock/graphs/contributors)

## License

The MIT License. See [LICENSE.md](LICENSE.md).
