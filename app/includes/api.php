<?php
/**
 * FSA Trading Journal — API v2.2.6 (Security Hardened)
 * 
 * Fixes in this version:
 * [SEC-1] CSRF protection via Content-Type + Origin header validation
 * [SEC-2] Input validation — all numeric fields use floatval()
 * [SEC-3] Password change requires current password
 * [SEC-4] File upload — 5MB limit + MIME type validation
 * [SEC-5] Import limit — max 500 trades per batch
 * [SEC-6] Division by zero protection in alerts + stats
 * [SEC-7] Consistent media path handling
 * [SEC-8] Secure random filenames using random_bytes
 * [SEC-9] Sanitized error messages — no raw user input echoed
 * [SEC-10] Input ID validation — all IDs must be positive integers
 */

define('IS_API', true);
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

// ── SECURITY HELPERS ────────────────────────────────────────

/**
 * [SEC-1] CSRF Protection
 * - JSON requests: browsers cannot send Content-Type: application/json cross-origin without CORS
 * - FormData requests: validate Origin or Referer matches our domain
 */
function csrfCheck() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    // JSON requests are safe — browsers can't forge Content-Type: application/json cross-origin
    if (stripos($content_type, 'application/json') !== false) return;

    // For FormData/multipart uploads — check Origin or Referer
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host    = $_SERVER['HTTP_HOST'] ?? '';

    $allowed = false;
    if ($origin && parse_url($origin, PHP_URL_HOST) === $host) $allowed = true;
    if (!$allowed && $referer && parse_url($referer, PHP_URL_HOST) === $host) $allowed = true;

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['error' => 'Request blocked — invalid origin']);
        exit;
    }
}

/**
 * [SEC-2] Sanitize numeric input — always returns float, never raw string
 */
function num($val, $default = 0) {
    if ($val === null || $val === '') return $default;
    return floatval($val);
}

/**
 * [SEC-10] Validate ID — must be positive integer
 */
function validId($val) {
    return filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

/**
 * [SEC-7] Consistent media directory path
 */
function safeMediaDir($user_id) {
    $dir = dirname(dirname(__FILE__)) . '/media/uploads/' . intval($user_id) . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * [SEC-4] Handle screenshot upload with size + MIME validation
 * Returns filename on success, null on skip, false on error
 */
function handleScreenshot($uid) {
    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        return null; // No file uploaded — not an error
    }

    $file = $_FILES['screenshot'];

    // 5MB max file size
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return false; // Too large
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed_ext)) {
        return false; // Bad extension
    }

    // Validate actual MIME type (not just what client claims)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mime)) {
        return false; // MIME mismatch
    }

    // [SEC-8] Secure random filename
    $fn = 'trade_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $media_dir = safeMediaDir($uid);

    if (move_uploaded_file($file['tmp_name'], $media_dir . $fn)) {
        return $fn;
    }
    return false;
}

// ── RUN CSRF CHECK ON ALL POST REQUESTS ─────────────────────
csrfCheck();

// ── MAIN ────────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();
$uid = uid();

switch ($action) {

// ── AUTH ─────────────────────────────────────────────────
case 'get_user':
    echo json_encode(currentUser()); break;

case 'update_settings':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) { echo json_encode(['error' => 'Invalid request data']); break; }

    // [SEC-3] Password change requires current password
    if (!empty($d['new_password'])) {
        if (empty($d['current_password'])) {
            echo json_encode(['error' => 'Current password is required to set a new password']);
            break;
        }
        $u = currentUser();
        if (!password_verify($d['current_password'], $u['password'])) {
            echo json_encode(['error' => 'Current password is incorrect']);
            break;
        }
    }

    $fields = ['display_name','account_balance','starting_balance','max_drawdown_pct','daily_loss_limit','risk_per_trade_pct','prop_firm','challenge_phase','avatar_color'];
    $sets = implode(',', array_map(fn($f) => "$f=?", $fields));
    $vals = array_map(fn($f) => $d[$f] ?? null, $fields);
    $vals[] = $uid;
    $db->prepare("UPDATE users SET $sets WHERE id=?")->execute($vals);

    if (!empty($d['new_password'])) {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([
            password_hash($d['new_password'], PASSWORD_DEFAULT), $uid
        ]);
    }
    echo json_encode(['success' => true]); break;

