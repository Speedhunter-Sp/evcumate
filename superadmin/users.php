<?php
// superadmin/users.php
// Full user management: search/lookup → promote/demote/toggle superuser/delete/create admin
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
requireAuth();

$pdo = db();

// ── Check if migration columns exist ─────────
$hasSuperuser = false;
try { $pdo->query('SELECT is_superuser FROM users LIMIT 1'); $hasSuperuser = true; }
catch (Throwable $e) {}

// ═══════════════════════════════════════════════
//  POST ACTIONS
// ═══════════════════════════════════════════════
$actionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act    = $_POST['action']  ?? '';
    $target = (int) ($_POST['user_id'] ?? 0);

    // ── Promote user → admin ──────────────────
    if ($act === 'promote' && $target > 0) {
        $pdo->prepare('UPDATE users SET role="admin" WHERE user_id=:id AND deleted_at IS NULL')
            ->execute([':id' => $target]);
        flash('✅ Account promoted to Admin successfully.');
        header('Location: users.php?lookup=' . $target); exit;
    }

    // ── Demote admin → user ───────────────────
    if ($act === 'demote' && $target > 0) {
        if ($hasSuperuser) {
            $pdo->prepare('UPDATE users SET role="user", is_superuser=0 WHERE user_id=:id AND deleted_at IS NULL')
                ->execute([':id' => $target]);
        } else {
            $pdo->prepare('UPDATE users SET role="user" WHERE user_id=:id AND deleted_at IS NULL')
                ->execute([':id' => $target]);
        }
        flash('⬇️ Account demoted to User.', 'error');
        header('Location: users.php?lookup=' . $target); exit;
    }

    // ── Grant superuser ───────────────────────
    if ($act === 'grant_super' && $target > 0 && $hasSuperuser) {
        $pdo->prepare('UPDATE users SET is_superuser=1 WHERE user_id=:id AND deleted_at IS NULL')
            ->execute([':id' => $target]);
        flash('⭐ Superuser privileges granted.');
        header('Location: users.php?lookup=' . $target); exit;
    }

    // ── Revoke superuser ──────────────────────
    if ($act === 'revoke_super' && $target > 0 && $hasSuperuser) {
        $pdo->prepare('UPDATE users SET is_superuser=0 WHERE user_id=:id AND deleted_at IS NULL')
            ->execute([':id' => $target]);
        flash('✕ Superuser privileges revoked.', 'error');
        header('Location: users.php?lookup=' . $target); exit;
    }

    // ── Soft delete ───────────────────────────
    if ($act === 'delete' && $target > 0) {
        $pdo->prepare('UPDATE users SET deleted_at=NOW() WHERE user_id=:id')
            ->execute([':id' => $target]);
        flash('🗑 Account deleted.', 'error');
        header('Location: users.php'); exit;
    }

    // ── Create new admin account ──────────────
    if ($act === 'create_admin') {
        $firstName     = trim($_POST['first_name'] ?? '');
        $lastName      = trim($_POST['last_name']  ?? '');
        $email         = trim($_POST['email']      ?? '');
        $password      = $_POST['password']        ?? '';
        $makeSuperuser = !empty($_POST['make_superuser']) && $hasSuperuser;

        if (!$firstName || !$lastName || !$email || strlen($password) < 6) {
            $actionError = 'All fields are required and password must be at least 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $actionError = 'Invalid email address.';
        } else {
            $chk = $pdo->prepare('SELECT user_id FROM users WHERE email=:em LIMIT 1');
            $chk->execute([':em' => $email]);
            if ($chk->fetch()) {
                $actionError = 'That email address is already registered.';
            } else {
                $hash     = password_hash($password, PASSWORD_BCRYPT);
                $superVal = $makeSuperuser ? 1 : 0;
                if ($hasSuperuser) {
                    $ins = $pdo->prepare(
                        'INSERT INTO users (first_name,last_name,email,password_hash,
                                           contact_number,address,role,is_superuser)
                         VALUES (:fn,:ln,:em,:pw,"","","admin",:su)'
                    );
                    $ins->execute([':fn'=>$firstName,':ln'=>$lastName,
                                   ':em'=>$email,':pw'=>$hash,':su'=>$superVal]);
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO users (first_name,last_name,email,password_hash,
                                           contact_number,address,role)
                         VALUES (:fn,:ln,:em,:pw,"","","admin")'
                    );
                    $ins->execute([':fn'=>$firstName,':ln'=>$lastName,
                                   ':em'=>$email,':pw'=>$hash]);
                }
                $newId  = $pdo->lastInsertId();
                $qrData = 'evacumate://user/' . $newId;
                $pdo->prepare('INSERT INTO qr_codes (user_id, qr_code_data) VALUES (:uid,:qr)')
                    ->execute([':uid' => $newId, ':qr' => $qrData]);
                $label = $makeSuperuser ? 'Admin + Superuser' : 'Admin';
                flash("✅ $label account created for $firstName $lastName.");
                header('Location: users.php?lookup=' . $newId); exit;
            }
        }
    }
}

