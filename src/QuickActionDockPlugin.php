<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use HoceineEl\QuickActionDock\Enums\DockLayout;
use HoceineEl\QuickActionDock\Enums\DockPosition;
use HoceineEl\QuickActionDock\Enums\DockStyle;
use HoceineEl\QuickActionDock\Support\DockContext;
use HoceineEl\QuickActionDock\Support\DockGroup;
use HoceineEl\QuickActionDock\Support\PageMatcher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Livewire\Livewire;

class QuickActionDockPlugin implements Plugin
{
    use EvaluatesClosures;

    protected DockLayout|Closure $layout = DockLayout::SpeedDial;

    protected DockPosition|Closure $position = DockPosition::BottomEnd;

    protected DockStyle|Closure $style = DockStyle::Native;

    protected bool|Closure $isDraggable = true;

    protected bool|Closure $shouldSnapToEdges = true;

    protected bool|Closure $shouldRememberPosition = true;

    protected string|BackedEnum|Htmlable|Closure|null $triggerIcon = null;

    protected string|Closure|null $triggerLabel = null;

    /**
     * @var array<string> | string | Closure | null
     */
    protected array|string|Closure|null $keyBindings = ['mod+.'];

    protected int|Closure $offset = 24;

    protected bool|Closure $hasLabels = false;

    protected string|Closure|null $badgePollingInterval = null;

    /**
     * @var array<DockGroup>
     */
    protected array $groups = [];

    /**
     * @var array<int | string, mixed> | string | Closure
     */
    protected array|string|Closure $hiddenOn = [];

    /**
     * @var array<int | string, mixed> | string | Closure
     */
    protected array|string|Closure $visibleOn = [];

    protected bool|Closure $isVisible = true;

    protected bool|Closure $isAuthenticatedOnly = true;

    protected string|Closure|null $guard = null;

    protected ?Closure $visibleFor = null;

    protected string|bool|Closure|null $ability = null;

    protected mixed $abilityArguments = [];

    /**
     * @var array<string> | string | Closure | null
     */
    protected array|string|Closure|null $roles = null;

    protected bool|Closure $isHiddenOnAuthPages = true;

    protected bool|Closure $isHiddenOnMobile = false;

    protected bool|Closure $isHiddenOnDesktop = false;

    protected int|Closure $mobileBreakpoint = 640;

