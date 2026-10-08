<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;
use HoceineEl\QuickActionDock\Support\DockContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuickActionDock extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public ?string $routeName = null;

    /**
     * @var array<string, scalar | null>
     */
    #[Locked]
    public array $routeParameters = [];

    #[Locked]
    public ?string $path = null;

    public function mount(): void
    {
        $context = DockContext::current();

        $this->routeName = $context->getRouteName();
        $this->routeParameters = $context->getRouteParameters();
        $this->path = $context->getPath();

        $this->cacheDockActions();
    }

    public function hydrate(): void
    {
        DockContext::current()->set($this->routeName, $this->routeParameters, $this->path);

        $this->cacheDockActions();
    }

    protected function cacheDockActions(): void
    {
        $plugin = $this->getPlugin();

        if (! $plugin->shouldRender()) {
            return;
        }

        foreach ($plugin->getActions() as $action) {
            $this->cacheAction($action);
        }
    }

    public function getPlugin(): QuickActionDockPlugin
    {
        return QuickActionDockPlugin::get();
    }

    /**
     * @return array<int, array{label: ?string, actions: array<string, Action>}>
     */
    public function getDockGroups(): array
    {
        $plugin = $this->getPlugin();

        if (! $plugin->shouldRender()) {
            return [];
        }

        $groups = [];

        foreach ($plugin->getGroups() as $group) {
            $actions = [];

            foreach ($group['actions'] as $name => $action) {
                $action = $this->cachedActions[$name] ?? $action;

                if ($action->isHidden()) {
                    continue;
                }

                $actions[$name] = $this->prepareDockAction($action);
            }

            if (filled($actions)) {
                $groups[] = ['label' => $group['label'], 'actions' => $actions];
            }
        }

        return $groups;
    }

    protected function prepareDockAction(Action $action): Action
    {
        if (! $action->hasIcon()) {
            $action->icon(Heroicon::Bolt);
        }

        return $action
            ->iconButton()
            ->extraAttributes(array_filter([
                'role' => 'menuitem',
                'tabindex' => '-1',
                'aria-keyshortcuts' => $this->toAriaKeyShortcuts($action->getKeyBindings() ?? []),
            ]), merge: true);
    }

    public function getUserHash(): string
    {
        $id = auth()->id();

        if ($id === null) {
            return 'guest';
        }

        return substr(hash_hmac('sha256', (string) $id, (string) config('app.key')), 0, 16);
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        $plugin = $this->getPlugin();

        return [
            'layout' => $plugin->getLayout()->value,
            'panel' => filament()->getCurrentPanel()?->getId(),
            'user' => $this->getUserHash(),
            'keyBindings' => $plugin->getKeyBindings(),
            'draggable' => $plugin->isDraggable(),
            'snap' => $plugin->shouldSnapToEdges(),
            'remember' => $plugin->shouldRememberPosition(),
            'defaultPosition' => $plugin->getPosition()->value,
            'offset' => $plugin->getOffset(),
            'mobileBreakpoint' => $plugin->getMobileBreakpoint(),
        ];
    }

    public function getDeviceMediaQuery(): ?string
    {
        $plugin = $this->getPlugin();
        $breakpoint = $plugin->getMobileBreakpoint();

        return match (true) {
            $plugin->isHiddenOnMobile() && $plugin->isHiddenOnDesktop() => 'all',
            $plugin->isHiddenOnMobile() => '(max-width: '.($breakpoint - 0.02).'px)',
            $plugin->isHiddenOnDesktop() => "(min-width: {$breakpoint}px)",
            default => null,
        };
    }

    public function getAriaKeyShortcuts(): ?string
    {
        return $this->toAriaKeyShortcuts($this->getPlugin()->getKeyBindings());
    }

    /**
     * @param  array<string>  $bindings
     */
    protected function toAriaKeyShortcuts(array $bindings): ?string
    {
        $shortcuts = [];

        foreach ($bindings as $binding) {
            $keys = array_map(fn (string $key): string => match ($key) {
                'ctrl' => 'Control',
                'alt', 'option' => 'Alt',
                'shift' => 'Shift',
                'meta', 'command', 'cmd' => 'Meta',
                default => $key,
            }, explode('+', strtolower($binding)));

            $modIndex = array_search('mod', $keys, true);

            if ($modIndex !== false) {
                $shortcuts[] = implode('+', array_replace($keys, [$modIndex => 'Meta']));
                $shortcuts[] = implode('+', array_replace($keys, [$modIndex => 'Control']));

                continue;
            }

            $shortcuts[] = implode('+', $keys);
        }

        return filled($shortcuts) ? implode(' ', $shortcuts) : null;
    }

    public function render(): View
    {
        return view('quick-action-dock::dock', [
            'plugin' => $this->getPlugin(),
            'groups' => $this->getDockGroups(),
        ]);
    }
}