// ═══════════════════════════════════════════════
//  LOOKUP — single account detail view
// ═══════════════════════════════════════════════
$lookupUser = null;
$lookupId   = (int) ($_GET['lookup'] ?? 0);
if ($lookupId > 0) {
    $ls = $pdo->prepare(
        'SELECT u.user_id, u.first_name, u.last_name, u.email,
                u.role, u.contact_number, u.address,
                u.date_created, u.deleted_at,
                COALESCE(q.qr_code_data, CONCAT("evacumate://user/", u.user_id)) AS qr_code
         FROM   users u
         LEFT   JOIN qr_codes q ON q.user_id=u.user_id AND q.is_active=1
         WHERE  u.user_id=:id LIMIT 1'
    );
    $ls->execute([':id' => $lookupId]);
    $lookupUser = $ls->fetch() ?: null;
    if ($lookupUser && $hasSuperuser) {
        $ss = $pdo->prepare('SELECT is_superuser FROM users WHERE user_id=:id LIMIT 1');
        $ss->execute([':id' => $lookupId]);
        $lookupUser['is_superuser'] = (bool) ($ss->fetchColumn() ?? false);
    } elseif ($lookupUser) {
        $lookupUser['is_superuser'] = false;
    }
}

// ═══════════════════════════════════════════════
//  USER LIST
// ═══════════════════════════════════════════════
$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role']   ?? '');
$showNewAdmin = isset($_GET['action']) && $_GET['action'] === 'new_admin';

$where  = ['u.deleted_at IS NULL'];
$params = [];
if ($search !== '') {
    $where[]      = '(u.first_name LIKE :s OR u.last_name LIKE :s OR u.email LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if ($roleFilter !== '') {
    $where[]      = 'u.role = :r';
    $params[':r'] = $roleFilter;
}
$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.role, u.date_created
     FROM   users u
     WHERE  $whereStr
     ORDER  BY u.role DESC, u.first_name, u.last_name
     LIMIT  100"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$superIds = [];
if ($hasSuperuser) {
    $superIds = array_map('intval',
        $pdo->query('SELECT user_id FROM users WHERE is_superuser=1 AND deleted_at IS NULL')
            ->fetchAll(PDO::FETCH_COLUMN)
    );
}

$csrf = csrfToken();
render_header('Users — EvacuMate Superadmin');
?>

<!-- ══ TOP BAR ══ -->
<div style="display:flex;align-items:center;justify-content:space-between;
            margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h2 style="font-size:1.3rem;font-weight:700;">👥 User &amp; Role Management</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="users.php?action=new_admin" class="btn btn-success">➕ New Admin Account</a>
    <a href="users.php" class="btn btn-gray">↩ Reset View</a>
  </div>
</div>

<?php if (!$hasSuperuser): ?>
<div style="background:#fff8e1;color:#e65100;border:1px solid #ffcc80;
    padding:12px 20px;border-radius:8px;margin-bottom:18px;font-size:.88rem;">
  ⚠️ <strong>migration.sql has not been run yet</strong> — the <code>is_superuser</code>
  column doesn't exist. Superuser grant/revoke will be available after you run the migration in phpMyAdmin.
</div>
<?php endif; ?>

