<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\OwnershipService;

class OwnershipController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $data = (new OwnershipService())->dashboardData();
        $this->view('ownership/index', $data);
    }

    public function backfill(): void
    {
        Auth::requireLogin();
        $summary = (new OwnershipService())->backfill();
        $_SESSION['flash'] = 'Ownership backfill complete: ' . implode(', ', array_map(fn($key, $value) => $key . ' ' . $value, array_keys($summary), $summary));
        $this->redirect('/ownership');
    }

    public function update(): void
    {
        Auth::requireLogin();
        (new OwnershipService())->updateOwnership($_POST);
        $this->redirect($_POST['return_to'] ?? '/ownership');
    }
}
