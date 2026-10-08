<?php

declare(strict_types=1);

use HoceineEl\QuickActionDock\Support\DockContext;
use HoceineEl\QuickActionDock\Tests\Fixtures\Customer;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;
use Illuminate\Routing\Route;

it('is a request scoped singleton', function (): void {
    expect(DockContext::current())->toBe(DockContext::current())
        ->and(DockContext::current())->toBe(app(DockContext::class));
});

it('matches route name patterns', function (): void {
    DockContext::current()->set('filament.admin.resources.customers.edit', ['record' => '1']);

    expect(DockContext::routeIs('filament.admin.resources.customers.*'))->toBeTrue()
        ->and(DockContext::routeIs('filament.admin.pages.*', 'filament.admin.resources.*'))->toBeTrue()
        ->and(DockContext::routeIs('filament.admin.pages.*'))->toBeFalse();
});

it('never matches when no route is known', function (): void {
    DockContext::current()->set(null, []);

    expect(DockContext::routeIs('*'))->toBeFalse();
});

it('builds a visibility closure for a resource', function (): void {
    $anyPage = DockContext::resource(CustomerResource::class);
    $editOnly = DockContext::resource(CustomerResource::class, pages: ['edit']);

    DockContext::current()->set('filament.admin.resources.customers.index', []);
    expect($anyPage())->toBeTrue()->and($editOnly())->toBeFalse();

    DockContext::current()->set('filament.admin.resources.customers.edit', ['record' => '1']);
    expect($anyPage())->toBeTrue()->and($editOnly())->toBeTrue();

    DockContext::current()->set('filament.admin.pages.dashboard', []);
    expect($anyPage())->toBeFalse();
});

it('resolves the record of a view or edit page', function (string $page): void {
    $customer = Customer::create(['name' => 'Ada']);

    DockContext::current()->set("filament.admin.resources.customers.{$page}", ['record' => (string) $customer->getKey()]);

    expect(DockContext::record())->toBeInstanceOf(Customer::class)
        ->and(DockContext::record()?->is($customer))->toBeTrue();
})->with(['view', 'edit']);

it('returns no record outside record pages or for missing records', function (): void {
    DockContext::current()->set('filament.admin.resources.customers.index', []);
    expect(DockContext::record())->toBeNull();

    DockContext::current()->set('filament.admin.resources.customers.edit', ['record' => '999']);
    expect(DockContext::record())->toBeNull();

    DockContext::current()->set('unknown.route', ['record' => '1']);
    expect(DockContext::record())->toBeNull();
});

it('captures a route with scalar parameters only', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    $route = (new Route('GET', 'customers/{record}/{tab}', fn () => null))->name('customers.show');
    $route->bind(request()->create('customers/1/notes'));
    $route->setParameter('record', $customer);

    $context = DockContext::fromRoute($route);

    expect($context->getRouteName())->toBe('customers.show')
        ->and($context->getRouteParameters())->toBe(['record' => $customer->getKey(), 'tab' => 'notes']);
});

it('captures nothing without a route', function (): void {
    expect(DockContext::fromRoute(null)->getRouteName())->toBeNull();
});