<!-- ══ ACCOUNT LOOKUP ══ -->
<div class="card">
  <h2>🔍 Account Lookup</h2>
  <p style="font-size:.88rem;color:#777;margin-bottom:16px;">
    Search by name or email, or jump directly to a User ID.
    Click any row in the table below to load their detail card here.
  </p>

  <div style="display:flex;gap:16px;flex-wrap:wrap;">
    <!-- Search form -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;flex:2;min-width:260px;">
      <div style="flex:2;min-width:180px;">
        <label>Search Name or Email</label>
        <input type="text" name="search" placeholder="e.g. Julian or admin@email.com"
               value="<?= htmlspecialchars($search) ?>" style="margin-bottom:0">
      </div>
      <div style="min-width:130px;">
        <label>Role Filter</label>
        <select name="role" style="margin-bottom:0;padding:9px 12px;border:1px solid #ddd;
                border-radius:8px;font-size:.9rem;width:100%;">
          <option value="">All roles</option>
          <option value="user"  <?= $roleFilter==='user' ?'selected':'' ?>>User only</option>
          <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin only</option>
        </select>
      </div>
      <div style="display:flex;gap:6px;">
        <button type="submit" class="btn btn-primary" style="padding:9px 18px;">Search</button>
        <?php if ($search || $roleFilter): ?>
          <a href="users.php" class="btn btn-gray" style="padding:9px 14px;">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Direct ID lookup -->
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;min-width:200px;">
      <div style="flex:1;">
        <label>Jump to User ID</label>
        <input type="number" name="lookup" placeholder="e.g. 2"
               value="<?= $lookupId ?: '' ?>" min="1" style="margin-bottom:0">
      </div>
      <button type="submit" class="btn btn-primary" style="padding:9px 18px;">Go</button>
    </form>
  </div>
</div>

<!-- ══ SINGLE USER DETAIL CARD ══ -->
<?php if ($lookupUser):
  $u       = $lookupUser;
  $uid     = (int) $u['user_id'];
  $isAdmin = $u['role'] === 'admin';
  $isSuper = (bool) ($u['is_superuser'] ?? false);
  $initial = strtoupper(substr($u['first_name'], 0, 1) ?: '?');
  $deleted = !empty($u['deleted_at']);
