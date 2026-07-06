<?php

namespace Modules\Invoice\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Invoice\Console\Commands\SendInvoices;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InvoiceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Invoice';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'invoice';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendInvoices::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
