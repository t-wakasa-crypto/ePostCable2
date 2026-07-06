<?php

namespace Modules\SendMailLog\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\Invoice\Models\Invoice;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SendMailLogServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'SendMailLog';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'sendmaillog';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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
     * モジュール起動処理。
     *
     * send_mail_log_items.sendable_type にはモデルの実クラス名ではなく
     * morphMap の論理名（'invoice' / 'delivery_note'）を格納する（Q-15 決定・
     * db-design §1.1）。モジュール型構成でクラス名変更に強くするため。
     */
    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            Invoice::MORPH_ALIAS => Invoice::class,
            DeliveryNote::MORPH_ALIAS => DeliveryNote::class,
        ]);
    }

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
