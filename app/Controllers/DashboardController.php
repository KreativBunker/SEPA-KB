<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ExportRunRepository;
use App\Support\Flash;
use App\Support\View;

final class DashboardController
{
    public function index(): void
    {
        $runs = (new ExportRunRepository())->all();
        $latest = $runs[0] ?? null;

        View::render('dashboard', [
            'latest' => $latest,
            'messages' => Flash::all(),
        ]);
    }
}
