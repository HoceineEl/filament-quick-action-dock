<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use HoceineEl\QuickActionDock\Enums\DockLayout;
use HoceineEl\QuickActionDock\Enums\DockPosition;
use HoceineEl\QuickActionDock\Enums\DockStyle;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;
use HoceineEl\QuickActionDock\Support\DockContext;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Filament\Pages\Settings;
use Workbench\App\Filament\Resources\Customers\CustomerResource;
use Workbench\App\Filament\Widgets\CampaignPalette;
use Workbench\App\Filament\Widgets\ChannelsChart;
use Workbench\App\Filament\Widgets\RevenueOverview;
use Workbench\App\Http\Middleware\ApplyPlaygroundOptions;
use Workbench\App\Models\Customer;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        config(['auth.providers.users.model' => User::class]);
    }

    public function panel(Panel $panel): Panel
    {
        $layout = DockLayout::tryFrom((string) env('QAD_LAYOUT')) ?? DockLayout::SpeedDial;
        $position = DockPosition::tryFrom((string) env('QAD_POSITION')) ?? DockPosition::BottomEnd;

        return $panel
            ->id('admin')
            ->brandName('Acme Admin')
            ->path('admin')
            ->default()
            ->login()
            ->resources([CustomerResource::class])
            ->pages([Dashboard::class, Settings::class])
            ->widgets([RevenueOverview::class, CampaignPalette::class, ChannelsChart::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([ApplyPlaygroundOptions::class], isPersistent: true)
            ->authMiddleware([Authenticate::class])
            ->renderHook(PanelsRenderHook::HEAD_START, function (): string {
                $dir = ApplyPlaygroundOptions::get('qad-dir');

                return in_array($dir, ['rtl', 'ltr'], true)
                    ? '<script>document.documentElement.dir = '.json_encode($dir).'</script>'
                    : '';
            })
            ->plugin(
                QuickActionDockPlugin::make()
                    ->layout(fn (): DockLayout => DockLayout::tryFrom((string) ApplyPlaygroundOptions::get('qad-layout')) ?? $layout)
                    ->position(fn (): DockPosition => DockPosition::tryFrom((string) ApplyPlaygroundOptions::get('qad-position')) ?? $position)
                    ->style(fn (): DockStyle => DockStyle::tryFrom((string) ApplyPlaygroundOptions::get('qad-style')) ?? DockStyle::Native)
                    ->showLabels(fn (): bool => (bool) (ApplyPlaygroundOptions::get('qad-labels') ?? env('QAD_LABELS')))
                    ->hiddenOn(['/admin/settings'])
                    ->hiddenOnMobile(fn (): bool => ApplyPlaygroundOptions::get('qad-mobile') === 'hidden')
                    ->actions(fn (): array => self::isDemo() ? $this->demoActions() : $this->playgroundActions())
                    ->group(
                        fn (): string => self::isDemo() ? self::demoLabel('This customer', 'هذا العميل') : 'This record',
                        fn (): array => self::isDemo() ? $this->demoRecordActions() : $this->playgroundRecordActions(),
                    )
                    ->group(
                        fn (): string => self::isDemo() ? self::demoLabel('Tools', 'أدوات') : 'Reports',
                        fn (): array => self::isDemo() ? $this->demoToolActions() : $this->playgroundReportActions(),
                    )
                    ->pollBadges(fn (): string => ApplyPlaygroundOptions::get('qad-poll') ?? '30s')
            );
    }

    /**
     * @return array<Action>
     */
    protected function playgroundActions(): array
    {
        return [
            Action::make('newCustomer')
                ->label('New customer')
                ->icon(Heroicon::UserPlus)
                ->keyBindings(['mod+shift+c'])
                ->schema([TextInput::make('name')->required(), TextInput::make('email')->email()])
                ->action(function (array $data): void {
                    Customer::create($data);
                    Notification::make()->title('Customer created')->success()->send();
                }),
            Action::make('openDocs')->label('Filament docs')->icon(Heroicon::BookOpen)->url('https://filamentphp.com/docs', shouldOpenInNewTab: true),
            Action::make('customers')->label('Customers')->icon(Heroicon::Inbox)->url(fn (): string => CustomerResource::getUrl())
                ->badge(fn (): ?int => Customer::query()->count() ?: null)->badgeColor('danger'),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function playgroundRecordActions(): array
    {
        return [
            Action::make('rename')
                ->label('Uppercase name')
                ->icon(Heroicon::PencilSquare)
                ->visible(DockContext::resource(CustomerResource::class, pages: ['edit']))
                ->action(function (): void {
                    $record = DockContext::record();
                    $record?->update(['name' => mb_strtoupper((string) $record->getAttribute('name'))]);
                    Notification::make()->title('Renamed')->success()->send();
                }),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function playgroundReportActions(): array
    {
        return [
            Action::make('exportRevenue')
                ->label('Export the quarterly revenue summary for every region')
                ->icon(Heroicon::ArrowDownTray)
                ->action(fn () => Notification::make()->title('Export queued')->success()->send()),
            Action::make('archive')->label('Archive old customers')->icon(Heroicon::ArchiveBox)->disabled(),
        ];
    }

    protected static function isDemo(): bool
    {
        return ApplyPlaygroundOptions::get('qad-demo') !== null;
    }

    protected static function demoLabel(string $english, string $arabic): string
    {
        return app()->getLocale() === 'ar' ? $arabic : $english;
    }

    /**
     * @return array<Action>
     */
    protected function demoActions(): array
    {
        return [
            Action::make('newCustomer')
                ->label(self::demoLabel('New customer', 'عميل جديد'))
                ->icon(Heroicon::UserPlus)
                ->keyBindings(['mod+shift+c'])
                ->schema([TextInput::make('name')->required(), TextInput::make('email')->email()])
                ->action(fn (array $data) => Customer::create($data)),
            Action::make('newInvoice')
                ->label(self::demoLabel('New invoice', 'فاتورة جديدة'))
                ->icon(Heroicon::DocumentPlus)
                ->keyBindings(['mod+shift+i'])
                ->url(fn (): string => CustomerResource::getUrl()),
            Action::make('inbox')
                ->label(self::demoLabel('Inbox', 'الوارد'))
                ->icon(Heroicon::Inbox)
                ->url(fn (): string => CustomerResource::getUrl())
                ->badge('12')
                ->badgeColor('danger'),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function demoRecordActions(): array
    {
        return [
            Action::make('emailCustomer')
                ->label(self::demoLabel('Email customer', 'مراسلة العميل'))
                ->icon(Heroicon::Envelope)
                ->visible(DockContext::resource(CustomerResource::class, pages: ['edit']))
                ->action(fn () => Notification::make()->title('Email sent')->success()->send()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function demoToolActions(): array
    {
        return [
            Action::make('sendNewsletter')
                ->label(self::demoLabel('Send newsletter', 'إرسال النشرة'))
                ->icon(Heroicon::PaperAirplane)
                ->requiresConfirmation()
                ->modalHeading(self::demoLabel('Send the newsletter?', 'إرسال النشرة الآن؟'))
                ->modalDescription(self::demoLabel('It goes to 1,284 subscribers.', 'ستصل إلى 1,284 مشتركًا.'))
                ->action(fn () => Notification::make()->title('Newsletter sent')->success()->send()),
            Action::make('exportCustomers')
                ->label(self::demoLabel('Export customers', 'تصدير العملاء'))
                ->icon(Heroicon::ArrowDownTray)
                ->keyBindings(['mod+shift+e'])
                ->action(fn () => Notification::make()->title('Export queued')->success()->send()),
            Action::make('documentation')
                ->label(self::demoLabel('Documentation', 'التوثيق'))
                ->icon(Heroicon::BookOpen)
                ->url('https://filamentphp.com/docs', shouldOpenInNewTab: true),
        ];
    }
}