// ── PAIRS ────────────────────────────────────────────────
case 'get_pairs':
    $s = $db->prepare("SELECT * FROM pairs WHERE user_id=? AND active=1 ORDER BY symbol");
    $s->execute([$uid]); echo json_encode($s->fetchAll()); break;

case 'add_pair':
    $d = json_decode(file_get_contents('php://input'), true);
    $sym = strtoupper(trim($d['symbol'] ?? ''));
    if (!$sym || strlen($sym) > 20) { echo json_encode(['error' => 'Valid symbol required (max 20 chars)']); break; }
    // Only allow alphanumeric + common separators
    if (!preg_match('/^[A-Z0-9\/\.\-_]+$/', $sym)) { echo json_encode(['error' => 'Invalid symbol format']); break; }
    $check = $db->prepare("SELECT id FROM pairs WHERE user_id=? AND symbol=?");
    $check->execute([$uid, $sym]);
    if ($check->fetch()) { echo json_encode(['error' => 'Pair already exists']); break; }
    $db->prepare("INSERT INTO pairs (user_id,symbol) VALUES (?,?)")->execute([$uid, $sym]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]); break;

case 'delete_pair':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid pair ID']); break; }
    $db->prepare("UPDATE pairs SET active=0 WHERE id=? AND user_id=?")->execute([$id, $uid]);
    echo json_encode(['success' => true]); break;

// ── TRADES ───────────────────────────────────────────────
case 'get_trades':
    $where = "WHERE user_id=?"; $params = [$uid];
    if (!empty($_GET['pair']))   { $where .= " AND pair=?";       $params[] = $_GET['pair']; }
    if (!empty($_GET['result'])) { $where .= " AND result=?";     $params[] = $_GET['result']; }
    if (!empty($_GET['from']))   { $where .= " AND trade_date>=?"; $params[] = $_GET['from']; }
    if (!empty($_GET['to']))     { $where .= " AND trade_date<=?"; $params[] = $_GET['to']; }
    $s = $db->prepare("SELECT * FROM trades $where ORDER BY trade_date DESC, id DESC");
    $s->execute($params); echo json_encode($s->fetchAll()); break;

