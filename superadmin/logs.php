<?php
// superadmin/logs.php  — View all admin action logs
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$pdo = db();

$search  = trim($_GET['search']  ?? '');
$type    = trim($_GET['type']    ?? '');
$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]        = '(l.action_description LIKE :s OR u.email LIKE :s)';
    $params[':s']   = '%' . $search . '%';
}
if ($type !== '') {
    $where[]        = 'l.action_type = :t';
    $params[':t']   = $type;
}

$whereStr = implode(' AND ', $where);

$total = (int) $pdo->prepare(
    "SELECT COUNT(*) FROM admin_actions_log l
     JOIN users u ON u.user_id = l.admin_id
     WHERE $whereStr"
)->execute($params) ? $pdo->prepare(
    "SELECT COUNT(*) FROM admin_actions_log l
     JOIN users u ON u.user_id = l.admin_id
     WHERE $whereStr"
) : null;

// Simple count
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM admin_actions_log l
     JOIN users u ON u.user_id = l.admin_id
     WHERE $whereStr"
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = (int) ceil($total / $perPage);

$logStmt = $pdo->prepare(
    "SELECT l.log_id, l.action_type, l.action_description,
            l.created_at, l.ip_address,
            CONCAT(u.first_name,' ',u.last_name) AS admin_name,
            u.email AS admin_email,
            tu.first_name AS target_first, tu.last_name AS target_last
     FROM   admin_actions_log l
     JOIN   users u  ON u.user_id  = l.admin_id
     LEFT   JOIN users tu ON tu.user_id = l.target_user_id
     WHERE  $whereStr
     ORDER  BY l.created_at DESC
     LIMIT  $perPage OFFSET $offset"
);
$logStmt->execute($params);
$logs = $logStmt->fetchAll();

// Action types for filter dropdown
$types = $pdo->query('SELECT DISTINCT action_type FROM admin_actions_log ORDER BY action_type')
             ->fetchAll(PDO::FETCH_COLUMN);

render_header('Logs — EvacuMate Superadmin');
?>

<h2 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;">
  📋 Admin Action Logs
  <span style="font-size:.9rem;font-weight:400;color:#888">(<?= $total ?> total)</span>
</h2>

<!-- Filter bar -->
<form method="GET" class="search-bar" style="flex-wrap:wrap;gap:8px;">
  <input type="text" name="search" placeholder="Search description or email…"
         value="<?= htmlspecialchars($search) ?>" style="flex:2;min-width:200px;">
  <select name="type" style="padding:9px 14px;border:1px solid #ddd;border-radius:8px;
          font-size:.9rem;margin-bottom:0;width:auto;">
    <option value="">All types</option>
    <?php foreach ($types as $t): ?>
      <option value="<?= htmlspecialchars($t) ?>" <?= $type===$t?'selected':'' ?>>
        <?= htmlspecialchars($t) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
  <?php if ($search || $type): ?>
    <a href="logs.php" class="btn btn-gray">Clear</a>
  <?php endif; ?>
</form>

<div class="card" style="padding:0;overflow:hidden;">
  <?php if (empty($logs)): ?>
    <p style="padding:24px;color:#888;">No logs found.</p>
  <?php else: ?>
  <table>
    <tr>
      <th>#</th>
      <th>Admin</th>
      <th>Action</th>
      <th>Description</th>
      <th>Target</th>
      <th>Time</th>
    </tr>
    <?php foreach ($logs as $log): ?>
    <tr>
      <td style="color:#bbb;font-size:.8rem"><?= $log['log_id'] ?></td>
      <td>
        <?= htmlspecialchars($log['admin_name']) ?>
        <div style="color:#aaa;font-size:.75rem"><?= htmlspecialchars($log['admin_email']) ?></div>
      </td>
      <td>
        <span class="badge badge-admin"><?= htmlspecialchars($log['action_type']) ?></span>
      </td>
      <td style="max-width:320px;font-size:.85rem">
        <?= htmlspecialchars($log['action_description']) ?>
      </td>
      <td style="color:#555;font-size:.85rem">
        <?php if ($log['target_first']): ?>
          <?= htmlspecialchars($log['target_first'] . ' ' . $log['target_last']) ?>
        <?php else: ?>
          <span style="color:#ccc">—</span>
        <?php endif; ?>
      </td>
      <td style="color:#888;font-size:.8rem;white-space:nowrap">
        <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
        <span style="color:#bbb"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:8px;">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>"
       class="btn <?= $i===$page?'btn-primary':'btn-gray' ?> btn-sm">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php render_footer(); ?>