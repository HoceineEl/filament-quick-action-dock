<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use HoceineEl\QuickActionDock\Enums\DockLayout;
use HoceineEl\QuickActionDock\Enums\DockPosition;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use HoceineEl\QuickActionDock\Support\DockContext;
use HoceineEl\QuickActionDock\Tests\Fixtures\Customer;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function newCustomerAction(): Action
{
    return Action::make('newCustomer')
        ->label('New customer')
        ->icon(Heroicon::UserPlus)
        ->keyBindings(['mod+shift+c'])
        ->schema([TextInput::make('name')->required()])
        ->action(fn (array $data): Customer => Customer::create($data));
}

it('calls a dock action with its form data', function (): void {
    dock()->actions([newCustomerAction()]);

    Livewire::test(QuickActionDock::class)
        ->callAction('newCustomer', data: ['name' => 'Ada'])
        ->assertHasNoErrors();

    expect(Customer::query()->pluck('name')->all())->toBe(['Ada']);
});

it('validates the action form natively', function (): void {
    dock()->actions([newCustomerAction()]);

    Livewire::test(QuickActionDock::class)
        ->callAction('newCustomer', data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);

    expect(Customer::query()->count())->toBe(0);
});

it('calls actions defined in closures and groups', function (): void {
    dock()->group('Create', fn (): array => [newCustomerAction()]);

    Livewire::test(QuickActionDock::class)->callAction('newCustomer', data: ['name' => 'Grace']);

    expect(Customer::query()->count())->toBe(1);
});

it('renders items with Filament action markup', function (): void {
    dock()->actions([
        newCustomerAction(),
        Action::make('openDocs')->label('Docs')->icon(Heroicon::BookOpen)->url('https://filamentphp.com', shouldOpenInNewTab: true),
    ]);

    Livewire::test(QuickActionDock::class)
        ->assertSeeHtml('x-mousetrap.global.mod-shift-c')
        ->assertSeeHtml('wire:click="mountAction(\'newCustomer\'')
        ->assertSeeHtml('href="https://filamentphp.com"')
        ->assertSeeHtml('role="menuitem"')
        ->assertSee('New customer')
        ->assertSee('Docs');
});

it('evaluates action badges', function (): void {
    Customer::create(['name' => 'Ada']);
    Customer::create(['name' => 'Grace']);

    dock()->actions([
        Action::make('inbox')->icon(Heroicon::Inbox)->badge(fn (): int => Customer::query()->count())->badgeColor('danger'),
    ]);

    Livewire::test(QuickActionDock::class)->assertSeeHtml('fi-badge')->assertSeeInOrder(['fi-icon-btn-badge-ctn', '2']);
});

it('polls badges only when asked', function (): void {
    dock()->actions([newCustomerAction()]);

    Livewire::test(QuickActionDock::class)->assertDontSeeHtml('wire:poll');

    dock()->pollBadges('15s');

    Livewire::test(QuickActionDock::class)->assertSeeHtml('wire:poll.15s');
});

it('renders group labels', function (): void {
    dock()->group('Create', [newCustomerAction()]);

    Livewire::test(QuickActionDock::class)->assertSee('Create')->assertSeeHtml('role="group"');
});

it('hides actions that are not visible', function (): void {
    dock()->actions([newCustomerAction(), Action::make('secret')->label('Secret')->hidden()]);

    Livewire::test(QuickActionDock::class)->assertSee('New customer')->assertDontSee('Secret');
});

it('renders layout, position, offset and toggle bindings', function (): void {
    dock()->actions([newCustomerAction()])
        ->layout(DockLayout::Bar)
        ->position(DockPosition::BottomStart)
        ->offset(32)
        ->keyBindings(['mod+.', 'alt+q']);

    Livewire::test(QuickActionDock::class)
        ->assertSeeHtml('data-position="bottom-start"')
        ->assertSeeHtml('--qad-offset: 32px')
        ->assertSeeHtml('data-layout="bar"')
        ->assertSeeHtml('aria-keyshortcuts="Meta+. Control+. Alt+q"');
});

it('renders nothing when the dock has no visible actions', function (): void {
    Livewire::test(QuickActionDock::class)->assertDontSeeHtml('qad-trigger');
});

it('renders nothing and resolves no actions when hidden', function (): void {
    dock()->actions([newCustomerAction()])->visible(false);

    $component = Livewire::test(QuickActionDock::class)->assertDontSeeHtml('qad-trigger');

    expect($component->instance()->getAction('newCustomer'))->toBeNull();
});

it('remembers the page route across update requests', function (): void {
    DockContext::current()->set('filament.admin.resources.customers.index', []);

    dock()->actions([
        newCustomerAction()->visible(DockContext::resource(CustomerResource::class)),
    ]);

    $component = Livewire::test(QuickActionDock::class);

    expect($component->get('routeName'))->toBe('filament.admin.resources.customers.index');

    DockContext::current()->set('livewire.update', []);

    $component->call('$refresh');

    expect(DockContext::current()->getRouteName())->toBe('filament.admin.resources.customers.index');

    $component->callAction('newCustomer', data: ['name' => 'Ada']);

    expect(Customer::query()->count())->toBe(1);
});

it('gives actions the current page record', function (): void {
    $customer = Customer::create(['name' => 'Ada']);
    DockContext::current()->set('filament.admin.resources.customers.edit', ['record' => (string) $customer->getKey()]);

    dock()->actions([
        Action::make('rename')->action(fn () => DockContext::record()?->update(['name' => 'Ada Lovelace'])),
    ]);

    Livewire::test(QuickActionDock::class)->callAction('rename');

    expect($customer->refresh()->name)->toBe('Ada Lovelace');
});

it('locks the captured route', function (): void {
    dock()->actions([newCustomerAction()]);

    Livewire::test(QuickActionDock::class)->set('routeName', 'filament.admin.pages.dashboard');
})->throws(CannotUpdateLockedPropertyException::class);

it('renders the action modals container outside the fixed dock', function (): void {
    dock()->actions([newCustomerAction()]);

    $html = Livewire::test(QuickActionDock::class)->html();

    expect($html)->toContain('filamentActionModals')
        ->and(strpos($html, 'filamentActionModals'))->toBeGreaterThan(strrpos($html, 'qad-trigger'));
});

it('is registered with Livewire by name so update requests resolve it', function (): void {
    dock()->actions([newCustomerAction()]);

    Livewire::test('quick-action-dock')->assertSeeHtml('qad-trigger');
});

it('accepts a closure for the badge polling interval', function (): void {
    dock()->actions([newCustomerAction()])->pollBadges(fn (): string => '5s');

    Livewire::test(QuickActionDock::class)->assertSeeHtml('wire:poll.5s');
});

it('announces and shows each item key binding', function (): void {
    dock()->actions([newCustomerAction(), Action::make('inbox')->label('Inbox')]);

    $html = Livewire::test(QuickActionDock::class)
        ->assertSeeHtml('aria-keyshortcuts="Meta+Shift+c Control+Shift+c"')
        ->assertSeeHtml('class="qad-kbd" data-binding="mod+shift+c"')
        ->html();

    expect(substr_count($html, 'class="qad-kbd"'))->toBe(1);
});
