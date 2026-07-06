<?php

namespace Modules\ShipmentFetch\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\ShipmentFetch\Console\Commands\FetchShipmentData;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ShipmentFetchServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ShipmentFetch';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'shipmentfetch';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        FetchShipmentData::class,
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