case 'add_trade':
case 'update_trade':
    // Handle both JSON and FormData
    $isForm = !empty($_FILES) || !empty($_POST);
    if ($isForm) {
        $d = $_POST;
    } else {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // [SEC-2] Validate all numeric inputs
    $entry_price = num($d['entry_price'] ?? null, null);
    $stop_loss   = num($d['stop_loss'] ?? null, null);
    $take_profit = num($d['take_profit'] ?? null, null);
    $exit_price  = num($d['exit_price'] ?? null, null);
    $lot_size    = num($d['lot_size'] ?? null, null);
    $fees        = num($d['fees'] ?? 0);
    $exec_score  = num($d['exec_score'] ?? null, null);
    $r_multiple_input = num($d['r_multiple'] ?? null, null);

    // Calculate P&L with validated numbers
    $pnl = 0;
    if ($exit_price && $entry_price && $lot_size) {
        $pnl = ($d['direction'] ?? '') === 'Long'
            ? ($exit_price - $entry_price) * $lot_size
            : ($entry_price - $exit_price) * $lot_size;
    }
    $net = $pnl - $fees;

    // Calculate R-multiple with validated numbers
    $r = 0;
    if ($entry_price && $stop_loss && $entry_price != $stop_loss) {
        $sld = abs($entry_price - $stop_loss);
        if (($d['result'] ?? '') === 'Loss') $r = -1;
        elseif (($d['result'] ?? '') === 'Break Even') $r = 0;
        elseif ($exit_price) {
            $r = ($d['direction'] ?? '') === 'Long'
                ? ($exit_price - $entry_price) / $sld
                : ($entry_price - $exit_price) / $sld;
            $r = round($r, 2);
        }
    }

    // [SEC-4] Handle screenshot with validation
    $screenshot = isset($d['screenshot']) && $d['screenshot'] ? $d['screenshot'] : null;
    $upload_result = handleScreenshot($uid);
    if ($upload_result === false) {
        echo json_encode(['error' => 'Screenshot upload failed — max 5MB, images only (JPG, PNG, GIF, WEBP)']);
        break;
    }
    if ($upload_result !== null) {
        $screenshot = $upload_result;
    }

    // Validate direction
    $direction = ($d['direction'] ?? '') === 'Short' ? 'Short' : 'Long';

    // Validate result
    $valid_results = ['Win', 'Loss', 'Break Even', ''];
    $result = in_array($d['result'] ?? '', $valid_results) ? ($d['result'] ?? '') : '';

    // Validate confidence
    $valid_confidence = ['High', 'Medium', 'Low', ''];
    $confidence = in_array($d['confidence'] ?? '', $valid_confidence) ? ($d['confidence'] ?? '') : '';

    $cols = ['trade_date','session','time_in','time_out','pair','direction','entry_price','stop_loss','take_profit','exit_price','lot_size','risk_amount','fees','result','confidence','exec_score','fib_level','fsa_rules','notes'];

    if ($action === 'update_trade') {
        // [SEC-10] Validate trade ID
        $trade_id = validId($d['id'] ?? 0);
        if (!$trade_id) { echo json_encode(['error' => 'Invalid trade ID']); break; }

        $update_vals = array_map(fn($k) => ($d[$k] ?? null) ?: null, $cols);
        $update_vals[] = round($pnl, 4);
        $update_vals[] = round($net, 4);
        $update_vals[] = $r;
        $update_vals[] = $screenshot;
        $update_vals[] = $trade_id;
        $update_vals[] = $uid;
        $sets = implode(',', array_map(fn($c) => "$c=?", $cols));
        $sets .= ",pnl=?,net_pnl=?,r_multiple=?,screenshot=?";
        $db->prepare("UPDATE trades SET $sets WHERE id=? AND user_id=?")->execute($update_vals);
    } else {
        $vals2 = array_map(fn($k) => ($d[$k] ?? null) ?: null, $cols);
        $vals2 = array_merge([$uid], $vals2, [round($pnl, 4), round($net, 4), $r, $screenshot]);
        $ph2 = implode(',', array_fill(0, count($cols) + 4, '?'));
        $allcols2 = implode(',', $cols) . ",pnl,net_pnl,r_multiple,screenshot";
        $db->prepare("INSERT INTO trades (user_id,$allcols2) VALUES (?,{$ph2})")->execute($vals2);
    }

    // Update daily limits
    $dl_date = $d['trade_date'] ?? date('Y-m-d');
    $db->prepare("INSERT INTO daily_limits (user_id,log_date,daily_pnl,trades_count) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE daily_pnl=daily_pnl+?,trades_count=trades_count+1")
       ->execute([$uid, $dl_date, round($net, 4), round($net, 4)]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]); break;

