<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('audit:purge --days=365')->daily()->at('02:00');
// Website page views grow without bound — see docs/VISITS_SCOPE.md.
Schedule::command('visits:prune')->daily()->at('02:30');
Schedule::command('fx:refresh-rates')->dailyAt('00:30');
Schedule::command('dashboard:warm-cache')->everyTenMinutes();
Schedule::command('sla:check-breaches')->everyFiveMinutes();
Schedule::command('leads:score')->hourly();
Schedule::command('workflows:run-scheduled')->everyMinute();
Schedule::command('activities:dispatch-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('projects:dispatch-task-notifications')->hourly()->withoutOverlapping();
Schedule::command('projects:run-automations')->hourly()->withoutOverlapping();
// SalesInbox — pull inbound mail over IMAP; a no-op for accounts without IMAP config.
Schedule::command('emails:fetch')->everyTenMinutes();
