<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\OpportunityPursuitService;

class PursuitController extends Controller
{
    private OpportunityPursuitService $service;

    public function __construct()
    {
        $this->service = new OpportunityPursuitService();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $this->render(null, 'National');
    }

    public function southeast(): void
    {
        $this->regional('Southeast');
    }

    public function greatLakes(): void
    {
        $this->regional('Great Lakes');
    }

    public function southwest(): void
    {
        $this->regional('Southwest');
    }

    public function detail(): void
    {
        Auth::requireLogin();
        $opportunity = $this->service->detail((int)($_GET['id'] ?? 0));
        if (!$opportunity) {
            $this->redirect('/pursuits');
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM communication_records WHERE region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $stmt->execute([(int)($opportunity['region_id'] ?? 0)]);
        $recentConversations = $stmt->fetchAll();
        $this->view('pursuits/detail', compact('opportunity', 'recentConversations'));
    }

    public function rebuild(): void
    {
        Auth::requireLogin();
        $this->service->rebuild();
        $this->redirect('/pursuits');
    }

    private function regional(string $regionName): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$regionName]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/pursuits');
        }
        $this->render($regionId, $regionName);
    }

    private function render(?int $regionId, string $label): void
    {
        $data = $this->service->dashboardData($regionId);
        $data = $this->filterDashboardData($data);
        $this->view('pursuits/index', ['data' => $data, 'label' => $label]);
    }

    private function filterDashboardData(array $data): array
    {
        $query = strtolower(trim((string)($_GET['q'] ?? '')));
        $owner = trim((string)($_GET['owner'] ?? ''));
        $region = trim((string)($_GET['region'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $allowed = match (Auth::user()['role'] ?? 'Admin') {
            'Southeast Owner' => ['Southeast', 'Southwest', 'National'],
            'Great Lakes Owner' => ['Great Lakes', 'Southwest', 'National'],
            'Southwest Owner' => ['Southwest', 'National'],
            default => [],
        };
        $filter = function (array $row) use ($query, $owner, $region, $status, $allowed): bool {
            $rowRegion = (string)($row['region_name'] ?? 'National');
            if ($allowed && !in_array($rowRegion, $allowed, true)) {
                return false;
            }
            if ($region !== '' && $rowRegion !== $region) {
                return false;
            }
            $rowOwner = (string)($row['owner'] ?? $row['assigned_owner'] ?? '');
            if ($owner !== '' && $rowOwner !== $owner) {
                return false;
            }
            $rowStatus = (string)($row['stage'] ?? $row['status'] ?? $row['recommended_decision'] ?? '');
            if ($status !== '' && $rowStatus !== $status) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            $haystack = strtolower(implode(' ', array_map(fn($value) => is_scalar($value) ? (string)$value : '', $row)));
            return str_contains($haystack, $query);
        };

        foreach (['topPursuits', 'fiberBackbone', 'avoid', 'capacityBlocked', 'relationshipBlocked', 'watchlist', 'recommendations'] as $key) {
            $data[$key] = array_values(array_filter($data[$key] ?? [], $filter));
        }
        foreach ($data['board'] ?? [] as $stage => $items) {
            $data['board'][$stage] = array_values(array_filter($items, $filter));
        }
        return $data;
    }
}
