<?php
declare(strict_types=1);
define('PARKSENSE', true);
require __DIR__ . '/includes/bootstrap.php';

$user = require_role('admin', 'guard');      // both roles may open the dashboard
$isAdmin = $user['role'] === 'admin';
$error = '';

function post_text(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'This page expired. Reload it and try again.';
    } elseif (!$isAdmin) {
        http_response_code(403);
        exit('You do not have permission to change parking slots.');
    } else {
        $action = post_text('action');
        $label = post_text('label');
        $zone = post_text('zone');
        $validLabel = preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,29}$/D', $label) === 1;
        if (in_array($action, ['create', 'update'], true)
            && (!$validLabel || $zone === '' || strlen($zone) > 80)) {
            $error = 'Enter a slot label (up to 30 letters, numbers, spaces, dots, underscores or dashes) and a zone (up to 80 characters).';
        } elseif ($action === 'create') {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $st = $pdo->prepare("INSERT INTO slots (label, zone) VALUES (?, ?) RETURNING id");
                $st->execute([$label, $zone]);
                $slotId = (int)$st->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO occupancy_logs (slot_id, slot_label, status, source, changed_by)
                     VALUES (?, ?, 'vacant', 'manual', ?)"
                )->execute([$slotId, $label, $user['id']]);
                $pdo->commit();
                redirect('dashboard.php?updated=1');
            } catch (PDOException $e) {
                if (db()->inTransaction()) db()->rollBack();
                if ($e->getCode() === '23505') {
                    $error = 'That slot label is already in use.';
                } else {
                    throw $e;
                }
            }
        } elseif ($action === 'update') {
            $slotId = filter_var($_POST['slot_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $status = post_text('status');
            if (!$slotId || !in_array($status, ['vacant', 'occupied', 'out_of_service'], true)) {
                $error = 'The slot details are invalid. Please try again.';
            } else {
                try {
                    $pdo = db();
                    $pdo->beginTransaction();
                    $st = $pdo->prepare('SELECT label, status FROM slots WHERE id = ? FOR UPDATE');
                    $st->execute([$slotId]);
                    $previous = $st->fetch();
                    if (!$previous) {
                        $pdo->rollBack();
                        $error = 'That slot no longer exists. Reload the page and try again.';
                    } else {
                        $pdo->prepare(
                            'UPDATE slots SET label = ?, zone = ?, status = ?, updated_at = NOW() WHERE id = ?'
                        )->execute([$label, $zone, $status, $slotId]);
                        if ($previous['status'] !== $status) {
                            $pdo->prepare(
                                'INSERT INTO occupancy_logs (slot_id, slot_label, status, source, changed_by)
                                 VALUES (?, ?, ?, \'manual\', ?)'
                            )->execute([$slotId, $label, $status, $user['id']]);
                        }
                        $pdo->commit();
                        redirect('dashboard.php?updated=1');
                    }
                } catch (PDOException $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    if ($e->getCode() === '23505') {
                        $error = 'That slot label is already in use.';
                    } else {
                        throw $e;
                    }
                }
            }
        } elseif ($action === 'delete') {
            $slotId = filter_var($_POST['slot_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$slotId) {
                $error = 'The slot details are invalid. Please try again.';
            } else {
                $st = db()->prepare('DELETE FROM slots WHERE id = ?');
                $st->execute([$slotId]);
                if ($st->rowCount() === 0) {
                    $error = 'That slot no longer exists. Reload the page and try again.';
                } else {
                    redirect('dashboard.php?updated=1');
                }
            }
        } else {
            $error = 'That action is not supported.';
        }
    }
}

$slots = db()->query('SELECT id, label, zone, status, updated_at FROM slots ORDER BY zone, label')->fetchAll();
$counts = ['vacant' => 0, 'occupied' => 0, 'out_of_service' => 0];
foreach ($slots as $slot) {
    $counts[$slot['status']]++;
}
$recentChanges = db()->query(
    'SELECT l.slot_label, l.status, l.source, l.occurred_at, u.full_name
     FROM occupancy_logs l LEFT JOIN users u ON u.id = l.changed_by
     ORDER BY l.occurred_at DESC, l.id DESC LIMIT 10'
)->fetchAll();

$statusLabels = ['vacant' => 'Vacant', 'occupied' => 'Occupied', 'out_of_service' => 'Out of service'];

$pageTitle  = 'Dashboard';
$topbarUser = $user;
require INCLUDES . '/header.php';
?>
<main class="dashboard">
  <div class="page-heading">
    <div>
      <h1>Parking monitor</h1>
      <p class="sub">Welcome, <?= e($user['full_name']) ?>. Current status of configured parking slots.</p>
    </div>
    <span class="mode-badge">Manual demo mode · sensors not connected</span>
  </div>
  <?php if (isset($_GET['updated'])): ?><p class="note" role="status">Changes saved.</p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="err form-error" role="alert"><?= e($error) ?></p><?php endif; ?>

  <section class="count-grid" aria-label="Parking occupancy summary">
    <article class="count-card"><span>Total slots</span><strong><?= count($slots) ?></strong></article>
    <article class="count-card status-vacant"><span>Vacant</span><strong><?= $counts['vacant'] ?></strong></article>
    <article class="count-card status-occupied"><span>Occupied</span><strong><?= $counts['occupied'] ?></strong></article>
    <article class="count-card status-out"><span>Out of service</span><strong><?= $counts['out_of_service'] ?></strong></article>
  </section>

  <?php if ($isAdmin): ?>
    <section class="slot-setup" id="slots">
      <h2>Configure parking slots</h2>
      <p class="sub">Add the slot labels and zones now. Status changes here are manual until sensors are connected.</p>
      <form method="post" action="dashboard.php#slots" class="slot-create">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>Slot label
          <input name="label" maxlength="30" placeholder="e.g. A-01" required>
        </label>
        <label>Zone
          <input name="zone" maxlength="80" placeholder="e.g. North lot" required>
        </label>
        <button type="submit" class="btn">Add slot</button>
      </form>
    </section>
  <?php endif; ?>

  <section class="slot-section" id="monitor">
    <h2>Slot status</h2>
    <?php if (!$slots): ?>
      <p class="empty-state">No parking slots have been configured yet.<?php if ($isAdmin): ?> Add slots above to begin setting up the lot.<?php endif; ?></p>
    <?php else: ?>
      <div class="slot-grid">
        <?php foreach ($slots as $slot): ?>
          <article class="slot-card slot-<?= e($slot['status']) ?>">
            <?php if ($isAdmin): ?>
              <form method="post" action="dashboard.php#monitor" class="slot-edit">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                <label>Label
                  <input name="label" maxlength="30" value="<?= e($slot['label']) ?>" required>
                </label>
                <label>Zone
                  <input name="zone" maxlength="80" value="<?= e($slot['zone']) ?>" required>
                </label>
                <label>Status
                  <select name="status">
                    <?php foreach ($statusLabels as $value => $labelText): ?>
                      <option value="<?= e($value) ?>"<?= $slot['status'] === $value ? ' selected' : '' ?>><?= e($labelText) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <button type="submit" class="btn-small">Save slot</button>
              </form>
              <form method="post" action="dashboard.php#monitor" class="slot-delete">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                <button type="submit" class="btn-danger">Remove slot</button>
              </form>
            <?php else: ?>
              <div class="slot-view">
                <h3><?= e($slot['label']) ?></h3>
                <p><?= e($slot['zone']) ?></p>
                <strong class="status-label"><?= e($statusLabels[$slot['status']]) ?></strong>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="history-section">
    <h2>Recent status changes</h2>
    <p class="sub">Showing the latest 10 entries.</p>
    <?php if (!$recentChanges): ?>
      <p class="empty-state">No status changes recorded yet.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Slot</th><th>Status</th><th>Source</th><th>Changed by</th><th>Time</th></tr></thead>
          <tbody>
          <?php foreach ($recentChanges as $change): ?>
            <tr>
              <td><?= e($change['slot_label']) ?></td>
              <td><?= e($statusLabels[$change['status']] ?? $change['status']) ?></td>
              <td><?= $change['source'] === 'sensor' ? 'Sensor' : 'Manual' ?></td>
              <td><?= e($change['full_name'] ?? 'System') ?></td>
              <td><?= e((new DateTimeImmutable($change['occurred_at']))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require INCLUDES . '/footer.php'; ?>
