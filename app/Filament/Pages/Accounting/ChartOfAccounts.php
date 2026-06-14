<?php

namespace App\Filament\Pages\Accounting;

use App\Enums\Accounting\AccountCategory;
use App\Filament\Concerns\RequiresBackOffice;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Business;
use App\Services\ChartOfAccountsService;
use App\Support\CurrentBusiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class ChartOfAccounts extends Page
{
    use RequiresBackOffice;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Chart of Accounts';

    protected static ?string $slug = 'accounting/chart';

    protected string $view = 'filament.pages.accounting.chart-of-accounts';

    #[Url]
    public string $activeTab = AccountCategory::Asset->value;

    protected function configureAction(Action $action): void
    {
        $action
            ->modal()
            ->slideOver()
            ->modalWidth('7xl');
    }

    public function mount(): void
    {
        $this->activeTab = $this->activeTab ?: AccountCategory::Asset->value;
    }

    public function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return Collection<string, array{label: string, subtypes: Collection<int, AccountSubtype>}>
     */
    public function getAccountCategoriesProperty(): Collection
    {
        $business = app(CurrentBusiness::class)->get();

        if (! $business instanceof Business) {
            return collect();
        }

        $structure = app(ChartOfAccountsService::class)->structure($business);

        return collect(AccountCategory::cases())->mapWithKeys(function (AccountCategory $category) use ($structure): array {
            return [
                $category->value => [
                    'label' => $category->label(),
                    'subtypes' => $structure->get($category->value, collect()),
                ],
            ];
        });
    }

    public function getCategoryLabel(string $categoryValue): string
    {
        return AccountCategory::from($categoryValue)->label();
    }

    public function createAccountAction(): Action
    {
        return CreateAction::make('createAccount')
            ->model(Account::class)
            ->label('Add account')
            ->icon('heroicon-o-plus-circle')
            ->modalHeading('Create chart account')
            ->form(fn (): array => [
                Hidden::make('subtype_id')->required(),
                TextInput::make('code_preview')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->fillForm(function (array $arguments): array {
                $subtype = AccountSubtype::query()->withoutGlobalScopes()->findOrFail($arguments['accountSubtype']);

                return [
                    'subtype_id' => $subtype->getKey(),
                    'code_preview' => app(ChartOfAccountsService::class)->nextAccountCode($subtype),
                ];
            })
            ->action(function (array $data): void {
                $subtype = AccountSubtype::query()->withoutGlobalScopes()->findOrFail((int) $data['subtype_id']);

                app(ChartOfAccountsService::class)->createAccount($subtype, [
                    'name' => (string) $data['name'],
                    'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
                    'archived' => false,
                ]);
            });
    }

    public function editAccountAction(): Action
    {
        return EditAction::make('editAccount')
            ->label('Edit')
            ->icon('heroicon-m-pencil-square')
            ->iconButton()
            ->record(fn (array $arguments) => Account::query()->withoutGlobalScopes()->findOrFail($arguments['account']))
            ->form(fn (): array => [
                TextInput::make('code')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('archived')
                    ->label('Archived'),
            ])
            ->action(function (Account $record, array $data): void {
                app(ChartOfAccountsService::class)->updateAccount($record, [
                    'name' => (string) $data['name'],
                    'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
                    'archived' => (bool) ($data['archived'] ?? false),
                ]);
            });
    }
}