?>
<div class="card" style="border:2px solid #ef5350;">
  <h2>👤 Account Detail</h2>

  <!-- Summary row -->
  <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-bottom:22px;">
    <div style="width:60px;height:60px;border-radius:50%;background:#ef5350;color:#fff;
                font-size:1.5rem;font-weight:700;display:flex;align-items:center;
                justify-content:center;flex-shrink:0;">
      <?= $initial ?>
    </div>
    <div style="flex:1;min-width:200px;">
      <div style="font-size:1.1rem;font-weight:700;">
        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
        <?php if ($deleted): ?>
          <span class="badge" style="background:#ffebee;color:#c62828;margin-left:6px;">DELETED</span>
        <?php endif; ?>
      </div>
      <div style="color:#666;margin:3px 0;"><?= htmlspecialchars($u['email']) ?></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
        <span class="badge <?= $isAdmin ? 'badge-admin' : 'badge-user' ?>">
          <?= $isAdmin ? '🔑 Admin' : '👤 User' ?>
        </span>
        <?php if ($isSuper): ?>
          <span class="badge badge-super">⭐ Superuser</span>
        <?php endif; ?>
      </div>
      <div style="color:#bbb;font-size:.78rem;margin-top:6px;">
        ID: <?= $uid ?> &nbsp;·&nbsp;
        Joined: <?= date('M j, Y', strtotime($u['date_created'])) ?>
      </div>
    </div>
  </div>

  <?php if (!$deleted): ?>

  <!-- ── Promotion ladder ── -->
  <div style="background:#fafafa;border-radius:10px;padding:16px;margin-bottom:18px;">
    <div style="font-size:.85rem;font-weight:700;color:#666;margin-bottom:12px;">
      ⚙️ Role &amp; Privilege Ladder
    </div>
    <div style="display:flex;gap:0;flex-wrap:wrap;">

      <!-- Step 1 -->
      <div style="flex:1;min-width:160px;padding:12px 16px;
                  border:2px solid <?= !$isAdmin ? '#2e7d32' : '#c8e6c9' ?>;
                  background:<?= !$isAdmin ? '#e8f5e9' : '#f9fbe7' ?>;
                  border-radius:10px 0 0 10px;">
        <div style="font-size:.72rem;font-weight:700;color:#999;letter-spacing:.5px;">STEP 1</div>
        <div style="font-weight:700;color:#2e7d32;margin-top:2px;">
          <?= $isAdmin ? '✅ Admin' : '👤 → 🔑 Admin' ?>
        </div>
        <div style="font-size:.76rem;color:#aaa;margin-top:3px;">App dashboard access</div>
      </div>

      <div style="padding:12px 6px;background:#f5f5f5;color:#ccc;
                  display:flex;align-items:center;font-size:1.3rem;">›</div>

      <!-- Step 2 -->
      <div style="flex:1;min-width:160px;padding:12px 16px;
                  border:2px solid <?= ($isAdmin && $hasSuperuser) ? ($isSuper ? '#ab47bc' : '#ce93d8') : '#e0e0e0' ?>;
                  background:<?= $isSuper ? '#f3e5f5' : ($isAdmin && $hasSuperuser ? '#fce4ec' : '#f5f5f5') ?>;
                  border-radius:0 10px 10px 0;">
        <div style="font-size:.72rem;font-weight:700;color:#999;letter-spacing:.5px;">STEP 2</div>
        <div style="font-weight:700;color:<?= $hasSuperuser ? '#6a1b9a' : '#bbb' ?>;margin-top:2px;">
          <?php if (!$hasSuperuser): ?>
            ⚠️ Run migration first
          <?php elseif ($isSuper): ?>
            ✅ Superuser
          <?php else: ?>
            🔑 → ⭐ Superuser
          <?php endif; ?>
        </div>
        <div style="font-size:.76rem;color:#aaa;margin-top:3px;">Enables delete in app</div>
      </div>
    </div>
  </div>

  <!-- ── Action buttons ── -->
  <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="hidden" name="csrf"    value="<?= $csrf ?>">
    <input type="hidden" name="user_id" value="<?= $uid ?>">

    <?php if (!$isAdmin): ?>
      <button name="action" value="promote" class="btn btn-success"
              onclick="return confirm('Promote <?= htmlspecialchars(addslashes($u['first_name'])) ?> to Admin?\n\nGives full admin dashboard access in the app.')">
        ⬆ Promote to Admin
      </button>
    <?php else: ?>
      <?php if ($hasSuperuser && !$isSuper): ?>
        <button name="action" value="grant_super" class="btn btn-primary"
                onclick="return confirm('Grant Superuser to <?= htmlspecialchars(addslashes($u['first_name'])) ?>?\n\nAllows deleting evacuee accounts in the app.')">
          ⭐ Grant Superuser
        </button>
      <?php elseif ($hasSuperuser && $isSuper): ?>
        <button name="action" value="revoke_super" class="btn btn-warning"
                onclick="return confirm('Revoke Superuser from <?= htmlspecialchars(addslashes($u['first_name'])) ?>?\n\nThey will lose the ability to delete accounts.')">
          ✕ Revoke Superuser
        </button>
      <?php endif; ?>
      <button name="action" value="demote" class="btn btn-warning"
              onclick="return confirm('Demote <?= htmlspecialchars(addslashes($u['first_name'])) ?> back to regular User?\n\nRemoves admin access<?= $isSuper?' and superuser privileges':'' ?>.')">
        ⬇ Demote to User
      </button>
    <?php endif; ?>

    <button name="action" value="delete" class="btn btn-danger"
            onclick="return confirm('Delete the account for <?= htmlspecialchars(addslashes($u['first_name'] . ' ' . $u['last_name'])) ?>?\n\nThis is a soft delete.')">
      🗑 Delete Account
    </button>
  </form>

  <?php else: ?>
    <p style="color:#c62828;font-style:italic;padding:8px 0;">
      This account has been soft-deleted (deleted_at is set).
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ CREATE NEW ADMIN FORM ══ -->
<div id="newAdminSection" style="display:<?= ($showNewAdmin || $actionError) ? 'block' : 'none' ?>;">
  <div class="card">
    <h2>➕ Create New Admin Account</h2>
    <?php if ($actionError): ?>
      <div style="background:#ffebee;color:#c62828;border:1px solid #ef9a9a;
          padding:12px 18px;border-radius:8px;margin-bottom:16px;">
        <?= htmlspecialchars($actionError) ?>
      </div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create_admin">
      <div class="form-row">
        <div>
          <label>First Name</label>
          <input type="text" name="first_name" required
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div>
          <label>Last Name</label>
          <input type="text" name="last_name" required
                 value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
      </div>
      <label>Email Address</label>
      <input type="email" name="email" required
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <label>Password <span style="font-weight:400;color:#999">(min 6 characters)</span></label>
      <input type="password" name="password" required>
      <?php if ($hasSuperuser): ?>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;
                    font-weight:500;margin-bottom:18px;">
        <input type="checkbox" name="make_superuser" value="1" style="width:auto;margin:0;">
        Also grant Superuser privileges (allows deleting evacuees in the app)
      </label>
      <?php endif; ?>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-success">Create Admin Account</button>
        <a href="users.php" class="btn btn-gray">Cancel</a>
      </div>
    </form>
  </div>
