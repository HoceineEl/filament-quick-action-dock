@php
    use Filament\Support\Icons\Heroicon;
    use HoceineEl\QuickActionDock\Enums\DockLayout;
    use HoceineEl\QuickActionDock\Enums\DockPosition;
    use Illuminate\Support\Facades\Vite;

    $layout = $plugin->getLayout();
    $position = $plugin->getPosition();
    $isDraggable = $plugin->isDraggable();
    $pollingInterval = $plugin->getBadgePollingInterval();
    $menuId = $this->getId() . '-menu';
    $headingId = $this->getId() . '-heading';
    $moveId = $this->getId() . '-move';
    $triggerLabel = $plugin->getTriggerLabel();
    $clientConfig = $this->getClientConfig();
    $deviceMedia = $this->getDeviceMediaQuery();
    $nonce = Vite::cspNonce();
    $index = collect($groups)->sum(fn (array $group): int => count($group['actions'])) + ($isDraggable ? 1 : 0);
    $corners = [
        'top-left' => Heroicon::ArrowUpLeft,
        'top-right' => Heroicon::ArrowUpRight,
        'bottom-left' => Heroicon::ArrowDownLeft,
        'bottom-right' => Heroicon::ArrowDownRight,
    ];
@endphp

<div @if ($pollingInterval) wire:poll.{{ $pollingInterval }} @endif>
    @if (filled($groups) && $deviceMedia)
        <style @if ($nonce) nonce="{{ $nonce }}" @endif>@media {{ $deviceMedia }} { [id="{{ $this->getId() }}-dock"] { display: none !important; } }</style>
    @endif

    @if (filled($groups))
        <div
            id="{{ $this->getId() }}-dock"
            wire:ignore.self
            x-data="quickActionDock(@js($clientConfig))"
            data-config="{{ json_encode($clientConfig) }}"
            x-on:keydown="onKeydown($event)"
            x-on:focusout="onFocusOut($event)"
            x-on:click="onClick($event)"
            x-on:click.outside="onOutsideClick($event)"
            x-on:pointerdown="onPointerDown($event)"
            x-on:contextmenu="onContextMenu($event)"
            x-on:x-modal-opened.document="close(false)"
            x-bind:data-mode="mode"
            x-bind:data-open="isExpanded ? 'true' : 'false'"
            x-bind:data-direction="direction"
            x-bind:data-chip="chip"
            x-bind:data-moving="isMoving ? '' : null"
            x-bind:data-dragging="isDragging ? '' : null"
            x-bind:data-lifted="isLifted ? '' : null"
            x-bind:data-compact="isMobile ? '' : null"
            data-layout="{{ $layout->value }}"
            data-mode="{{ $layout->value }}"
            data-open="{{ $layout === DockLayout::Bar ? 'true' : 'false' }}"
            data-position="{{ $position->value }}"
            data-qad-style="{{ $plugin->getStyle()->value }}"
            data-direction="up"
            data-chip="{{ $position === DockPosition::BottomStart ? 'end' : 'start' }}"
            @if ($isDraggable) data-draggable @endif
            @class(['qad-root', 'qad-has-labels' => $plugin->hasLabels()])
            style="--qad-offset: {{ $plugin->getOffset() }}px"
        >
            <h2 id="{{ $headingId }}" class="qad-sr-only">{{ __('quick-action-dock::dock.heading') }}</h2>

            <span class="qad-probe" aria-hidden="true" x-ref="probe"></span>

            <div class="qad-scrim" aria-hidden="true" x-on:click="close()"></div>

            <div
                wire:ignore.self
                id="{{ $menuId }}"
                role="menu"
                aria-labelledby="{{ $headingId }}"
                x-ref="menu"
                x-bind:aria-orientation="mode === 'bar' ? 'horizontal' : 'vertical'"
                class="qad-menu"
            >
                @if ($isDraggable)
                    <div role="group" aria-label="{{ __('quick-action-dock::dock.move') }}" class="qad-group qad-move-group">
                        <div class="qad-item" style="--qad-i: {{ --$index }}">
                            <button
                                type="button"
                                role="menuitem"
                                tabindex="-1"
                                aria-haspopup="true"
                                aria-controls="{{ $moveId }}"
                                aria-label="{{ __('quick-action-dock::dock.move') }}"
                                title="{{ __('quick-action-dock::dock.move') }}"
                                x-bind:aria-expanded="isMoving ? 'true' : 'false'"
                                x-on:click.stop="openMove()"
                                class="fi-icon-btn qad-move-toggle"
                            >
                                <x-filament::icon :icon="Heroicon::ArrowsPointingOut" />
                            </button>

                            <span class="qad-label" aria-hidden="true"><span class="qad-label-text">{{ __('quick-action-dock::dock.move') }}</span></span>
                        </div>
                    </div>
                @endif

                @foreach ($groups as $group)
                    <div
                        role="group"
                        @if (filled($group['label'])) aria-label="{{ $group['label'] }}" @endif
                        class="qad-group"
                    >
                        @if (filled($group['label']))
                            <div class="qad-group-label" aria-hidden="true">{{ $group['label'] }}</div>
                        @endif

                        @foreach ($group['actions'] as $action)
                            <div class="qad-item" style="--qad-i: {{ --$index }}" wire:key="{{ $this->getId() }}.{{ $action->getName() }}">
                                {{ $action }}

                                <span class="qad-label" aria-hidden="true" title="{{ $action->getLabel() }}">
                                    <span class="qad-label-text">{{ $action->getLabel() }}</span>
                                    @if ($binding = $action->getKeyBindings()[0] ?? null)
                                        <kbd wire:ignore class="qad-kbd" data-binding="{{ $binding }}"></kbd>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            @if ($isDraggable)
                <div
                    wire:ignore
                    id="{{ $moveId }}"
                    role="group"
                    aria-labelledby="{{ $moveId }}-title"
                    x-ref="move"
                    x-on:keydown="onMoveKeydown($event)"
                    class="qad-move"
                >
                    <div id="{{ $moveId }}-title" class="qad-move-title">{{ __('quick-action-dock::dock.move_title') }}</div>

                    <div class="qad-move-map">
                        @foreach ($corners as $corner => $icon)
                            @php($label = __('quick-action-dock::dock.corner_' . str_replace('-', '_', $corner)))
                            <button
                                type="button"
                                data-corner="{{ $corner }}"
                                aria-label="{{ $label }}"
                                title="{{ $label }}"
                                x-bind:aria-pressed="corner === @js($corner) ? 'true' : 'false'"
                                x-on:click.stop="moveTo(@js($corner))"
                                class="qad-move-button"
                            >
                                <x-filament::icon :icon="$icon" />
                            </button>
                        @endforeach
                    </div>

                    <button
                        type="button"
                        x-on:click.stop="resetPosition()"
                        class="qad-move-reset"
                    >
                        <x-filament::icon :icon="Heroicon::ArrowUturnLeft" />
                        <span>{{ __('quick-action-dock::dock.reset') }}</span>
                    </button>
                </div>
            @endif

            <button
                wire:ignore
                type="button"
                x-ref="trigger"
                aria-haspopup="menu"
                aria-controls="{{ $menuId }}"
                aria-expanded="false"
                aria-label="{{ $triggerLabel }}"
                title="{{ $triggerLabel }}"
                @if ($shortcuts = $this->getAriaKeyShortcuts()) aria-keyshortcuts="{{ $shortcuts }}" @endif
                @if ($isDraggable) aria-description="{{ __('quick-action-dock::dock.move_hint') }}" @endif
                x-bind:aria-expanded="isExpanded ? 'true' : 'false'"
                x-bind:aria-label="mode === 'bar' ? (isExpanded ? @js(__('quick-action-dock::dock.collapse')) : @js(__('quick-action-dock::dock.expand'))) : (isExpanded ? @js(__('quick-action-dock::dock.close')) : @js($triggerLabel))"
                x-bind:title="$el.getAttribute('aria-label')"
                x-on:click="onTriggerClick($event)"
                x-on:dblclick="mode === 'bar' && resetPosition()"
                class="qad-trigger"
            >
                <x-filament::icon :icon="$plugin->getTriggerIcon()" class="qad-trigger-icon" />
                <x-filament::icon :icon="Heroicon::ChevronDown" class="qad-handle-icon" />
            </button>
        </div>

        <x-filament-actions::modals />
    @endif
</div>