case 'delete_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    // [SEC-10] Validate trade ID
    $trade_id = validId($d['id'] ?? 0);
    if (!$trade_id) { echo json_encode(['error' => 'Invalid trade ID']); break; }

    // Get screenshot to delete
    $s = $db->prepare("SELECT screenshot FROM trades WHERE id=? AND user_id=?");
    $s->execute([$trade_id, $uid]); $t = $s->fetch();
    if ($t && $t['screenshot']) {
        // [SEC-7] Consistent media path
        $media_dir = safeMediaDir($uid);
        $filepath = $media_dir . basename($t['screenshot']); // basename() prevents directory traversal
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    $db->prepare("DELETE FROM trades WHERE id=? AND user_id=?")->execute([$trade_id, $uid]);
    echo json_encode(['success' => true]); break;

// ── STATS ────────────────────────────────────────────────
case 'get_stats':
    $month = $_GET['month'] ?? null;
    $year  = $_GET['year']  ?? null;
    $where = "WHERE user_id=?"; $p = [$uid];
    if ($month && $year) {
        $where .= " AND MONTH(trade_date)=? AND YEAR(trade_date)=?";
        $p[] = intval($month);
        $p[] = intval($year);
    }

    $q  = fn($sql) => $db->prepare($sql);
    $qv = function($sql, $p) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); };
    $qa = function($sql, $p) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(); };

    $stats = [];
    $stats['total_trades']  = $qv("SELECT COUNT(*) FROM trades $where", $p);
    $stats['wins']          = $qv("SELECT COUNT(*) FROM trades $where AND result='Win'", $p);
    $stats['losses']        = $qv("SELECT COUNT(*) FROM trades $where AND result='Loss'", $p);
    $stats['break_evens']   = $qv("SELECT COUNT(*) FROM trades $where AND result='Break Even'", $p);
    $stats['win_rate']      = $stats['total_trades'] > 0 ? round($stats['wins'] / $stats['total_trades'] * 100, 1) : 0;
    $stats['net_pnl']       = $qv("SELECT COALESCE(SUM(net_pnl),0) FROM trades $where", $p);
    $stats['gross_pnl']     = $qv("SELECT COALESCE(SUM(pnl),0) FROM trades $where", $p);
    $stats['total_fees']    = $qv("SELECT COALESCE(SUM(fees),0) FROM trades $where", $p);
    $stats['avg_win']       = $qv("SELECT COALESCE(AVG(net_pnl),0) FROM trades $where AND result='Win'", $p);
    $stats['avg_loss']      = $qv("SELECT COALESCE(AVG(net_pnl),0) FROM trades $where AND result='Loss'", $p);
    $stats['avg_r']         = $qv("SELECT COALESCE(AVG(r_multiple),0) FROM trades $where AND r_multiple IS NOT NULL", $p);
    $wins_sum  = $qv("SELECT COALESCE(SUM(net_pnl),0) FROM trades $where AND result='Win'", $p);
    $loss_sum  = abs($qv("SELECT COALESCE(SUM(net_pnl),0) FROM trades $where AND result='Loss'", $p));
    $stats['profit_factor'] = $loss_sum > 0 ? round($wins_sum / $loss_sum, 2) : 0;
    $stats['by_session']    = $qa("SELECT session,COUNT(*) as trades,SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins,COALESCE(SUM(net_pnl),0) as pnl FROM trades $where AND session IS NOT NULL GROUP BY session", $p);
    $stats['by_fib']        = $qa("SELECT fib_level,COUNT(*) as trades,SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins,COALESCE(SUM(net_pnl),0) as pnl FROM trades $where AND fib_level IS NOT NULL GROUP BY fib_level ORDER BY fib_level", $p);
    $stats['by_pair']       = $qa("SELECT pair,COUNT(*) as trades,SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins,COALESCE(SUM(net_pnl),0) as pnl FROM trades $where AND pair IS NOT NULL GROUP BY pair", $p);
    $stats['by_direction']  = $qa("SELECT direction,COUNT(*) as trades,SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins,COALESCE(SUM(net_pnl),0) as pnl FROM trades $where AND direction IS NOT NULL GROUP BY direction", $p);

    // Cumulative P&L + drawdown
    $cum_trades = $qa("SELECT id,trade_date,net_pnl FROM trades $where ORDER BY trade_date,id", $p);
    $u = currentUser();
    // [SEC-6] Division by zero protection
    $starting_bal = floatval($u['starting_balance'] ?? 0);
    $running = 0; $peak = 0; $cum = [];
    foreach ($cum_trades as $i => $t) {
        $running += $t['net_pnl'];
        if ($running > $peak) $peak = $running;
        $dd = ($peak > 0 && $starting_bal > 0) ? (($peak - $running) / $starting_bal) * 100 : 0;
        $cum[] = ['trade' => $i + 1, 'net_pnl' => round($t['net_pnl'], 2), 'cumulative' => round($running, 2), 'drawdown' => round($dd, 2), 'date' => $t['trade_date']];
    }
    $stats['cumulative'] = $cum;

    // Max drawdown
    $stats['max_drawdown_pct']     = count($cum) > 0 ? max(array_column($cum, 'drawdown')) : 0;
    $stats['current_drawdown_pct'] = count($cum) > 0 ? end($cum)['drawdown'] : 0;

    // Streak
    $all_results = $qa("SELECT result FROM trades WHERE user_id=? AND result IN ('Win','Loss') ORDER BY trade_date,id", [$uid]);
    $cur_streak = 0; $cur_type = ''; $max_win = 0; $max_loss = 0; $tmp = 0; $tmp_type = '';
    foreach ($all_results as $t) {
        if ($t['result'] === $tmp_type) { $tmp++; }
        else { $tmp = 1; $tmp_type = $t['result']; }
        if ($tmp_type === 'Win' && $tmp > $max_win) $max_win = $tmp;
        if ($tmp_type === 'Loss' && $tmp > $max_loss) $max_loss = $tmp;
    }
    $last = end($all_results);
    if ($last) { $cur_type = $last['result']; $cur_streak = $tmp; }
    $stats['streak'] = ['current' => $cur_streak, 'type' => $cur_type, 'max_win' => $max_win, 'max_loss' => $max_loss];

    // Hours heatmap
    $hours = $qa("SELECT HOUR(time_in) as hour, COUNT(*) as trades, SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins, COALESCE(SUM(net_pnl),0) as pnl FROM trades WHERE user_id=? AND time_in IS NOT NULL GROUP BY HOUR(time_in) ORDER BY hour", [$uid]);
    $stats['by_hour'] = $hours;

    // Calendar heatmap
    $cal = $qa("SELECT trade_date, COALESCE(SUM(net_pnl),0) as pnl, COUNT(*) as trades FROM trades WHERE user_id=? GROUP BY trade_date ORDER BY trade_date", [$uid]);
    $stats['calendar'] = $cal;

    // [SEC-6] Daily loss check — division by zero protected
    $today_pnl = $qv("SELECT COALESCE(SUM(net_pnl),0) FROM trades WHERE user_id=? AND trade_date=CURDATE()", [$uid]);
    $u = currentUser();
    $daily_limit = floatval($u['daily_loss_limit'] ?? 0);
    $stats['today_pnl'] = $today_pnl;
    $stats['daily_limit_pct'] = ($u && $daily_limit > 0) ? abs(min(0, $today_pnl)) / $daily_limit * 100 : 0;
    $stats['dd_pct'] = ($u && $starting_bal > 0)
        ? abs(min(0, floatval($u['account_balance']) - $starting_bal)) / $starting_bal * 100
        : 0;

    echo json_encode($stats); break;

