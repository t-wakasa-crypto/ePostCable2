<?php

namespace Modules\DeliveryNote\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\DeliveryNote\Console\Commands\SendDeliveryNotes;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DeliveryNoteServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'DeliveryNote';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'deliverynote';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendDeliveryNotes::class,
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