</div>

<!-- ══ ALL USERS TABLE ══ -->
<div class="card" style="padding:0;overflow:hidden;">
  <div style="padding:16px 20px 10px;font-weight:700;font-size:.95rem;
              border-bottom:1px solid #f0f0f0;display:flex;
              justify-content:space-between;align-items:center;">
    <span>
      All Accounts
      <?php if ($search || $roleFilter): ?>
        <span style="font-weight:400;color:#888;font-size:.85rem">
          — <?= count($users) ?> result(s)
          <?= $search ? 'for "' . htmlspecialchars($search) . '"' : '' ?>
          <?= $roleFilter ? '(role: ' . htmlspecialchars($roleFilter) . ')' : '' ?>
        </span>
      <?php endif; ?>
    </span>
    <span style="font-size:.78rem;color:#bbb;">Click any row to view details</span>
  </div>
  <table>
    <tr>
      <th>User</th><th>Email</th><th>Role</th>
      <?php if ($hasSuperuser): ?><th>Superuser</th><?php endif; ?>
      <th>Joined</th><th style="text-align:right">Quick Action</th>
    </tr>
    <?php if (empty($users)): ?>
      <tr><td colspan="<?= $hasSuperuser ? 6 : 5 ?>"
              style="text-align:center;padding:28px;color:#aaa;">
        No accounts found.<?= $search ? ' Try a different search term.' : '' ?>
      </td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u):
      $uid     = (int) $u['user_id'];
      $isAdmin = $u['role'] === 'admin';
      $isSuper = in_array($uid, $superIds, true);
      $initial = strtoupper(substr($u['first_name'], 0, 1) ?: '?');
    ?>
    <tr style="cursor:pointer"
        onclick="window.location='users.php?lookup=<?= $uid ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>'">
      <td>
        <span class="avatar"><?= $initial ?></span>
        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
      </td>
      <td style="color:#666"><?= htmlspecialchars($u['email']) ?></td>
      <td>
        <span class="badge <?= $isAdmin ? 'badge-admin' : 'badge-user' ?>">
          <?= $isAdmin ? '🔑 admin' : '👤 user' ?>
        </span>
      </td>
      <?php if ($hasSuperuser): ?>
      <td>
        <?= $isSuper
          ? '<span class="badge badge-super">⭐ yes</span>'
          : '<span style="color:#ccc;font-size:.8rem">—</span>' ?>
      </td>
      <?php endif; ?>
      <td style="color:#aaa;font-size:.82rem;white-space:nowrap">
        <?= date('M j, Y', strtotime($u['date_created'])) ?>
      </td>
      <td style="text-align:right" onclick="event.stopPropagation()">
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf"    value="<?= $csrf ?>">
          <input type="hidden" name="user_id" value="<?= $uid ?>">
          <?php if (!$isAdmin): ?>
            <button name="action" value="promote" class="btn btn-success btn-sm"
                    onclick="return confirm('Promote <?= htmlspecialchars(addslashes($u['first_name'])) ?> to Admin?')">
              ⬆ Promote
            </button>
          <?php elseif ($hasSuperuser && !$isSuper): ?>
            <button name="action" value="grant_super" class="btn btn-primary btn-sm"
                    onclick="return confirm('Grant Superuser to <?= htmlspecialchars(addslashes($u['first_name'])) ?>?')">
              ⭐ Grant Super
            </button>
          <?php else: ?>
            <a href="users.php?lookup=<?= $uid ?>" class="btn btn-gray btn-sm">View</a>
          <?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php render_footer(); ?>