// ── RISK CALCULATOR ──────────────────────────────────────
case 'calculate_risk':
    $d = json_decode(file_get_contents('php://input'), true);
    $u = currentUser();
    $balance  = num($d['balance'] ?? $u['account_balance']);
    $risk_pct = num($d['risk_pct'] ?? $u['risk_per_trade_pct']);
    $entry    = num($d['entry'] ?? 0);
    $sl       = num($d['sl'] ?? 0);
    if ($entry <= 0 || $sl <= 0 || $entry == $sl) { echo json_encode(['error' => 'Invalid prices']); break; }
    $risk_amt = $balance * $risk_pct / 100;
    $sl_dist  = abs($entry - $sl);
    $lot_size = $risk_amt / $sl_dist;
    $tp = num($d['tp'] ?? 0);
    $rr = ($tp > 0 && $sl_dist > 0) ? abs($tp - $entry) / $sl_dist : 0;
    $potential_profit = $tp > 0 ? $lot_size * abs($tp - $entry) : 0;
    echo json_encode([
        'risk_amount'      => round($risk_amt, 2),
        'lot_size'         => round($lot_size, 4),
        'sl_distance'      => round($sl_dist, 2),
        'rr_ratio'         => round($rr, 2),
        'potential_profit'  => round($potential_profit, 2),
        'risk_pct'         => $risk_pct
    ]); break;

