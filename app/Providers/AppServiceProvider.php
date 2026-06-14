<?php

namespace App\Providers;

use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Support\CurrentBusiness;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentBusiness::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'bank' => BankAccount::class,
            'cash_register' => CashRegister::class,
        ]);

        $this->app->resolving(Schema::class, function (Schema $schema): void {
            if (! $schema->hasCustomColumns()) {
                $schema->columns(1);
            }
        });
    }
}
