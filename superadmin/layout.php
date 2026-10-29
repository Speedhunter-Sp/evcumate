<?php
// superadmin/layout.php
// Call render_header() at the top and render_footer() at the bottom of each page.

function render_header(string $title = 'EvacuMate Superadmin'): void {
    $flash = getFlash();
    $flashHtml = '';
    if ($flash) {
        $color = $flash['type'] === 'success' ? '#2e7d32' : '#c62828';
        $bg    = $flash['type'] === 'success' ? '#e8f5e9' : '#ffebee';
        $flashHtml = "<div style='background:$bg;color:$color;border:1px solid $color;
            padding:12px 20px;border-radius:8px;margin-bottom:20px;font-weight:500;'>
            " . htmlspecialchars($flash['msg']) . "</div>";
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #222; min-height: 100vh; }

    /* ── Nav ── */
    nav {
      background: #ef5350; color: #fff;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 28px; height: 56px;
      box-shadow: 0 2px 6px rgba(0,0,0,.18);
    }
    nav .brand { font-size: 1.15rem; font-weight: 700; letter-spacing: .5px; }
    nav .brand span { opacity: .75; font-weight: 400; }
    nav a { color: #fff; text-decoration: none; font-size: .9rem;
            padding: 6px 14px; border-radius: 6px; transition: background .15s; }
    nav a:hover { background: rgba(255,255,255,.2); }

    /* ── Wrapper ── */
    .wrap { max-width: 1080px; margin: 32px auto; padding: 0 20px; }

    /* ── Cards ── */
    .card { background: #fff; border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08); padding: 28px; margin-bottom: 24px; }
    .card h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 18px;
               color: #ef5350; border-bottom: 2px solid #fce4e4; padding-bottom: 10px; }

    /* ── Table ── */
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { background: #fce4e4; color: #b71c1c; font-weight: 600;
         padding: 10px 14px; text-align: left; }
    td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }

    /* ── Avatar ── */
    .avatar { width: 36px; height: 36px; border-radius: 50%;
              background: #ef5350; color: #fff; font-weight: 700;
              display: inline-flex; align-items: center; justify-content: center;
              font-size: .85rem; margin-right: 8px; vertical-align: middle; }
    .avatar img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px;
             font-size: .78rem; font-weight: 600; }
    .badge-admin    { background: #fce4e4; color: #c62828; }
    .badge-user     { background: #e3f2fd; color: #1565c0; }
    .badge-super    { background: #f3e5f5; color: #6a1b9a; }

    /* ── Buttons ── */
    .btn { display: inline-block; padding: 7px 18px; border-radius: 8px;
           border: none; cursor: pointer; font-size: .85rem; font-weight: 600;
           text-decoration: none; transition: opacity .15s; }
    .btn:hover { opacity: .85; }
    .btn-primary  { background: #ef5350; color: #fff; }
    .btn-success  { background: #2e7d32; color: #fff; }
    .btn-warning  { background: #f57f17; color: #fff; }
    .btn-danger   { background: #c62828; color: #fff; }
    .btn-gray     { background: #9e9e9e; color: #fff; }
    .btn-sm       { padding: 4px 12px; font-size: .78rem; }

    /* ── Form ── */
    label { display: block; font-size: .85rem; font-weight: 600;
            margin-bottom: 4px; color: #555; }
    input[type=text], input[type=email], input[type=password], select {
      width: 100%; padding: 9px 14px; border: 1px solid #ddd;
      border-radius: 8px; font-size: .9rem; margin-bottom: 14px;
      transition: border .15s; }
    input:focus, select:focus { outline: none; border-color: #ef5350; }
    .form-row { display: flex; gap: 16px; }
    .form-row > * { flex: 1; }

    /* ── Stats bar ── */
    .stats { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
    .stat-box { flex: 1; min-width: 160px; background: #fff; border-radius: 12px;
                padding: 18px 22px; box-shadow: 0 2px 8px rgba(0,0,0,.08);
                border-left: 4px solid #ef5350; }
    .stat-box .num { font-size: 2rem; font-weight: 700; color: #ef5350; }
    .stat-box .lbl { font-size: .8rem; color: #888; margin-top: 2px; }

    /* ── Search ── */
    .search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
    .search-bar input { flex: 1; }
    .search-bar .btn { align-self: flex-start; margin-top: 0; padding: 9px 20px; }
  </style>
</head>
<body>
<nav>
  <div class="brand">EvacuMate &nbsp;<span>Superadmin Panel</span></div>
  <div>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="logs.php">Logs</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>
<div class="wrap">
{$flashHtml}
HTML;
}

function render_footer(): void {
    echo <<<HTML
</div>
<footer style="text-align:center;padding:24px;color:#bbb;font-size:.8rem;">
  EvacuMate Superadmin &mdash; restricted access
</footer>
</body></html>
HTML;
}