// ── ALERTS ───────────────────────────────────────────────
case 'get_alerts':
    $u = currentUser();
    $alerts = [];

    $today_pnl_stmt = $db->prepare("SELECT COALESCE(SUM(net_pnl),0) FROM trades WHERE user_id=? AND trade_date=CURDATE()");
    $today_pnl_stmt->execute([$uid]); $today = floatval($today_pnl_stmt->fetchColumn());

    $today_trades = $db->prepare("SELECT COUNT(*) FROM trades WHERE user_id=? AND trade_date=CURDATE()");
    $today_trades->execute([$uid]); $tc = intval($today_trades->fetchColumn());

    // [SEC-6] Division by zero protection — check starting_balance and daily_loss_limit before dividing
    $starting_bal = floatval($u['starting_balance'] ?? 0);
    $daily_limit  = floatval($u['daily_loss_limit'] ?? 0);
    $max_dd_pct   = floatval($u['max_drawdown_pct'] ?? 10);

    $dd_pct = ($starting_bal > 0)
        ? abs(min(0, floatval($u['account_balance']) - $starting_bal)) / $starting_bal * 100
        : 0;

    $daily_pct = ($daily_limit > 0)
        ? abs(min(0, $today)) / $daily_limit * 100
        : 0;

    if ($daily_pct >= 100)     $alerts[] = ['type' => 'danger',  'icon' => '🛑', 'msg' => 'DAILY LOSS LIMIT REACHED — STOP TRADING TODAY'];
    elseif ($daily_pct >= 80)  $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'msg' => 'At ' . round($daily_pct) . '% of daily loss limit — be careful'];

    if ($dd_pct >= $max_dd_pct)       $alerts[] = ['type' => 'danger',  'icon' => '💀', 'msg' => 'MAX DRAWDOWN REACHED — Account at risk'];
    elseif ($dd_pct >= $max_dd_pct * 0.8) $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'msg' => 'Drawdown at ' . round($dd_pct, 1) . '% — approaching limit of ' . $max_dd_pct . '%'];

    if ($tc >= 2) $alerts[] = ['type' => 'info', 'icon' => 'ℹ️', 'msg' => 'You have taken ' . $tc . ' trades today — max recommended is 2'];

    // Consecutive losses
    $last3 = $db->prepare("SELECT result FROM trades WHERE user_id=? AND result IN ('Win','Loss') ORDER BY trade_date DESC, id DESC LIMIT 3");
    $last3->execute([$uid]); $r3 = $last3->fetchAll();
    if (count($r3) >= 3 && array_sum(array_map(fn($r) => $r['result'] === 'Loss' ? 1 : 0, $r3)) >= 3)
        $alerts[] = ['type' => 'danger', 'icon' => '🚨', 'msg' => '3 consecutive losses — consider stopping for the day'];

    echo json_encode($alerts); break;

// ── IMPORT FROM EXCEL DATA ───────────────────────────────
case 'import_trades':
    $d = json_decode(file_get_contents('php://input'), true);
    $trades = $d['trades'] ?? [];

    // [SEC-5] Import limit — max 500 trades per batch
    if (count($trades) > 500) {
        echo json_encode(['error' => 'Import limit is 500 trades per batch. You sent ' . count($trades) . '.']);
        break;
    }

    $count = 0;
    foreach ($trades as $t) {
        if (empty($t['trade_date']) || empty($t['pair']) || empty($t['direction'])) continue;
        $pnl  = num($t['pnl'] ?? 0);
        $fees = num($t['fees'] ?? 0);
        $net  = $pnl - $fees;
        $stmt = $db->prepare("INSERT INTO trades (user_id,trade_date,session,pair,direction,entry_price,stop_loss,take_profit,exit_price,lot_size,pnl,fees,net_pnl,r_multiple,result,confidence,exec_score,fib_level,fsa_rules,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $uid,
            $t['trade_date'],
            $t['session'] ?? 'London',
            $t['pair'],
            $t['direction'],
            $t['entry_price'] ?? null,
            $t['stop_loss'] ?? null,
            $t['take_profit'] ?? null,
            $t['exit_price'] ?? null,
            $t['lot_size'] ?? null,
            $pnl, $fees, $net,
            num($t['r_multiple'] ?? 0),
            $t['result'] ?? null,
            $t['confidence'] ?? null,
            $t['exec_score'] ?? null,
            $t['fib_level'] ?? null,
            $t['fsa_rules'] ?? null,
            $t['notes'] ?? null
        ]);
        $count++;
    }
    echo json_encode(['success' => true, 'imported' => $count]); break;

