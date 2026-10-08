<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use HoceineEl\QuickActionDock\Support\DockContext;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages\EditCustomer;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages\ListCustomers;
use HoceineEl\QuickActionDock\Tests\Fixtures\RoleUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

function onPage(?string $routeName, string $path = 'admin', array $parameters = []): void
{
    DockContext::current()->set($routeName, $parameters, $path);
}

function roleUser(string ...$roles): RoleUser
{
    $user = (new RoleUser)->forceFill(['id' => 5]);
    $user->assignedRoles = $roles;

    return $user;
}

beforeEach(function (): void {
    dock()->actions([Action::make('inbox')]);
    onPage('filament.admin.pages.dashboard');
});

describe('audience', function (): void {
    it('never renders for guests by default', function (): void {
        auth()->logout();

        expect(dock()->isAuthenticatedOnly())->toBeTrue()
            ->and(dock()->shouldRender())->toBeFalse();

        Filament::bootCurrentPanel();

        expect(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())->not->toContain('qad-root');
        Livewire::test(QuickActionDock::class)->assertDontSeeHtml('qad-trigger');
    });

    it('can be shown to guests when asked', function (): void {
        auth()->logout();

        expect(dock()->authenticatedOnly(false)->shouldRender())->toBeTrue()
            ->and(dock()->authenticatedOnly(fn (): bool => true)->shouldRender())->toBeFalse();
    });

    it('reads the user from a chosen guard', function (): void {
        config(['auth.guards.admin' => ['driver' => 'session', 'provider' => 'users']]);
        auth()->logout();

        dock()->guard('admin');
        expect(dock()->shouldRender())->toBeFalse();

        $this->actingAs((new User)->forceFill(['id' => 9]), 'admin');
        expect(dock()->getUser()?->getAuthIdentifier())->toBe(9)
            ->and(dock()->shouldRender())->toBeTrue();
    });

    it('asks a callback with the current user', function (): void {
        dock()->visibleFor(fn (?Authenticatable $user): bool => $user?->getAuthIdentifier() === 1);
        expect(dock()->shouldRender())->toBeTrue();

        $this->actingAs((new User)->forceFill(['id' => 2]));
        expect(dock()->shouldRender())->toBeFalse();
    });

    it('checks a gate ability with optional arguments', function (): void {
        Gate::define('useQuickActions', fn (User $user, string $area = 'any'): bool => $user->getAuthIdentifier() === 1 && $area !== 'billing');

        expect(dock()->can('useQuickActions')->shouldRender())->toBeTrue()
            ->and(dock()->authorize('useQuickActions', ['billing'])->shouldRender())->toBeFalse();

        $this->actingAs((new User)->forceFill(['id' => 2]));
        expect(dock()->can('useQuickActions')->shouldRender())->toBeFalse()
            ->and(dock()->authorize(fn (): bool => true)->shouldRender())->toBeTrue()
            ->and(dock()->authorize(fn (): string => 'useQuickActions')->shouldRender())->toBeFalse();
    });

    it('checks roles only on users that have them', function (): void {
        dock()->roles(['admin', 'support']);

        expect(dock()->shouldRender())->toBeFalse();

        $this->actingAs(roleUser('support'));
        expect(dock()->shouldRender())->toBeTrue();

        $this->actingAs(roleUser('viewer'));
        expect(dock()->shouldRender())->toBeFalse()
            ->and(dock()->roles(fn (): string => 'viewer')->shouldRender())->toBeTrue()
            ->and(dock()->roles(null)->shouldRender())->toBeTrue();
    });
});

