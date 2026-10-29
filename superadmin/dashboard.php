<?php
// superadmin/dashboard.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$pdo = db();

$totalUsers  = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role="user"  AND deleted_at IS NULL')->fetchColumn();
$totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role="admin" AND deleted_at IS NULL')->fetchColumn();
$totalScans  = (int) $pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();
$markedSafe  = (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM scans WHERE scan_type="Marked Safe"')->fetchColumn();

// Try to get superuser count (column may not exist yet)
$totalSuper = 0;
try {
    $totalSuper = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_superuser=1')->fetchColumn();
} catch (Throwable $e) {}

// Recent admin actions
$recentLogs = [];
try {
    $recentLogs = $pdo->query(
        'SELECT l.action_type, l.action_description, l.created_at,
                CONCAT(u.first_name," ",u.last_name) AS admin_name
         FROM   admin_actions_log l
         JOIN   users u ON u.user_id = l.admin_id
         ORDER  BY l.created_at DESC
         LIMIT  10'
    )->fetchAll();
} catch (Throwable $e) {}

render_header('Dashboard — EvacuMate Superadmin');
?>

<h2 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;">📊 Dashboard Overview</h2>

<div class="stats">
  <div class="stat-box">
    <div class="num"><?= $totalUsers ?></div>
    <div class="lbl">Total Evacuees</div>
  </div>
  <div class="stat-box" style="border-color:#1565c0">
    <div class="num" style="color:#1565c0"><?= $totalAdmins ?></div>
    <div class="lbl">Admin Accounts</div>
  </div>
  <div class="stat-box" style="border-color:#6a1b9a">
    <div class="num" style="color:#6a1b9a"><?= $totalSuper ?></div>
    <div class="lbl">Superusers</div>
  </div>
  <div class="stat-box" style="border-color:#2e7d32">
    <div class="num" style="color:#2e7d32"><?= $markedSafe ?></div>
    <div class="lbl">Marked Safe</div>
  </div>
  <div class="stat-box" style="border-color:#f57f17">
    <div class="num" style="color:#f57f17"><?= $totalScans ?></div>
    <div class="lbl">Total Scans</div>
  </div>
</div>

<div class="card">
  <h2>⚡ Quick Actions</h2>
  <a href="users.php" class="btn btn-primary" style="margin-right:10px;margin-bottom:8px;">
    👥 Manage Users &amp; Roles
  </a>
  <a href="users.php?action=new_admin" class="btn btn-success" style="margin-right:10px;margin-bottom:8px;">
    ➕ Create Admin Account
  </a>
  <a href="logs.php" class="btn btn-warning" style="margin-bottom:8px;">
    📋 View All Logs
  </a>
</div>

<?php if (!empty($recentLogs)): ?>
<div class="card">
  <h2>🕐 Recent Admin Activity</h2>
  <table>
    <tr>
      <th>Admin</th><th>Action</th><th>Description</th><th>Time</th>
    </tr>
    <?php foreach ($recentLogs as $log): ?>
    <tr>
      <td><?= htmlspecialchars($log['admin_name']) ?></td>
      <td><span class="badge badge-admin"><?= htmlspecialchars($log['action_type']) ?></span></td>
      <td><?= htmlspecialchars($log['action_description']) ?></td>
      <td style="color:#888;font-size:.82rem;white-space:nowrap">
        <?= date('M j, Y  g:i A', strtotime($log['created_at'])) ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php render_footer(); ?>