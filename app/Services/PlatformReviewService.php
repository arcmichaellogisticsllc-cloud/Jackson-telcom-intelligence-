<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class PlatformReviewService
{
    public function rebuild(): void
    {
        $db = Database::connection();
        $db->exec('DELETE FROM platform_health_checks');
        $db->exec("DELETE FROM sqlite_sequence WHERE name = 'platform_health_checks'");
        $db->exec('DELETE FROM operator_modes');
        $db->exec("DELETE FROM sqlite_sequence WHERE name = 'operator_modes'");
        $this->buildHealthChecks($db);
        $this->buildOperatorModes($db);
    }

    public function dashboardData(): array
    {
        $db = Database::connection();
        return [
            'operatingView' => $this->operatingView($db),
            'health' => $db->query('SELECT * FROM platform_health_checks ORDER BY CASE status WHEN "Fail" THEN 1 WHEN "Warn" THEN 2 ELSE 3 END, issue_count DESC')->fetchAll(),
            'modes' => $db->query('SELECT * FROM operator_modes WHERE active = 1 ORDER BY id')->fetchAll(),
            'duplicates' => $this->duplicateConcepts($db),
        ];
    }

    private function buildHealthChecks(PDO $db): void
    {
        $checks = [
            ['Signal Center', 'Data Freshness', $this->count($db, "SELECT COUNT(*) FROM signals WHERE updated_at < datetime('now','-30 days')"), 'Signals older than 30 days need review.', 'Run acquisition cycle and review stale signal classes.'],
            ['Recommendations', 'Stale Actions', $this->count($db, "SELECT COUNT(*) FROM recommended_actions WHERE status = 'Open' AND updated_at < datetime('now','-14 days')"), 'Open recommendations can become noise if not promoted or dismissed.', 'Promote only the top recommendations into Daily Actions and dismiss stale findings.'],
            ['Daily Actions', 'Stale Actions', $this->count($db, "SELECT COUNT(*) FROM daily_actions WHERE status = 'Open' AND due_date < date('now','-7 days')"), 'Overdue daily actions reduce operator trust.', 'Complete, defer, or dismiss overdue daily actions.'],
            ['Relationships', 'Broken Relationships', $this->count($db, 'SELECT COUNT(*) FROM relationship_intelligence_profiles WHERE contact_id NOT IN (SELECT id FROM contacts) AND contact_id IS NOT NULL'), 'Relationship profiles should point to valid contacts.', 'Regenerate relationship intelligence and clean orphaned profiles.'],
            ['Acquisition Targets', 'Duplicate Records', $this->count($db, 'SELECT COUNT(*) FROM (SELECT target_name, region_id, COUNT(*) c FROM acquisition_targets GROUP BY target_name, region_id HAVING c > 1)'), 'Duplicate targets create duplicate hunts and outreach prep.', 'Merge duplicate targets before assigning hunts.'],
            ['Activities', 'Orphaned Records', $this->orphanedActivities($db), 'Activity records should point to existing operational records.', 'Run integrity check and clean orphaned activity references.'],
            ['Navigation', 'Complexity', max(0, $this->count($db, 'SELECT COUNT(*) FROM recommended_actions WHERE status = "Open"') - 200), 'Too many open findings make the platform feel like 500 things.', 'Use Executive Operating View and operator modes to focus the next 5 actions.'],
        ];
        $stmt = $db->prepare('INSERT INTO platform_health_checks (module_name, check_type, status, issue_count, summary, recommended_action) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($checks as [$module, $type, $count, $summary, $action]) {
            $status = $count === 0 ? 'Pass' : ($count > 20 ? 'Fail' : 'Warn');
            $stmt->execute([$module, $type, $status, $count, $summary, $action]);
        }
    }

    private function buildOperatorModes(PDO $db): void
    {
        $stmt = $db->prepare('INSERT INTO operator_modes (mode_name, objective, primary_screen, focus_categories, top_metrics, recommended_workflow) VALUES (?, ?, ?, ?, ?, ?)');
        $rows = [
            ['Executive Mode', 'See the five things that matter today across Jackson Telcom.', '/', 'Work, Capacity, Need, Influence, Today Priorities', 'Top Actions, Growth Blockers, Ready for SyncERP, Platform Health', 'Open Jackson Telcom Command Center, decide priorities, assign owners, and dismiss noise.'],
            ['Mike Mode', 'Let Mike work Southeast plus shared Southwest and national priorities.', '/command/southeast', 'Southeast Work, Capacity, Need, Influence, Actions', 'Capacity gaps, top relationships, work-ready organizations, SyncERP handoffs', 'Open Southeast Command Center, complete top actions, and record outcomes.'],
            ['Ron Mode', 'Let Ron work Great Lakes plus shared Southwest and national priorities.', '/command/great-lakes', 'Great Lakes Work, Capacity, Need, Influence, Actions', 'Capacity gaps, top relationships, work-ready organizations, SyncERP handoffs', 'Open Great Lakes Command Center, complete top actions, and record outcomes.'],
            ['Regional Owner Mode', 'Use the matching theater command center without national clutter.', '/regions', 'Theater Work, Theater Capacity, Theater Need, Theater Influence', 'Regional readiness, watchlists, relationship risk, capacity gaps', 'Choose the theater, work the priorities panel, and update linked records.'],
            ['Admin Mode', 'Maintain system reliability, health, seed mode, backups, and operator readiness.', '/platform-review', 'Health, Integrity, Navigation, Seeds, Backup, Security', 'Health checks, integrity warnings, stale actions, release gate', 'Run release checks, review warnings, and keep operator screens clean.'],
        ];
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private function operatingView(PDO $db): array
    {
        return [
            'work' => $db->query('SELECT wi.*, o.name organization_name, r.name region_name FROM work_intelligence wi LEFT JOIN organizations o ON o.id = wi.organization_id LEFT JOIN regions r ON r.id = wi.region_id ORDER BY wi.work_readiness_score DESC LIMIT 5')->fetchAll(),
            'capacity' => $db->query('SELECT ci.*, cp.profile_name, r.name region_name FROM capacity_intelligence ci LEFT JOIN capacity_profiles cp ON cp.id = ci.capacity_profile_id LEFT JOIN regions r ON r.id = ci.region_id ORDER BY ci.deployable_capacity_score DESC LIMIT 5')->fetchAll(),
            'need' => $db->query('SELECT ni.*, o.name organization_name, r.name region_name FROM need_intelligence ni LEFT JOIN organizations o ON o.id = ni.organization_id LEFT JOIN regions r ON r.id = ni.region_id ORDER BY ni.need_score DESC LIMIT 5')->fetchAll(),
            'influence' => $db->query('SELECT ii.*, c.first_name, c.last_name, o.name organization_name, r.name region_name FROM influence_intelligence ii JOIN relationship_intelligence_profiles rip ON rip.id = ii.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = ii.region_id ORDER BY ii.final_influence_score DESC LIMIT 5')->fetchAll(),
            'nextActions' => $db->query('SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status = "Open" ORDER BY da.decision_score DESC LIMIT 5')->fetchAll(),
        ];
    }

    private function duplicateConcepts(PDO $db): array
    {
        return [
            ['concept' => 'Recommendations', 'role' => 'System findings and raw decision signals.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM recommended_actions WHERE status = "Open"')],
            ['concept' => 'Daily Actions', 'role' => 'Prioritized operator actions promoted from findings.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM daily_actions WHERE status = "Open"')],
            ['concept' => 'Growth Blockers', 'role' => 'Structural issues blocking growth.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM growth_blockers WHERE status = "Open"')],
            ['concept' => 'Escalations', 'role' => 'High-value signals requiring attention.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM signal_quality_profiles WHERE classification = "Escalate"')],
            ['concept' => 'Targets', 'role' => 'Entities worth hunting.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM acquisition_targets WHERE status NOT IN ("Converted","Archived","Not Fit")')],
            ['concept' => 'Hunts', 'role' => 'Structured pursuit of target groups.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM hunts WHERE status = "Active"')],
            ['concept' => 'Watchlists', 'role' => 'Monitoring buckets for future movement.', 'count' => $this->count($db, 'SELECT COUNT(*) FROM acquisition_watchlists WHERE status != "Archived"')],
        ];
    }

    private function count(PDO $db, string $sql): int
    {
        return (int)$db->query($sql)->fetchColumn();
    }

    private function orphanedActivities(PDO $db): int
    {
        $map = ['signal' => 'signals', 'organization' => 'organizations', 'contact' => 'contacts', 'subcontractor' => 'subcontractors', 'opportunity' => 'opportunities', 'daily_action' => 'daily_actions'];
        $count = 0;
        foreach ($map as $type => $table) {
            $count += $this->count($db, 'SELECT COUNT(*) FROM activities WHERE entity_type = ' . $db->quote($type) . " AND entity_id NOT IN (SELECT id FROM {$table})");
        }
        return $count;
    }
}