describe('where', function (): void {
    it('matches route name patterns', function (): void {
        dock()->hiddenOn('filament.admin.resources.*');

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeFalse();

        onPage('filament.admin.pages.dashboard');
        expect(dock()->shouldRender())->toBeTrue();
    });

    it('matches URL path patterns with or without a leading slash', function (string $pattern): void {
        dock()->hiddenOn([$pattern]);

        onPage('filament.admin.resources.customers.edit', 'admin/customers/4/edit', ['record' => '4']);
        expect(dock()->shouldRender())->toBeFalse();

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeTrue();
    })->with(['/admin/customers/*/edit', 'admin/customers/*/edit']);

    it('matches every page of a resource', function (): void {
        dock()->hiddenOn([CustomerResource::class]);

        onPage('filament.admin.resources.customers.view', 'admin/customers/4', ['record' => '4']);
        expect(dock()->shouldRender())->toBeFalse();

        onPage('filament.admin.pages.dashboard');
        expect(dock()->shouldRender())->toBeTrue();
    });

    it('matches chosen pages of a resource', function (): void {
        dock()->hiddenOn([CustomerResource::class, ['edit', 'view']]);

        onPage('filament.admin.resources.customers.edit', 'admin/customers/4/edit', ['record' => '4']);
        expect(dock()->shouldRender())->toBeFalse();

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeTrue();

        dock()->hiddenOn(['filament.admin.pages.*', [CustomerResource::class, ['index']]]);
        expect(dock()->shouldRender())->toBeFalse();
    });

    it('matches page classes', function (): void {
        dock()->visibleOn([ListCustomers::class]);

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeTrue();

        onPage('filament.admin.resources.customers.edit', 'admin/customers/4/edit', ['record' => '4']);
        expect(dock()->shouldRender())->toBeFalse();

        dock()->visibleOn([EditCustomer::class]);
        expect(dock()->shouldRender())->toBeTrue();
    });

    it('limits the dock to an allow list', function (): void {
        dock()->visibleOn(fn (): array => [CustomerResource::class, 'admin/reports/*']);

        expect(dock()->shouldRender())->toBeFalse();

        onPage('filament.admin.pages.reports', 'admin/reports/sales');
        expect(dock()->shouldRender())->toBeTrue();

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeTrue();
    });

    it('lets the deny list win over the allow list', function (): void {
        dock()->visibleOn([CustomerResource::class])->hiddenOn([[CustomerResource::class, ['edit']]]);

        onPage('filament.admin.resources.customers.index', 'admin/customers');
        expect(dock()->shouldRender())->toBeTrue();

        onPage('filament.admin.resources.customers.edit', 'admin/customers/4/edit', ['record' => '4']);
        expect(dock()->shouldRender())->toBeFalse();
    });

    it('hides on auth pages even for signed in users unless allowed', function (string $route): void {
        onPage($route, 'admin/login');

        expect(dock()->shouldRender())->toBeFalse()
            ->and(dock()->hiddenOnAuthPages(false)->shouldRender())->toBeTrue();
    })->with(['filament.admin.auth.login', 'filament.admin.auth.password-reset.request', 'filament.admin.auth.email-verification.prompt']);

    it('keeps the page it was rendered on across update requests', function (): void {
        dock()->visibleOn(['/admin/customers']);
        onPage('filament.admin.resources.customers.index', 'admin/customers');

        $component = Livewire::test(QuickActionDock::class)->assertSeeHtml('qad-trigger');

        expect($component->get('path'))->toBe('admin/customers');

        onPage('livewire.update', 'livewire/update');
        $component->call('$refresh')->assertSeeHtml('qad-trigger');

        expect(DockContext::current()->getPath())->toBe('admin/customers')
            ->and(DockContext::pathIs('/admin/customers'))->toBeTrue();
    });
});

describe('devices', function (): void {
    it('hides with a media query only on the chosen devices', function (): void {
        Livewire::test(QuickActionDock::class)->assertDontSeeHtml('<style');

        dock()->hiddenOnMobile();
        Livewire::test(QuickActionDock::class)->assertSeeHtml('@media (max-width: 639.98px)')->assertSeeHtml('[id="');

        dock()->hiddenOnMobile(false)->hiddenOnDesktop()->mobileBreakpoint(768);
        Livewire::test(QuickActionDock::class)->assertSeeHtml('@media (min-width: 768px)');

        dock()->hiddenOnMobile();
        Livewire::test(QuickActionDock::class)->assertSeeHtml('@media all');
    });

    it('treats visibleOnMobile as the opposite of hiddenOnMobile', function (): void {
        expect(dock()->visibleOnMobile(false)->isHiddenOnMobile())->toBeTrue()
            ->and(dock()->visibleOnMobile()->isHiddenOnMobile())->toBeFalse();
    });

    it('passes the breakpoint to the client', function (): void {
        dock()->mobileBreakpoint(fn (): int => 900);

        expect(Livewire::test(QuickActionDock::class)->instance()->getClientConfig()['mobileBreakpoint'])->toBe(900);
    });
});
