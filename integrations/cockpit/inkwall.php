<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../inkwall/app.php';

$user = admin_require_login();
$pdo = inkwall_db();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
    $id = (string)($_POST['id'] ?? '');
    $status = (string)($_POST['status'] ?? '');
    if (preg_match('/^[a-f0-9-]{20,40}$/i', $id) && in_array($status, ['published', 'held', 'rejected', 'deleted'], true)) {
        $stmt = $pdo->prepare('UPDATE inkwall_notes SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, inkwall_now(), $id]);
        $notice = 'Note status updated.';
    }
}

$statusFilter = (string)($_GET['status'] ?? 'all');
$visitorFilter = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['visitor'] ?? '')));
$where = []; $params = [];
if (in_array($statusFilter, ['published', 'held', 'rejected', 'deleted'], true)) { $where[] = 'n.status = ?'; $params[] = $statusFilter; }
if ($visitorFilter !== '') { $where[] = 'n.visitor_hash LIKE ?'; $params[] = $visitorFilter . '%'; }
$sql = 'SELECT n.*, COUNT(DISTINCT r.id) AS report_count, COUNT(DISTINCT x.id) AS reaction_count FROM inkwall_notes n LEFT JOIN inkwall_reports r ON r.note_id=n.id LEFT JOIN inkwall_reactions x ON x.note_id=n.id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' GROUP BY n.id ORDER BY n.created_at DESC LIMIT 120';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $notes = $stmt->fetchAll();

