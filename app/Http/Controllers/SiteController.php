<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BackupService;

class SiteController extends Controller
{
    public function __invoke(BackupService $service)
    {
        if (false === $service->backupInfo->completed) {
            $seconds = (int)ini_get('max_execution_time') - 1;
            header("Refresh: $seconds");

            $service->createDump();
        } else {
            $service->backupInfo->clear();

            return response('Backup completed');
        }
    }
}