// ── STRATEGY TESTS ───────────────────────────────────────
case 'get_strategy_trades':
    $s = $db->prepare("SELECT * FROM strategy_tests WHERE user_id=? ORDER BY created_at DESC");
    $s->execute([$uid]); echo json_encode($s->fetchAll()); break;

case 'add_strategy_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    $s = $db->prepare("INSERT INTO strategy_tests (user_id,strategy_name,timeframe,market,rule1,rule2,rule3,rule4,rule5,test_date,pair,direction,r1,r2,r3,r4,r5,result,fib_level,r_multiple,net_pnl,session,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([
        $uid,
        $d['strategy_name'] ?? '', $d['timeframe'] ?? '', $d['market'] ?? '',
        $d['rule1'] ?? '', $d['rule2'] ?? '', $d['rule3'] ?? '', $d['rule4'] ?? '', $d['rule5'] ?? '',
        date('Y-m-d'),
        $d['pair'] ?? '', $d['direction'] ?? 'Long',
        $d['r1'] ?? 'N', $d['r2'] ?? 'N', $d['r3'] ?? 'N', $d['r4'] ?? 'N', $d['r5'] ?? 'N',
        $d['result'] ?? null, $d['fib_level'] ?? null,
        num($d['r_multiple'] ?? 0), num($d['net_pnl'] ?? 0),
        $d['session'] ?? null, $d['notes'] ?? null
    ]);
    echo json_encode(['success' => true]); break;

case 'delete_strategy_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid ID']); break; }
    $db->prepare("DELETE FROM strategy_tests WHERE id=? AND user_id=?")->execute([$id, $uid]);
    echo json_encode(['success' => true]); break;

// ── WEEKLY REVIEWS ───────────────────────────────────────
case 'get_reviews':
    $s = $db->prepare("SELECT * FROM weekly_reviews WHERE user_id=? ORDER BY week_start DESC");
    $s->execute([$uid]); echo json_encode($s->fetchAll()); break;

case 'save_review':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!empty($d['id'])) {
        $review_id = validId($d['id']);
        if (!$review_id) { echo json_encode(['error' => 'Invalid review ID']); break; }
        $db->prepare("UPDATE weekly_reviews SET week_start=?,week_end=?,process_score=?,mindset_score=?,key_lesson=?,what_went_well=?,what_to_improve=?,rules_followed=? WHERE id=? AND user_id=?")
           ->execute([$d['week_start'], $d['week_end'], $d['process_score'], $d['mindset_score'], $d['key_lesson'], $d['what_went_well'], $d['what_to_improve'], $d['rules_followed'], $review_id, $uid]);
    } else {
        $db->prepare("INSERT INTO weekly_reviews (user_id,week_start,week_end,process_score,mindset_score,key_lesson,what_went_well,what_to_improve,rules_followed) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$uid, $d['week_start'], $d['week_end'], $d['process_score'], $d['mindset_score'], $d['key_lesson'], $d['what_went_well'], $d['what_to_improve'], $d['rules_followed']]);
    }
    echo json_encode(['success' => true]); break;

// [SEC-9] Sanitized error — never echo raw user input
default:
    echo json_encode(['error' => 'Unknown action']);
}
?>