$totals = $pdo->query("SELECT
    (SELECT COUNT(*) FROM inkwall_notes) notes,
    (SELECT COUNT(*) FROM inkwall_notes WHERE status='published') published,
    (SELECT COUNT(*) FROM inkwall_notes WHERE status='held') held,
    (SELECT COUNT(*) FROM inkwall_reports) reports,
    (SELECT COUNT(*) FROM inkwall_events) events,
    (SELECT COUNT(DISTINCT visitor_hash) FROM inkwall_events) visitors,
    (SELECT COUNT(*) FROM inkwall_notes WHERE image_bytes > 0) image_notes")->fetch();
$eventBreakdown = $pdo->query('SELECT event_type label, COUNT(*) count FROM inkwall_events GROUP BY event_type ORDER BY count DESC')->fetchAll();
$countryBreakdown = $pdo->query('SELECT country label, COUNT(*) count FROM inkwall_events GROUP BY country ORDER BY count DESC LIMIT 12')->fetchAll();
$referrerBreakdown = $pdo->query('SELECT referrer_host label, COUNT(*) count FROM inkwall_events GROUP BY referrer_host ORDER BY count DESC LIMIT 12')->fetchAll();
$recentReports = $pdo->query('SELECT r.*, n.author_name, n.message_text, n.status FROM inkwall_reports r JOIN inkwall_notes n ON n.id=r.note_id ORDER BY r.created_at DESC LIMIT 30')->fetchAll();
$aiReviewRows = $pdo->query("SELECT ai_review_json FROM inkwall_notes WHERE ai_review_json IS NOT NULL AND ai_review_json <> '{}' ORDER BY created_at DESC LIMIT 1000")->fetchAll();
$visitorEvents = [];
if ($visitorFilter !== '') {
    $ev = $pdo->prepare('SELECT * FROM inkwall_events WHERE visitor_hash LIKE ? ORDER BY created_at DESC LIMIT 100');
    $ev->execute([$visitorFilter . '%']); $visitorEvents = $ev->fetchAll();
}
function iw_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function iw_short_hash(string $hash): string { return substr($hash, 0, 12); }
function iw_json_list(mixed $json): string {
    $data = json_decode((string)$json, true);
    if (!is_array($data) || !$data) return 'none';
    return implode(', ', array_map(static fn($value): string => (string)$value, array_slice($data, 0, 8)));
}
function iw_review_chain(mixed $json): array {
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}
function iw_review_summary(mixed $json): string {
    $chain = iw_review_chain($json);
    if (!$chain) return 'none';
    $parts = [];
    foreach (['text', 'image'] as $channel) {
        if (!is_array($chain[$channel] ?? null)) continue;
        $step = $chain[$channel];
        $flags = is_array($step['flags'] ?? null) && $step['flags'] ? ' flags ' . implode(',', array_slice(array_map('strval', $step['flags']), 0, 4)) : '';
        $latency = isset($step['latency_ms']) ? ' ' . (int)$step['latency_ms'] . 'ms' : '';
        $parts[] = $channel . ' ' . ($step['provider'] ?? 'unknown') . '/' . ($step['model'] ?? 'model') . ' ' . ($step['decision'] ?? 'review') . $latency . $flags;
    }
    return $parts ? implode(' | ', $parts) : 'none';
}
function iw_ai_rows(array $rows): array {
    $map = [];
    foreach ($rows as $row) {
        foreach (iw_review_chain($row['ai_review_json'] ?? '{}') as $channel => $step) {
            if (!is_array($step)) continue;
            $key = (string)$channel . ' · ' . (string)($step['provider'] ?? 'unknown') . ' · ' . (string)($step['model'] ?? 'model');
            $map[$key] = ($map[$key] ?? 0) + 1;
        }
    }
    arsort($map);
    $out = [];
    foreach (array_slice($map, 0, 12, true) as $label => $count) $out[] = ['label' => $label, 'count' => $count];
    return $out;
}
function iw_bar_rows(array $rows): string {
    $max = max(1, ...array_map(static fn(array $row): int => (int)$row['count'], $rows));
    $html = '';
    foreach ($rows as $row) {
        $width = max(3, (int)round(((int)$row['count'] / $max) * 100));
        $html .= '<div class="bar"><span>' . iw_e($row['label']) . '</span><i style="--w:' . $width . '%"></i><b>' . (int)$row['count'] . '</b></div>';
    }
    return $html ?: '<p class="muted">No data yet.</p>';
}
$aiBreakdown = iw_ai_rows($aiReviewRows);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive"><title>InkWall · Cockpit</title>
<style>
:root{color-scheme:dark;--bg:#09090d;--card:#121219;--line:#292934;--text:#f4f3ee;--muted:#9897a4;--purple:#8067ff;--green:#39c987;--red:#f26464;--amber:#e5ae55}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 Inter,ui-sans-serif,system-ui,sans-serif}a{color:#a99aff;text-decoration:none}button,select{font:inherit}.shell{max-width:1480px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:24px}.eyebrow{font:700 11px ui-monospace,monospace;letter-spacing:.16em;color:#9e91ff}.top h1{margin:4px 0 0;font-size:32px;letter-spacing:-.04em}.top nav{display:flex;gap:9px}.btn{border:1px solid var(--line);border-radius:9px;padding:9px 12px;color:var(--text);background:#17171f;cursor:pointer}.btn--green{border-color:#246d50;color:#81e6b5}.btn--amber{border-color:#7e5d29;color:#ffd28b}.btn--red{border-color:#783b3b;color:#ff9c9c}.notice{padding:10px 13px;background:#173b2c;border:1px solid #286849;border-radius:9px;margin-bottom:18px}.metrics{display:grid;grid-template-columns:repeat(7,minmax(105px,1fr));gap:12px;margin-bottom:18px}.metric,.panel{background:var(--card);border:1px solid var(--line);border-radius:14px}.metric{padding:16px}.metric span{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.1em}.metric strong{display:block;font:700 27px ui-monospace,monospace;margin-top:7px}.charts{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px}.panel{padding:18px}.panel h2{font-size:14px;margin:0 0 14px}.bar{display:grid;grid-template-columns:110px 1fr 42px;gap:10px;align-items:center;margin:9px 0;font-size:12px}.bar span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c9c8d2}.bar i{height:6px;border-radius:9px;background:linear-gradient(90deg,var(--purple) var(--w),#24242e var(--w))}.bar b{text-align:right;font:600 12px ui-monospace,monospace}.filters{display:flex;gap:8px;align-items:center;margin:0 0 12px;flex-wrap:wrap}.filters a{padding:6px 10px;border:1px solid var(--line);border-radius:99px;color:var(--muted)}.filters a.active{background:#28233f;color:#d8d1ff;border-color:#55488a}.notes{display:grid;gap:10px}.note{display:grid;grid-template-columns:minmax(300px,1.5fr) minmax(200px,.75fr) auto;gap:18px;padding:16px;border:1px solid var(--line);border-radius:11px;background:#0e0e14}.note h3{font-size:14px;margin:0 0 5px}.note p{margin:0;color:#d2d1d8}.meta{font:11px/1.7 ui-monospace,monospace;color:var(--muted)}.pill{display:inline-flex;border:1px solid var(--line);padding:2px 7px;border-radius:99px;margin-right:4px}.pill.published{color:#75dfa8;border-color:#276747}.pill.held{color:#ffd28b;border-color:#775825}.pill.rejected,.pill.deleted{color:#ff9d9d;border-color:#743b3b}.actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.actions form{display:inline}.reports{width:100%;border-collapse:collapse}.reports th,.reports td{text-align:left;border-bottom:1px solid var(--line);padding:10px 7px;vertical-align:top}.reports th{font-size:11px;color:var(--muted);text-transform:uppercase}.muted{color:var(--muted)}.section-title{display:flex;justify-content:space-between;align-items:center;margin:26px 0 10px}.section-title h2{margin:0}.timeline{font:12px ui-monospace,monospace}.timeline div{padding:8px 0;border-bottom:1px solid var(--line)}@media(max-width:1000px){.metrics{grid-template-columns:repeat(3,1fr)}.charts{grid-template-columns:1fr}.note{grid-template-columns:1fr}.actions{justify-content:flex-start}}@media(max-width:600px){.shell{padding:18px}.metrics{grid-template-columns:repeat(2,1fr)}.top{align-items:flex-start}.top nav{flex-direction:column}.reports{display:block;overflow:auto}}
</style>
</head>
<body><main class="shell">
<header class="top"><div><span class="eyebrow">PUBLIC SURFACE / PRIVATE CONTROL</span><h1>InkWall Cockpit</h1></div><nav><a class="btn" href="https://angusu.de/inkwall/" target="_blank" rel="noopener">Open live surface ↗</a><a class="btn" href="<?= iw_e(admin_url('index.php')) ?>">Back to cockpit</a></nav></header>
<?php if ($notice): ?><div class="notice"><?= iw_e($notice) ?></div><?php endif ?>
<section class="metrics">
<?php foreach (['Notes'=>'notes','Published'=>'published','Held'=>'held','Reports'=>'reports','Events'=>'events','Pseudonyms'=>'visitors','With image'=>'image_notes'] as $label=>$key): ?><div class="metric"><span><?= iw_e($label) ?></span><strong><?= (int)($totals[$key]??0) ?></strong></div><?php endforeach ?>
</section>
<section class="charts"><div class="panel"><h2>Usage events</h2><?= iw_bar_rows($eventBreakdown) ?></div><div class="panel"><h2>AI channels</h2><?= iw_bar_rows($aiBreakdown) ?></div><div class="panel"><h2>Referrer domains</h2><?= iw_bar_rows($referrerBreakdown) ?></div></section>

<div class="section-title"><h2>Messages</h2><span class="muted">No raw IP, browser fingerprint, full referrer URL or identity stored.</span></div>
<nav class="filters"><?php foreach (['all','published','held','rejected','deleted'] as $status): ?><a class="<?= $statusFilter===$status?'active':'' ?>" href="?status=<?= $status ?>"><?= ucfirst($status) ?></a><?php endforeach ?><?php if ($visitorFilter): ?><a class="active" href="?visitor=<?= iw_e($visitorFilter) ?>">Pseudonym <?= iw_e(substr($visitorFilter,0,12)) ?>… ×</a><?php endif ?></nav>
<section class="notes">
<?php if (!$notes): ?><div class="panel muted">No matching messages.</div><?php endif ?>
<?php foreach ($notes as $note): $layout = inkwall_layout(json_decode((string)($note['layout_json'] ?? '{}'), true)); ?><article class="note"><div><h3><?= iw_e($note['author_name']) ?> <span class="pill <?= iw_e($note['status']) ?>"><?= iw_e($note['status']) ?></span></h3><p><?= nl2br(iw_e($note['message_text'])) ?></p></div><div class="meta">ID <?= iw_e($note['id']) ?><br><?= iw_e($note['created_at']) ?><br><a href="?visitor=<?= iw_e(iw_short_hash($note['visitor_hash'])) ?>">user <?= iw_e(iw_short_hash($note['visitor_hash'])) ?>…</a> · <?= iw_e($note['country']) ?> · <?= iw_e($note['referrer_host']) ?><br>layout <?= iw_e($layout['align']) ?> / <?= iw_e($layout['media']) ?> · <?= count(json_decode((string)$note['bindings_json'], true) ?: []) ?> links<br>AI <?= iw_e($note['ai_verdict'] ?? 'allow') ?> · <?= iw_e(iw_review_summary($note['ai_review_json'] ?? '{}')) ?><br>flags <?= iw_e(iw_json_list($note['ai_flags_json'] ?? '[]')) ?><?= !empty($note['ai_error']) ? '<br>AI error ' . iw_e($note['ai_error']) : '' ?><br><?= (int)$note['image_bytes'] ?> image bytes · <?= (int)$note['reaction_count'] ?> reactions · <?= (int)$note['report_count'] ?> reports</div><div class="actions">
<?php foreach (['published'=>'Publish','held'=>'Hold','rejected'=>'Reject','deleted'=>'Delete'] as $state=>$label): if ($note['status']===$state) continue; ?><form method="post"><input type="hidden" name="csrf" value="<?= iw_e(admin_csrf()) ?>"><input type="hidden" name="id" value="<?= iw_e($note['id']) ?>"><input type="hidden" name="status" value="<?= $state ?>"><button class="btn <?= $state==='published'?'btn--green':($state==='held'?'btn--amber':'btn--red') ?>" type="submit"><?= $label ?></button></form><?php endforeach ?>
</div></article><?php endforeach ?>
</section>

<?php if ($visitorFilter): ?><div class="section-title"><h2>Pseudonymous usage timeline</h2><a href="?">Clear filter</a></div><section class="panel timeline"><?php foreach($visitorEvents as $event): ?><div><?= iw_e($event['created_at']) ?> · <?= iw_e($event['event_type']) ?> · <?= iw_e($event['country']) ?> · <?= iw_e($event['referrer_host']) ?><?= $event['note_id']?' · note '.iw_e($event['note_id']):'' ?></div><?php endforeach ?></section><?php endif ?>

<div class="section-title"><h2>Recent reports</h2></div><section class="panel"><table class="reports"><thead><tr><th>When</th><th>Note</th><th>Reason</th><th>Detail</th><th>Reporter</th><th>Origin</th></tr></thead><tbody><?php foreach($recentReports as $report): ?><tr><td><?= iw_e($report['created_at']) ?></td><td><strong><?= iw_e($report['author_name']) ?></strong><br><?= iw_e(mb_strimwidth($report['message_text'],0,72,'…')) ?><br><span class="pill <?= iw_e($report['status']) ?>"><?= iw_e($report['status']) ?></span></td><td><?= iw_e($report['reason']) ?></td><td><?= iw_e($report['detail']) ?></td><td><a href="?visitor=<?= iw_e(iw_short_hash($report['reporter_hash'])) ?>"><?= iw_e(iw_short_hash($report['reporter_hash'])) ?>…</a></td><td><?= iw_e($report['country']) ?><br><?= iw_e($report['referrer_host']) ?></td></tr><?php endforeach ?></tbody></table><?php if(!$recentReports): ?><p class="muted">No reports.</p><?php endif ?></section>
</main></body></html>