    public function getId(): string
    {
        return 'quick-action-dock';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): string => $this->renderDock());
    }

    public function boot(Panel $panel): void {}

    public function layout(DockLayout|Closure $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function getLayout(): DockLayout
    {
        return $this->evaluate($this->layout);
    }

    public function position(DockPosition|Closure $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): DockPosition
    {
        return $this->evaluate($this->position);
    }

    public function defaultPosition(DockPosition|Closure $position): static
    {
        return $this->position($position);
    }

    public function style(DockStyle|Closure $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function getStyle(): DockStyle
    {
        return $this->evaluate($this->style);
    }

    public function draggable(bool|Closure $condition = true): static
    {
        $this->isDraggable = $condition;

        return $this;
    }

    public function isDraggable(): bool
    {
        return (bool) $this->evaluate($this->isDraggable);
    }

    public function snapToEdges(bool|Closure $condition = true): static
    {
        $this->shouldSnapToEdges = $condition;

        return $this;
    }

    public function shouldSnapToEdges(): bool
    {
        return (bool) $this->evaluate($this->shouldSnapToEdges);
    }

    public function rememberPosition(bool|Closure $condition = true): static
    {
        $this->shouldRememberPosition = $condition;

        return $this;
    }

    public function shouldRememberPosition(): bool
    {
        return (bool) $this->evaluate($this->shouldRememberPosition);
    }

    public function triggerIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->triggerIcon = $icon;

        return $this;
    }

    public function getTriggerIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->triggerIcon) ?? Heroicon::Plus;
    }

    public function triggerLabel(string|Closure|null $label): static
    {
        $this->triggerLabel = $label;

        return $this;
    }

    public function getTriggerLabel(): string
    {
        return $this->evaluate($this->triggerLabel) ?? __('quick-action-dock::dock.trigger');
    }

    /**
     * @param  array<string> | string | Closure | null  $bindings
     */
    public function keyBindings(array|string|Closure|null $bindings): static
    {
        $this->keyBindings = $bindings;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getKeyBindings(): array
    {
        return array_values(array_filter((array) $this->evaluate($this->keyBindings), filled(...)));
    }

    public function offset(int|Closure $pixels): static
    {
        $this->offset = $pixels;

        return $this;
    }

    public function getOffset(): int
    {
        return (int) $this->evaluate($this->offset);
    }

    public function showLabels(bool|Closure $condition = true): static
    {
        $this->hasLabels = $condition;

        return $this;
    }

    public function hasLabels(): bool
    {
        return (bool) $this->evaluate($this->hasLabels);
    }

    public function pollBadges(string|Closure|null $interval = '30s'): static
    {
        $this->badgePollingInterval = $interval;

        return $this;
    }

    public function getBadgePollingInterval(): ?string
    {
        return $this->evaluate($this->badgePollingInterval);
    }

    /**
     * @param  array<Action> | Closure(): array<Action>  $actions
     */
    public function actions(array|Closure $actions): static
    {
        $this->groups[] = new DockGroup(null, $actions);

        return $this;
    }

    /**
     * @param  array<Action> | Closure(): array<Action>  $actions
     */
    public function group(string|Closure $label, array|Closure $actions): static
    {
        $this->groups[] = new DockGroup($label, $actions);

        return $this;
    }

    /**
     * @return array<int, array{label: ?string, actions: array<string, Action>}>
     */
    public function getGroups(): array
    {
        return array_map(fn (DockGroup $group): array => [
            'label' => $this->evaluate($group->label),
            'actions' => collect($this->evaluate($group->actions))
                ->keyBy(fn (Action $action): string => $action->getName())
                ->all(),
        ], $this->groups);
    }

    /**
     * @return array<string, Action>
     */
    public function getActions(): array
    {
        return array_merge(...array_column($this->getGroups(), 'actions'));
    }

    /**
     * @param  array<int | string, mixed> | string | Closure  $pages
     */
    public function hiddenOn(array|string|Closure $pages): static
    {
        $this->hiddenOn = $pages;

        return $this;
    }

    /**
     * @return array<int | string, mixed>
     */
    public function getHiddenOn(): array
    {
        return $this->normalizePages($this->hiddenOn);
    }

    /**
     * @param  array<int | string, mixed> | string | Closure  $pages
     */
    public function visibleOn(array|string|Closure $pages): static
    {
        $this->visibleOn = $pages;

        return $this;
    }

    /**
     * @return array<int | string, mixed>
     */
    public function getVisibleOn(): array
    {
        return $this->normalizePages($this->visibleOn);
    }

    public function hiddenOnAuthPages(bool|Closure $condition = true): static
    {
        $this->isHiddenOnAuthPages = $condition;

        return $this;
    }

    public function isHiddenOnAuthPages(): bool
    {
        return (bool) $this->evaluate($this->isHiddenOnAuthPages);
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function isVisible(): bool
    {
        return (bool) $this->evaluate($this->isVisible);
    }

    public function authenticatedOnly(bool|Closure $condition = true): static
    {
        $this->isAuthenticatedOnly = $condition;

        return $this;
    }

    public function isAuthenticatedOnly(): bool
    {
        return (bool) $this->evaluate($this->isAuthenticatedOnly);
    }

    public function guard(string|Closure|null $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function getGuard(): ?string
    {
        return $this->evaluate($this->guard);
    }

    public function getUser(): ?Authenticatable
    {
        $guard = $this->getGuard();

        return $guard === null ? Filament::auth()->user() : auth()->guard($guard)->user();
    }

    /**
     * @param  Closure(?Authenticatable): bool  $callback
     */
    public function visibleFor(Closure $callback): static
    {
        $this->visibleFor = $callback;

        return $this;
    }

    public function authorize(string|bool|Closure|null $ability, mixed $arguments = []): static
    {
        $this->ability = $ability;
        $this->abilityArguments = $arguments;

        return $this;
    }

    public function can(string|Closure $ability, mixed $arguments = []): static
    {
        return $this->authorize($ability, $arguments);
    }

    /**
     * @param  array<string> | string | Closure | null  $roles
     */
    public function roles(array|string|Closure|null $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return array_values(array_filter((array) $this->evaluate($this->roles), filled(...)));
    }

    public function hiddenOnMobile(bool|Closure $condition = true): static
    {
        $this->isHiddenOnMobile = $condition;

        return $this;
    }

    public function visibleOnMobile(bool|Closure $condition = true): static
    {
        return $this->hiddenOnMobile(fn (): bool => ! $this->evaluate($condition));
    }

    public function isHiddenOnMobile(): bool
    {
        return (bool) $this->evaluate($this->isHiddenOnMobile);
    }

    public function hiddenOnDesktop(bool|Closure $condition = true): static
    {
        $this->isHiddenOnDesktop = $condition;

        return $this;
    }

    public function isHiddenOnDesktop(): bool
    {
        return (bool) $this->evaluate($this->isHiddenOnDesktop);
    }

    public function mobileBreakpoint(int|Closure $pixels): static
    {
        $this->mobileBreakpoint = $pixels;

        return $this;
    }

    public function getMobileBreakpoint(): int
    {
        return (int) $this->evaluate($this->mobileBreakpoint);
    }

    public function isVisibleForCurrentUser(): bool
    {
        $user = $this->getUser();

        if ($user === null && $this->isAuthenticatedOnly()) {
            return false;
        }

        $injections = [['user' => $user], [Authenticatable::class => $user]];

        if ($this->visibleFor && ! $this->evaluate($this->visibleFor, ...$injections)) {
            return false;
        }

        $ability = $this->evaluate($this->ability, ...$injections);

        if (is_bool($ability) && ! $ability) {
            return false;
        }

        if (is_string($ability) && filled($ability) && Gate::forUser($user)->denies($ability, $this->evaluate($this->abilityArguments))) {
            return false;
        }

        $roles = $this->getRoles();

        return blank($roles) || ($user !== null && method_exists($user, 'hasRole') && $user->hasRole($roles));
    }

    public function isHiddenOnCurrentPage(): bool
    {
        $context = DockContext::current();

        if ($this->isHiddenOnAuthPages() && $this->isAuthPage($context)) {
            return true;
        }

        if (PageMatcher::matches($this->getHiddenOn(), $context)) {
            return true;
        }

        $visibleOn = $this->getVisibleOn();

        return filled($visibleOn) && ! PageMatcher::matches($visibleOn, $context);
    }

    public function isHiddenOnCurrentRoute(): bool
    {
        return $this->isHiddenOnCurrentPage();
    }

    public function shouldRender(): bool
    {
        return $this->isVisible() && $this->isVisibleForCurrentUser() && ! $this->isHiddenOnCurrentPage();
    }

    protected function isAuthPage(DockContext $context): bool
    {
        $routeName = $context->getRouteName();
        $panel = Filament::getCurrentOrDefaultPanel();

        return filled($routeName) && $panel !== null && Str::is($panel->generateRouteName('auth.*'), $routeName);
    }

    /**
     * @param  array<int | string, mixed> | string | Closure  $pages
     * @return array<int | string, mixed>
     */
    protected function normalizePages(array|string|Closure $pages): array
    {
        $pages = $this->evaluate($pages);

        $isSingleResourceRule = is_array($pages)
            && array_is_list($pages)
            && count($pages) === 2
            && is_string($pages[0])
            && is_subclass_of($pages[0], Resource::class)
            && is_array($pages[1]);

        return $isSingleResourceRule ? [$pages] : (array) $pages;
    }

    public function renderDock(): string
    {
        if (! $this->isRegisteredOnCurrentPanel() || ! $this->shouldRender()) {
            return '';
        }

        $nonce = Vite::cspNonce();
        $nonceAttribute = filled($nonce) ? ' nonce="'.e($nonce).'"' : '';

        return Livewire::mount('quick-action-dock')
            ."<script{$nonceAttribute}>window.QuickActionDock?.prePlace(document.currentScript.previousElementSibling?.querySelector('.qad-root'))</script>";
    }

    protected function isRegisteredOnCurrentPanel(): bool
    {
        $panel = filament()->getCurrentPanel();

        return $panel?->hasPlugin($this->getId()) && ($panel->getPlugin($this->getId()) === $this);
    }
}
