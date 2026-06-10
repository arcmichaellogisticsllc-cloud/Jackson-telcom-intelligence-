<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RecommendationEngine;
use PDO;

class OutreachIntelligenceService
{
    public const TARGET_TYPES = ['Subcontractor','Utility','Prime Contractor','Equipment Seller','Workforce Candidate','Vendor','Relationship Contact','Other'];
    public const CHANNELS = ['Phone','Email','LinkedIn','Facebook Message','In Person','Conference'];
    public const GOALS = ['Recruit Capacity','Open Project Access','Open Prime Access','Open Utility Access','Gather Market Intelligence','Qualify Subcontractor','Request Documents','Schedule Meeting','Ask for Work','Ask for Workers'];
    public const STATUSES = ['Draft','Ready for Human','Used','Completed','Skipped'];

    public function rebuild(): void
    {
        $db = Database::connection();
        $this->ensureQuestionBank($db);
        $this->generateFromDailyActions($db);
        $this->generateFromTargets($db);
        $this->generateFromRelationshipActions($db);
        $this->generateFromCapacityRecruitment($db);
        $this->generateFromCompliance($db);
        $this->generateFromDistributionPlans($db);
        $this->ensureScripts($db);
    }

    public function reviewScript(int $scriptId, string $status): void
    {
        if (!in_array($status, ['Draft','Needs Review','Approved','Rejected','Used'], true)) {
            return;
        }
        Database::connection()->prepare('UPDATE outreach_scripts SET review_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $scriptId]);
    }

    public function saveOutcome(int $outreachId, string $outcomeType, string $notes, ?string $followUpDate, string $createdBy): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM outreach_intelligence WHERE id = ?');
        $stmt->execute([$outreachId]);
        $outreach = $stmt->fetch();
        if (!$outreach) {
            return;
        }
        $db->prepare('INSERT INTO outreach_outcomes (outreach_intelligence_id, outcome_type, outcome_notes, follow_up_date, created_by) VALUES (?, ?, ?, ?, ?)')->execute([$outreachId, $outcomeType, $notes, $followUpDate ?: null, $createdBy]);
        $newStatus = in_array($outcomeType, ['Converted','Meeting Scheduled','Documents Requested','Interested'], true) ? 'Completed' : ($outcomeType === 'Needs Follow-Up' ? 'Used' : 'Used');
        $db->prepare('UPDATE outreach_intelligence SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$newStatus, $outreachId]);
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, owner) VALUES ("outreach_intelligence", ?, ?, "Note", ?, ?, ?)')->execute([
            $outreachId,
            $outreach['region_id'],
            'Outreach outcome: ' . $outcomeType,
            $notes,
            $createdBy,
        ]);

        if ($followUpDate && $outcomeType === 'Needs Follow-Up') {
            $this->createFollowUpAction($db, $outreach, $followUpDate, $notes);
        }

        $this->touchLinkedRecord($db, $outreach, $outcomeType, $notes, $createdBy);
        RecommendationEngine::regenerate();
        (new DecisionSupportService())->rebuild();
    }

    public function queue(?int $regionId = null): array
    {
        $this->rebuild();
        $db = Database::connection();
        return [
            'metrics' => $this->metrics($db, $regionId),
            'items' => $this->items($db, $regionId),
            'critical' => $this->items($db, $regionId, "oi.priority = 'Critical'"),
            'scripts' => $this->scriptsNeedingReview($db, $regionId),
            'channels' => $this->grouped($db, $regionId, 'channel'),
            'goals' => $this->grouped($db, $regionId, 'outreach_goal'),
            'sequences' => $this->sequencePlans($db, $regionId),
        ];
    }

    public function detail(int $id): ?array
    {
        $this->rebuild();
        $db = Database::connection();
        $stmt = $db->prepare('SELECT oi.*, r.name region_name FROM outreach_intelligence oi LEFT JOIN regions r ON r.id = oi.region_id WHERE oi.id = ?');
        $stmt->execute([$id]);
        $outreach = $stmt->fetch();
        if (!$outreach) {
            return null;
        }
        $scripts = $db->prepare('SELECT * FROM outreach_scripts WHERE outreach_intelligence_id = ? ORDER BY id');
        $scripts->execute([$id]);
        $outcomes = $db->prepare('SELECT * FROM outreach_outcomes WHERE outreach_intelligence_id = ? ORDER BY created_at DESC');
        $outcomes->execute([$id]);
        $activities = $db->prepare('SELECT * FROM activities WHERE entity_type = "outreach_intelligence" AND entity_id = ? ORDER BY activity_date DESC');
        $activities->execute([$id]);
        return [
            'outreach' => $outreach,
            'scripts' => $scripts->fetchAll(),
            'outcomes' => $outcomes->fetchAll(),
            'activities' => $activities->fetchAll(),
            'questions' => $this->questionsFor($db, $outreach['target_type']),
        ];
    }

    private function generateFromDailyActions(PDO $db): void
    {
        $rows = $db->query("SELECT da.*, r.name region_name FROM daily_actions da LEFT JOIN regions r ON r.id = da.region_id WHERE da.status IN ('Open','In Progress') ORDER BY da.decision_score DESC LIMIT 25")->fetchAll();
        foreach ($rows as $row) {
            $targetType = $this->targetTypeFromCategory($row['action_category']);
            $this->upsert($db, [
                'outreach_title' => 'Outreach prep: ' . $row['action_title'],
                'target_type' => $targetType,
                'linked_record_type' => 'daily_action',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['owner'], $row['region_name'] ?? null),
                'channel' => $this->channelFor($targetType, $row['recommended_next_step'] ?? ''),
                'outreach_goal' => $this->goalFor($targetType, $row['action_category']),
                'priority' => $row['priority'],
                'reason' => $row['reason'],
                'recommended_opening' => $this->opening($targetType, $row['recommended_next_step'] ?: $row['action_title']),
                'discovery_questions' => $this->questionText($db, $targetType),
                'desired_outcome' => $this->desiredOutcome($targetType, $row['action_category']),
                'status' => 'Ready for Human',
                'notes' => 'Generated from Daily Action #' . $row['id'] . '. No automated sending.',
                'due_date' => $row['due_date'],
            ]);
        }
    }

    private function generateFromTargets(PDO $db): void
    {
        $rows = $db->query("SELECT at.*, r.name region_name FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id WHERE at.status NOT IN ('Converted','Not Fit','Archived') ORDER BY at.acquisition_score DESC LIMIT 35")->fetchAll();
        foreach ($rows as $row) {
            $targetType = $this->normalizeTargetType($row['target_type']);
            $this->upsert($db, [
                'outreach_title' => 'Contact target: ' . $row['target_name'],
                'target_type' => $targetType,
                'linked_record_type' => 'acquisition_target',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['owner'], $row['region_name'] ?? null),
                'channel' => $this->channelFor($targetType, $row['recommended_next_action'] ?? ''),
                'outreach_goal' => $this->goalFor($targetType, 'Acquisition Target'),
                'priority' => $row['priority'],
                'reason' => $row['reason_to_pursue'],
                'recommended_opening' => $this->opening($targetType, $row['recommended_next_action'] ?: $row['reason_to_pursue']),
                'discovery_questions' => $this->questionText($db, $targetType),
                'desired_outcome' => $this->desiredOutcome($targetType, 'Acquisition Target'),
                'status' => (int)$row['acquisition_score'] >= 80 ? 'Ready for Human' : 'Draft',
                'notes' => 'Generated from Acquisition Target #' . $row['id'] . '. Source: ' . $row['source_type'] . '.',
                'due_date' => $row['next_action_due_at'] ?: date('Y-m-d', strtotime('+3 days')),
            ]);
        }
    }

    private function generateFromRelationshipActions(PDO $db): void
    {
        $rows = $db->query("SELECT ra.*, rip.region_id, rip.owner profile_owner, rip.relationship_value_score, c.first_name, c.last_name, c.title, o.name organization_name, r.name region_name FROM relationship_actions ra JOIN relationship_intelligence_profiles rip ON rip.id = ra.relationship_profile_id LEFT JOIN contacts c ON c.id = rip.contact_id LEFT JOIN organizations o ON o.id = rip.organization_id LEFT JOIN regions r ON r.id = rip.region_id WHERE ra.status IN ('Open','In Progress') ORDER BY rip.relationship_value_score DESC LIMIT 25")->fetchAll();
        foreach ($rows as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['organization_name'] ?? 'relationship contact');
            $this->upsert($db, [
                'outreach_title' => 'Relationship outreach: ' . $name,
                'target_type' => 'Relationship Contact',
                'linked_record_type' => 'relationship_action',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['owner'] ?: $row['profile_owner'], $row['region_name'] ?? null),
                'channel' => $row['action_type'] === 'LinkedIn Engagement' ? 'LinkedIn' : ($row['action_type'] === 'Meeting' ? 'In Person' : 'Phone'),
                'outreach_goal' => $this->goalFor('Relationship Contact', $row['action_type']),
                'priority' => (int)$row['relationship_value_score'] >= 85 ? 'Critical' : 'High',
                'reason' => 'Relationship priority and open action require human outreach.',
                'recommended_opening' => $row['recommended_script'] ?: $this->opening('Relationship Contact', $row['notes'] ?? ''),
                'discovery_questions' => $this->questionText($db, 'Relationship Contact'),
                'desired_outcome' => 'Clarify project access, prime access, utility access, market intelligence, or capacity access.',
                'status' => 'Ready for Human',
                'notes' => 'Generated from Relationship Action #' . $row['id'] . '.',
                'due_date' => $row['due_date'] ?: date('Y-m-d', strtotime('+2 days')),
            ]);
        }
    }

    private function generateFromCapacityRecruitment(PDO $db): void
    {
        $rows = $db->query("SELECT crr.*, r.name region_name, r.owner region_owner FROM capacity_recruitment_recommendations crr LEFT JOIN regions r ON r.id = crr.region_id WHERE crr.status = 'Open' ORDER BY crr.needed_count DESC LIMIT 20")->fetchAll();
        foreach ($rows as $row) {
            $this->upsert($db, [
                'outreach_title' => 'Recruit capacity: ' . $row['discipline'] . ' in ' . ($row['region_name'] ?: 'National'),
                'target_type' => 'Subcontractor',
                'linked_record_type' => 'capacity_recruitment_recommendation',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['region_owner'], $row['region_name'] ?? null),
                'channel' => 'Phone',
                'outreach_goal' => 'Recruit Capacity',
                'priority' => $row['urgency'],
                'reason' => $row['reason'],
                'recommended_opening' => 'We are qualifying additional ' . $row['discipline'] . ' telecom construction capacity in ' . ($row['region_name'] ?: 'your region') . '. Are you currently taking on additional work?',
                'discovery_questions' => $this->questionText($db, 'Subcontractor'),
                'desired_outcome' => 'Identify available crews and move qualified subcontractors into the pipeline.',
                'status' => 'Ready for Human',
                'notes' => 'Generated from Capacity Recruitment Recommendation #' . $row['id'] . '.',
                'due_date' => date('Y-m-d', strtotime('+1 day')),
            ]);
        }
    }

    private function generateFromCompliance(PDO $db): void
    {
        $rows = $db->query("SELECT scp.*, s.company_name, s.region_id, r.owner region_owner, r.name region_name FROM subcontractor_compliance_profiles scp JOIN subcontractors s ON s.id = scp.subcontractor_id LEFT JOIN regions r ON r.id = s.region_id WHERE scp.status IN ('Missing','Requested','Expired') LIMIT 25")->fetchAll();
        foreach ($rows as $row) {
            $this->upsert($db, [
                'outreach_title' => 'Request ' . $row['document_type'] . ': ' . $row['company_name'],
                'target_type' => 'Subcontractor',
                'linked_record_type' => 'subcontractor_compliance_profile',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['region_owner'], $row['region_name'] ?? null),
                'channel' => 'Email',
                'outreach_goal' => 'Request Documents',
                'priority' => $row['status'] === 'Expired' ? 'High' : 'Medium',
                'reason' => $row['document_type'] . ' status is ' . $row['status'] . '.',
                'recommended_opening' => 'We are updating subcontractor readiness records and need your current ' . $row['document_type'] . ' before you can be treated as deployable capacity.',
                'discovery_questions' => "Can you send the current document today?\nWho should receive compliance follow-up?\nIs anything blocking approval?",
                'desired_outcome' => 'Receive and review the required document.',
                'status' => 'Ready for Human',
                'notes' => 'Generated from compliance profile #' . $row['id'] . '.',
                'due_date' => date('Y-m-d', strtotime('+2 days')),
            ]);
        }
    }

    private function generateFromDistributionPlans(PDO $db): void
    {
        $rows = $db->query("SELECT dp.*, co.title content_title, co.audience, co.region_id, ch.channel_name, ch.channel_type, r.owner region_owner, r.name region_name FROM distribution_plans dp JOIN content_opportunities co ON co.id = dp.content_id JOIN channels ch ON ch.id = dp.channel_id LEFT JOIN regions r ON r.id = co.region_id WHERE dp.status IN ('Planned','Approved','Scheduled') ORDER BY dp.audience_match_score DESC LIMIT 20")->fetchAll();
        foreach ($rows as $row) {
            $targetType = $this->audienceTarget($row['audience']);
            $this->upsert($db, [
                'outreach_title' => 'Prepare distribution engagement: ' . $row['content_title'],
                'target_type' => $targetType,
                'linked_record_type' => 'distribution_plan',
                'linked_record_id' => (int)$row['id'],
                'region_id' => $row['region_id'],
                'owner' => $this->owner($row['region_owner'], $row['region_name'] ?? null),
                'channel' => $this->channelFromDistribution($row['channel_type']),
                'outreach_goal' => $this->goalFor($targetType, 'Content'),
                'priority' => $row['priority'],
                'reason' => 'Demand distribution plan has a strong audience match for ' . $row['channel_name'] . '.',
                'recommended_opening' => 'This content should be reviewed by a human before any post, comment, or direct share. Use it to start a useful industry conversation.',
                'discovery_questions' => $this->questionText($db, $targetType),
                'desired_outcome' => 'Create relationship, capacity, or opportunity signals from reviewed content distribution.',
                'status' => 'Draft',
                'notes' => 'Generated from Distribution Plan #' . $row['id'] . '. No auto-publishing.',
                'due_date' => date('Y-m-d', strtotime('+4 days')),
            ]);
        }
    }

    private function ensureScripts(PDO $db): void
    {
        foreach ($db->query('SELECT * FROM outreach_intelligence')->fetchAll() as $row) {
            foreach ($this->scriptTypesFor($row) as $type) {
                $exists = $db->prepare('SELECT id FROM outreach_scripts WHERE outreach_intelligence_id = ? AND script_type = ? LIMIT 1');
                $exists->execute([$row['id'], $type]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $db->prepare('INSERT INTO outreach_scripts (outreach_intelligence_id, script_type, subject_line, body, review_status, human_review_required) VALUES (?, ?, ?, ?, "Needs Review", 1)')->execute([
                    $row['id'],
                    $type,
                    $this->subjectLine($row, $type),
                    $this->scriptBody($row, $type),
                ]);
            }
        }
    }

    private function upsert(PDO $db, array $data): void
    {
        $existing = $db->prepare('SELECT id FROM outreach_intelligence WHERE linked_record_type = ? AND linked_record_id = ? AND outreach_goal = ? LIMIT 1');
        $existing->execute([$data['linked_record_type'], $data['linked_record_id'], $data['outreach_goal']]);
        $id = $existing->fetchColumn();
        if ($id) {
            $db->prepare('UPDATE outreach_intelligence SET outreach_title = ?, target_type = ?, region_id = ?, owner = ?, channel = ?, priority = ?, reason = ?, recommended_opening = ?, discovery_questions = ?, desired_outcome = ?, status = CASE WHEN status IN ("Completed","Skipped") THEN status ELSE ? END, notes = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([
                $data['outreach_title'], $data['target_type'], $data['region_id'], $data['owner'], $data['channel'], $data['priority'], $data['reason'], $data['recommended_opening'], $data['discovery_questions'], $data['desired_outcome'], $data['status'], $data['notes'], $data['due_date'], $id,
            ]);
            return;
        }
        $db->prepare('INSERT INTO outreach_intelligence (outreach_title, target_type, linked_record_type, linked_record_id, region_id, owner, channel, outreach_goal, priority, reason, recommended_opening, discovery_questions, desired_outcome, status, notes, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $data['outreach_title'], $data['target_type'], $data['linked_record_type'], $data['linked_record_id'], $data['region_id'], $data['owner'], $data['channel'], $data['outreach_goal'], $data['priority'], $data['reason'], $data['recommended_opening'], $data['discovery_questions'], $data['desired_outcome'], $data['status'], $data['notes'], $data['due_date'],
        ]);
    }

    private function ensureQuestionBank(PDO $db): void
    {
        if ((int)$db->query('SELECT COUNT(*) FROM outreach_discovery_questions')->fetchColumn() > 0) {
            return;
        }
        $questions = [
            'Subcontractor' => ['Are you currently taking on additional telecom construction work?','How many crews can you mobilize?','What regions do you cover?','What services do you self-perform?','Do you have current COI/W9 ready?'],
            'Equipment Seller' => ['Are you upgrading, downsizing, or exiting a market?','Are you currently performing telecom construction work?','Would you consider subcontracting?'],
            'Utility' => ['What construction programs are active this year?','Where do you need additional capacity?','Who manages field construction?'],
            'Prime Contractor' => ['Where do you need subcontractor support?','What markets are under capacity pressure?','What qualification requirements do you have?'],
            'Relationship Contact' => ['What work or capacity pressure are you seeing right now?','Who else should Jackson know in this market?','What should we understand before we pursue this opportunity?'],
            'Workforce Candidate' => ['What telecom construction roles have you performed?','Can you travel?','When are you available?'],
            'Vendor' => ['What regions can you support?','What lead times should we expect?','What construction needs do you support best?'],
        ];
        $stmt = $db->prepare('INSERT INTO outreach_discovery_questions (target_type, question, sort_order) VALUES (?, ?, ?)');
        foreach ($questions as $targetType => $items) {
            foreach ($items as $index => $question) {
                $stmt->execute([$targetType, $question, $index + 1]);
            }
        }
    }

    private function createFollowUpAction(PDO $db, array $outreach, string $followUpDate, string $notes): void
    {
        $score = new DecisionScoringService();
        $decisionScore = $score->score(['impact_score' => 65, 'urgency_score' => 70, 'confidence_score' => 70, 'relationship_value' => 60]);
        $db->prepare('INSERT INTO daily_actions (action_title, action_category, region_id, owner, priority, reason, recommended_next_step, linked_record_type, linked_record_id, due_date, impact_score, urgency_score, confidence_score, decision_score) VALUES (?, "Relationship", ?, ?, ?, ?, ?, "outreach_intelligence", ?, ?, 65, 70, 70, ?)')->execute([
            'Follow up outreach: ' . $outreach['outreach_title'],
            $outreach['region_id'],
            $outreach['owner'],
            $score->priorityFromScore($decisionScore),
            'Outreach outcome requires follow-up.',
            $notes ?: 'Follow up on outreach outcome.',
            $outreach['id'],
            $followUpDate,
            $decisionScore,
        ]);
    }

    private function touchLinkedRecord(PDO $db, array $outreach, string $outcomeType, string $notes, string $owner): void
    {
        $type = $outreach['linked_record_type'];
        $id = (int)$outreach['linked_record_id'];
        if (!$type || !$id) {
            return;
        }
        if ($type === 'acquisition_target') {
            $status = match ($outcomeType) {
                'Interested', 'Meeting Scheduled', 'Documents Requested' => 'Engaged',
                'Converted' => 'Converted',
                'Not Interested', 'Bad Data' => 'Not Fit',
                default => null,
            };
            if ($status) {
                $db->prepare('UPDATE acquisition_targets SET status = ?, last_touched_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $id]);
                if ($status === 'Converted') {
                    $this->createSubcontractorFromConvertedTarget($db, $id, $owner);
                }
            } else {
                $db->prepare('UPDATE acquisition_targets SET last_touched_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            }
        }
        if ($type === 'relationship_action' && in_array($outcomeType, ['Meeting Scheduled','Interested','Converted'], true)) {
            $db->prepare('UPDATE relationship_actions SET status = "Completed", outcome = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$outcomeType . ': ' . $notes, $id]);
        }
    }

    private function createSubcontractorFromConvertedTarget(PDO $db, int $targetId, string $owner): void
    {
        $stmt = $db->prepare('SELECT * FROM acquisition_targets WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target || ($target['target_type'] ?? '') !== 'Subcontractor') {
            return;
        }
        $name = trim((string)($target['organization_name'] ?: $target['target_name']));
        if ($name === '') {
            return;
        }
        $org = $db->prepare('SELECT id FROM organizations WHERE LOWER(name) = LOWER(?) AND region_id = ? LIMIT 1');
        $org->execute([$name, $target['region_id']]);
        $orgId = (int)$org->fetchColumn();
        if (!$orgId) {
            $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, "Fiber Construction Contractor", ?, ?, ?, ?, ?, ?, "Prospect")')
                ->execute([$name, $target['region_id'], $target['state'], $target['city'], $target['website'], $target['phone'], 'Created from converted outreach target #' . $targetId . '.']);
            $orgId = (int)$db->lastInsertId();
        }
        $sub = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? LIMIT 1');
        $sub->execute([$orgId]);
        $subcontractorId = (int)$sub->fetchColumn();
        if (!$subcontractorId) {
            $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, website, phone, email, primary_contact, states_served, markets_served, services_offered, insurance_status, w9_status, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Missing", "Missing", "Researching", "Limited", ?)')
                ->execute([$orgId, $target['region_id'], $name, $target['website'], $target['phone'], $target['email'], $target['contact_name'], $target['state'], trim((string)($target['city'] . ' ' . $target['state'])), 'Telecom Construction', 'Created from converted outreach target #' . $targetId . '. Human onboarding review required.']);
            $subcontractorId = (int)$db->lastInsertId();
        }
        (new OnboardingService())->ensureSubcontractorOnboarding($subcontractorId);
        $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES ("subcontractor", ?, ?, "Created", "Subcontractor created from outreach conversion", ?, CURRENT_TIMESTAMP, ?)')
            ->execute([$subcontractorId, $target['region_id'], 'Converted outreach target created/linked subcontractor onboarding.', $owner]);
    }

    private function metrics(PDO $db, ?int $regionId): array
    {
        $where = $regionId ? ' AND region_id = ' . (int)$regionId : $this->allowedRegionSql('region_id', ' AND ');
        return [
            'ready' => (int)$db->query("SELECT COUNT(*) FROM outreach_intelligence WHERE status = 'Ready for Human' {$where}")->fetchColumn(),
            'critical' => (int)$db->query("SELECT COUNT(*) FROM outreach_intelligence WHERE priority = 'Critical' AND status NOT IN ('Completed','Skipped') {$where}")->fetchColumn(),
            'overdue' => (int)$db->query("SELECT COUNT(*) FROM outreach_intelligence WHERE due_date < date('now') AND status NOT IN ('Completed','Skipped') {$where}")->fetchColumn(),
            'capacity' => (int)$db->query("SELECT COUNT(*) FROM outreach_intelligence WHERE outreach_goal IN ('Recruit Capacity','Qualify Subcontractor','Request Documents') {$where}")->fetchColumn(),
            'relationship' => (int)$db->query("SELECT COUNT(*) FROM outreach_intelligence WHERE target_type = 'Relationship Contact' {$where}")->fetchColumn(),
        ];
    }

    private function items(PDO $db, ?int $regionId, string $extra = '1 = 1', int $limit = 100): array
    {
        $sql = "SELECT oi.*, r.name region_name FROM outreach_intelligence oi LEFT JOIN regions r ON r.id = oi.region_id WHERE {$extra}";
        $params = [];
        if ($regionId) {
            $sql .= ' AND oi.region_id = ?';
            $params[] = $regionId;
        } elseif (!Auth::hasGlobalRegionAccess()) {
            $ids = Auth::allowedRegionIds();
            $sql .= $ids ? ' AND (oi.region_id IS NULL OR oi.region_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))' : ' AND 1=0';
            $params = array_merge($params, $ids);
        }
        $sql .= " ORDER BY CASE oi.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, oi.due_date ASC LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function scriptsNeedingReview(PDO $db, ?int $regionId): array
    {
        $sql = "SELECT os.*, oi.outreach_title, oi.region_id, r.name region_name FROM outreach_scripts os JOIN outreach_intelligence oi ON oi.id = os.outreach_intelligence_id LEFT JOIN regions r ON r.id = oi.region_id WHERE os.review_status IN ('Draft','Needs Review')";
        $params = [];
        if ($regionId) {
            $sql .= ' AND oi.region_id = ?';
            $params[] = $regionId;
        } elseif (!Auth::hasGlobalRegionAccess()) {
            $ids = Auth::allowedRegionIds();
            $sql .= $ids ? ' AND (oi.region_id IS NULL OR oi.region_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))' : ' AND 1=0';
            $params = array_merge($params, $ids);
        }
        $sql .= ' ORDER BY os.updated_at DESC LIMIT 12';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function grouped(PDO $db, ?int $regionId, string $field): array
    {
        $allowed = ['channel','outreach_goal'];
        if (!in_array($field, $allowed, true)) {
            return [];
        }
        $sql = "SELECT {$field} label, COUNT(*) count FROM outreach_intelligence WHERE status NOT IN ('Completed','Skipped')";
        if ($regionId) {
            $sql .= ' AND region_id = ' . (int)$regionId;
        } else {
            $sql .= $this->allowedRegionSql('region_id', ' AND ');
        }
        $sql .= " GROUP BY {$field} ORDER BY count DESC";
        return $db->query($sql)->fetchAll();
    }

    private function sequencePlans(PDO $db, ?int $regionId): array
    {
        $sql = 'SELECT os.*, r.name region_name FROM outreach_sequences os LEFT JOIN regions r ON r.id = os.region_id WHERE os.status = "Planned"';
        if ($regionId) {
            $sql .= ' AND (os.region_id = ' . (int)$regionId . ' OR os.region_id IS NULL)';
        } elseif (!Auth::hasGlobalRegionAccess()) {
            $ids = Auth::allowedRegionIds();
            $sql .= $ids ? ' AND (os.region_id IS NULL OR os.region_id IN (' . implode(',', array_map('intval', $ids)) . '))' : ' AND 1=0';
        }
        $sql .= ' ORDER BY os.name, os.step_number LIMIT 40';
        return $db->query($sql)->fetchAll();
    }

    private function allowedRegionSql(string $column, string $prefix = ''): string
    {
        if (Auth::hasGlobalRegionAccess()) {
            return '';
        }
        $ids = Auth::allowedRegionIds();
        if (!$ids) {
            return $prefix . '1=0';
        }
        return $prefix . '(' . $column . ' IS NULL OR ' . $column . ' IN (' . implode(',', array_map('intval', $ids)) . '))';
    }

    private function questionsFor(PDO $db, string $targetType): array
    {
        $stmt = $db->prepare('SELECT * FROM outreach_discovery_questions WHERE active = 1 AND target_type IN (?, "Other") ORDER BY sort_order');
        $stmt->execute([$targetType]);
        return $stmt->fetchAll();
    }

    private function questionText(PDO $db, string $targetType): string
    {
        $questions = $this->questionsFor($db, $targetType);
        return implode("\n", array_map(fn($q) => $q['question'], $questions));
    }

    private function normalizeTargetType(string $type): string
    {
        return match ($type) {
            'Municipality' => 'Utility',
            'Engineering Firm' => 'Vendor',
            default => in_array($type, self::TARGET_TYPES, true) ? $type : 'Other',
        };
    }

    private function targetTypeFromCategory(string $category): string
    {
        return match ($category) {
            'Capacity', 'Subcontractor' => 'Subcontractor',
            'Relationship' => 'Relationship Contact',
            'Opportunity' => 'Prime Contractor',
            'Content', 'Demand' => 'Prime Contractor',
            default => 'Other',
        };
    }

    private function audienceTarget(?string $audience): string
    {
        return match ($audience) {
            'Subcontractor' => 'Subcontractor',
            'Utility' => 'Utility',
            'Prime Contractor' => 'Prime Contractor',
            'Workforce' => 'Workforce Candidate',
            'Vendor' => 'Vendor',
            default => 'Other',
        };
    }

    private function channelFor(string $targetType, string $text): string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'document') || str_contains($lower, 'w9') || str_contains($lower, 'coi')) {
            return 'Email';
        }
        return match ($targetType) {
            'Subcontractor', 'Equipment Seller', 'Relationship Contact' => 'Phone',
            'Prime Contractor', 'Utility', 'Vendor' => 'Email',
            'Workforce Candidate' => 'LinkedIn',
            default => 'Phone',
        };
    }

    private function channelFromDistribution(string $channelType): string
    {
        return match ($channelType) {
            'LinkedIn' => 'LinkedIn',
            'Facebook Group' => 'Facebook Message',
            'Conference' => 'Conference',
            default => 'Email',
        };
    }

    private function goalFor(string $targetType, string $context): string
    {
        $text = strtolower($targetType . ' ' . $context);
        return match (true) {
            str_contains($text, 'document'), str_contains($text, 'compliance') => 'Request Documents',
            str_contains($text, 'utility') => 'Open Utility Access',
            str_contains($text, 'prime') => 'Open Prime Access',
            str_contains($text, 'relationship'), str_contains($text, 'project manager') => 'Open Project Access',
            str_contains($text, 'equipment') => 'Gather Market Intelligence',
            str_contains($text, 'workforce') => 'Ask for Workers',
            str_contains($text, 'subcontractor'), str_contains($text, 'capacity') => 'Recruit Capacity',
            default => 'Schedule Meeting',
        };
    }

    private function desiredOutcome(string $targetType, string $context): string
    {
        return match ($this->goalFor($targetType, $context)) {
            'Recruit Capacity' => 'Confirm service fit, crew count, mobilization, and next qualification step.',
            'Request Documents' => 'Receive required documents and remove compliance blocker.',
            'Open Project Access' => 'Create a useful project access conversation and next relationship action.',
            'Open Prime Access' => 'Identify subcontractor onboarding path and decision contact.',
            'Open Utility Access' => 'Identify active construction programs and field decision makers.',
            'Ask for Workers' => 'Identify available workforce or candidate referral path.',
            default => 'Schedule a qualified next step and record outcome notes.',
        };
    }

    private function opening(string $targetType, string $context): string
    {
        return match ($targetType) {
            'Subcontractor' => 'Are you currently taking on additional telecom construction work in this region?',
            'Equipment Seller' => 'Are you selling equipment because you are upgrading, downsizing, or exiting a market?',
            'Utility' => 'Jackson Telcom supports telecom construction capacity, and we would like to understand upcoming build needs.',
            'Prime Contractor' => 'Jackson Telcom is building deployable subcontractor and field capacity across this region.',
            'Relationship Contact' => $context ?: 'I wanted to reconnect and understand where field capacity or project support may be needed.',
            'Workforce Candidate' => 'Are you open to field telecom construction opportunities with Jackson Telcom?',
            default => $context ?: 'I am reaching out to understand whether there is a useful acquisition or partnership conversation here.',
        };
    }

    private function scriptTypesFor(array $outreach): array
    {
        $types = ['Call Opener', 'Follow-Up'];
        if ($outreach['channel'] === 'Email') {
            $types[] = 'Email Draft';
        } elseif ($outreach['channel'] === 'LinkedIn') {
            $types[] = 'LinkedIn Message';
        } elseif ($outreach['channel'] === 'Facebook Message') {
            $types[] = 'Facebook Message';
        } elseif (in_array($outreach['channel'], ['In Person','Conference'], true)) {
            $types[] = 'Meeting Agenda';
        }
        return array_unique($types);
    }

    private function subjectLine(array $outreach, string $type): string
    {
        return match ($type) {
            'Email Draft' => 'Jackson Telcom - ' . $outreach['outreach_goal'],
            'LinkedIn Message', 'Facebook Message' => 'Quick telecom construction question',
            'Meeting Agenda' => 'Agenda: ' . $outreach['outreach_goal'],
            default => $outreach['outreach_title'],
        };
    }

    private function scriptBody(array $outreach, string $type): string
    {
        $body = $outreach['recommended_opening'] . "\n\n";
        $body .= "Why this matters: {$outreach['reason']}\n\n";
        $body .= "Discovery questions:\n{$outreach['discovery_questions']}\n\n";
        $body .= "Desired outcome: {$outreach['desired_outcome']}\n\n";
        $body .= "Human review required. Do not send automatically.";
        return $body;
    }

    private function owner(?string $owner, ?string $regionName): string
    {
        if ($owner && $owner !== 'Unassigned') {
            return (new OwnerModelService())->normalizeOwner($owner, $owner);
        }
        return (new OwnerModelService())->ownerForRegionName((string)($regionName ?: 'National'), 'relationship_opportunity');
    }
}
