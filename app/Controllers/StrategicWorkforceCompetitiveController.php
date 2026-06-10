<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\ExecutivePackagingService;
use App\Services\StrategicWorkforceCompetitiveService;

class StrategicWorkforceCompetitiveController extends Controller
{
    public function accounts(): void
    {
        $this->dashboard(null, 'Strategic Account Intelligence', 'Who has work, who runs it, and where Jackson needs account coverage.', 'accounts');
    }

    public function workforce(): void
    {
        $this->dashboard(null, 'Workforce Intelligence', 'Project leaders, field leaders, technical talent, and recruitment opportunities.', 'workforce');
    }

    public function competitors(): void
    {
        $this->dashboard(null, 'Competitive Intelligence', 'Who else is chasing the work and where competitive pressure is rising.', 'competitors');
    }

    public function accountDetail(): void
    {
        Auth::requireLogin();
        $db = Database::connection();
        (new StrategicWorkforceCompetitiveService())->rebuild();
        $stmt = $db->prepare('SELECT sa.*, r.name region_name FROM strategic_accounts sa LEFT JOIN regions r ON r.id = sa.region_id WHERE sa.id = ?');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        $account = $stmt->fetch();
        if (!$account) {
            $this->redirect('/strategic-account-intelligence');
        }
        $contacts = $db->prepare('SELECT c.* FROM contacts c JOIN organizations o ON o.id = c.organization_id WHERE c.region_id = ? AND (o.name LIKE ? OR c.notes LIKE ? OR c.title LIKE ?) ORDER BY c.last_contact_date DESC LIMIT 12');
        $like = '%' . strtok($account['account_name'], ' ') . '%';
        $contacts->execute([(int)$account['region_id'], $like, $like, '%Manager%']);
        $opportunities = $db->prepare('SELECT op.* FROM opportunities op JOIN organizations o ON o.id = op.organization_id WHERE op.region_id = ? AND (o.name LIKE ? OR op.name LIKE ?) ORDER BY op.estimated_value DESC LIMIT 12');
        $opportunities->execute([(int)$account['region_id'], $like, $like]);
        $recentConversations = $db->prepare('SELECT * FROM communication_records WHERE region_id = ? ORDER BY communication_date DESC LIMIT 8');
        $recentConversations->execute([(int)$account['region_id']]);
        $conversationRows = $recentConversations->fetchAll();
        $timelineItems = array_map(fn($row) => ['type' => $row['communication_type'], 'title' => $row['summary'], 'why' => $row['outcome'] ?: 'Conversation may change strategic account access.', 'next' => $row['next_step'] ?: 'Assign a follow-up if needed.', 'owner' => $row['owner'], 'date' => $row['communication_date']], $conversationRows);
        $this->view('strategic_intelligence/detail', ['account' => $account, 'contacts' => $contacts->fetchAll(), 'opportunities' => $opportunities->fetchAll(), 'recentConversations' => $conversationRows, 'timelineItems' => $timelineItems]);
    }

    public function southeast(): void { $this->regional('Southeast'); }
    public function greatLakes(): void { $this->regional('Great Lakes'); }
    public function southwest(): void { $this->regional('Southwest'); }

    public function rebuild(): void
    {
        Auth::requireLogin();
        (new StrategicWorkforceCompetitiveService())->rebuild();
        (new ExecutivePackagingService())->rebuild();
        $this->redirect($_POST['return_to'] ?? '/strategic-account-intelligence');
    }

    private function regional(string $name): void
    {
        $stmt = Database::connection()->prepare('SELECT id FROM regions WHERE name = ?');
        $stmt->execute([$name]);
        $regionId = (int)$stmt->fetchColumn();
        if (!$regionId) {
            $this->redirect('/strategic-account-intelligence');
        }
        $this->dashboard($regionId, $name . ' Strategic Intelligence', $name . ' account, workforce, and competitor operating view.', 'regional');
    }

    private function dashboard(?int $regionId, string $title, string $subtitle, string $viewMode): void
    {
        Auth::requireLogin();
        $service = new StrategicWorkforceCompetitiveService();
        $service->rebuild();
        $data = $service->dashboardData($regionId);
        $data = $this->filterDashboardData($data);
        $this->view('strategic_intelligence/index', array_merge($data, compact('title', 'subtitle', 'regionId', 'viewMode')));
    }

    private function filterDashboardData(array $data): array
    {
        $allowed = Auth::hasGlobalRegionAccess() ? [] : Auth::allowedRegionNames();
        $query = strtolower(trim((string)($_GET['q'] ?? '')));
        $owner = trim((string)($_GET['owner'] ?? ''));
        $region = trim((string)($_GET['region'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        foreach (['accounts', 'workforce', 'competitors', 'recommendations'] as $key) {
            $data[$key] = array_values(array_filter($data[$key] ?? [], function (array $row) use ($allowed, $query, $owner, $region, $status, $key): bool {
                $rowRegion = (string)($row['region_name'] ?? 'National');
                if (!Auth::hasGlobalRegionAccess() && (!$allowed || !in_array($rowRegion, $allowed, true))) {
                    return false;
                }
                if ($region !== '' && $rowRegion !== $region) {
                    return false;
                }
                $rowOwner = (string)($row['owner'] ?? $row['primary_owner'] ?? $row['assigned_owner'] ?? '');
                if ($owner !== '' && $rowOwner !== $owner) {
                    return false;
                }
                $rowStatus = (string)($row['account_status'] ?? $row['availability_status'] ?? $row['threat_level'] ?? $row['status'] ?? '');
                if ($status !== '' && $rowStatus !== $status) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }
                $haystack = strtolower(implode(' ', array_map(fn($value) => is_scalar($value) ? (string)$value : '', $row)));
                return str_contains($haystack, $query);
            }));
        }

        return $data;
    }
}
