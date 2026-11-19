<?php

namespace App\Observers;

use App\Services\Admin\FinancialReportService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/** Keeps the dashboard projection disposable: financial writes only invalidate it after commit. */
final class FinancialReportCacheInvalidator implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        app(FinancialReportService::class)->forget();
    }

    public function deleted(Model $model): void
    {
        app(FinancialReportService::class)->forget();
    }
}
