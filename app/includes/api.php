<?php
/**
 * FSA Trading Journal — API v2.3.0
 * Major: Challenge Settings separated from User Profile
 * - New challenges table (one user → many challenges)
 * - Profile is personal identity; challenge is trading account
 * - All trades scoped to active challenge
 * - Includes all v2.2.7 security fixes
 */

define('IS_API', true);
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

// ── SECURITY HELPERS (from v2.2.7) ─────────────────────────
function csrfCheck() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') !== false) return;
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
function num($val, $default = 0) {
    if ($val === null || $val === '') return $default;
    return floatval($val);
}
function validId($val) {
    return filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
function safeMediaDir($user_id) {
    $dir = dirname(dirname(__FILE__)) . '/media/uploads/' . intval($user_id) . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}
function handleScreenshot($uid) {
    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES['screenshot'];
    if ($file['size'] > 5 * 1024 * 1024) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'])) return false;
    $fn = 'trade_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $media_dir = safeMediaDir($uid);
    if (move_uploaded_file($file['tmp_name'], $media_dir . $fn)) return $fn;
    return false;
}

/**
 * Get active challenge for current user
 * Returns challenge row or null
 */
function getActiveChallenge() {
    $db = getDB();
    $uid = uid();
    $s = $db->prepare("SELECT * FROM challenges WHERE user_id=? AND is_active=1 LIMIT 1");
    $s->execute([$uid]);
    return $s->fetch() ?: null;
}

csrfCheck();

// ── MAIN ────────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();
$uid = uid();

switch ($action) {

// ══════════════════════════════════════════════════════════════
// PROFILE (User Identity)
// ══════════════════════════════════════════════════════════════
case 'get_user':
    $u = currentUser();
    $ch = getActiveChallenge();
    // Merge active challenge data for backward compatibility
    $result = [
        'id'                => $u['id'],
        'username'          => $u['username'],
        'display_name'      => $u['display_name'],
        'avatar_color'      => $u['avatar_color'],
        'bio'               => $u['bio'] ?? '',
        // Active challenge fields (backward compat)
        'account_balance'   => $ch['current_balance'] ?? $u['account_balance'] ?? 10000,
        'starting_balance'  => $ch['starting_balance'] ?? $u['starting_balance'] ?? 10000,
        'max_drawdown_pct'  => $ch['max_drawdown_pct'] ?? $u['max_drawdown_pct'] ?? 10,
        'daily_loss_limit'  => $ch['daily_loss_limit'] ?? $u['daily_loss_limit'] ?? 500,
        'risk_per_trade_pct'=> $ch['risk_per_trade_pct'] ?? $u['risk_per_trade_pct'] ?? 0.5,
        'prop_firm'         => $ch['prop_firm'] ?? $u['prop_firm'] ?? '',
        'challenge_phase'   => $ch['challenge_phase'] ?? $u['challenge_phase'] ?? '',
        'profit_target_pct' => $ch['profit_target_pct'] ?? 8,
        // Active challenge ID
        'active_challenge_id' => $ch['id'] ?? null,
        'active_challenge_name' => $ch['name'] ?? 'Default',
        'password'          => $u['password'] ?? '',
    ];
    echo json_encode($result); break;

case 'update_profile':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) { echo json_encode(['error' => 'Invalid request']); break; }

    // Password change requires current password
    if (!empty($d['new_password'])) {
        if (empty($d['current_password'])) {
            echo json_encode(['error' => 'Current password required']); break;
        }
        $u = currentUser();
        if (!password_verify($d['current_password'], $u['password'])) {
            echo json_encode(['error' => 'Current password is incorrect']); break;
        }
    }

    $db->prepare("UPDATE users SET display_name=?, avatar_color=?, bio=? WHERE id=?")
       ->execute([$d['display_name'] ?? null, $d['avatar_color'] ?? '#4f7cff', $d['bio'] ?? '', $uid]);

    if (!empty($d['new_password'])) {
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([password_hash($d['new_password'], PASSWORD_DEFAULT), $uid]);
    }
    echo json_encode(['success' => true]); break;

// Backward compat — old settings endpoint still works
case 'update_settings':
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) { echo json_encode(['error' => 'Invalid request']); break; }

    // Password change requires current password
    if (!empty($d['new_password'])) {
        if (empty($d['current_password'])) {
            echo json_encode(['error' => 'Current password required']); break;
        }
        $u = currentUser();
        if (!password_verify($d['current_password'], $u['password'])) {
            echo json_encode(['error' => 'Current password is incorrect']); break;
        }
    }

    // Update profile fields on users table
    $db->prepare("UPDATE users SET display_name=?, avatar_color=?, bio=? WHERE id=?")
       ->execute([$d['display_name'] ?? null, $d['avatar_color'] ?? '#4f7cff', $d['bio'] ?? '', $uid]);

    if (!empty($d['new_password'])) {
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([password_hash($d['new_password'], PASSWORD_DEFAULT), $uid]);
    }

    // Update active challenge if challenge fields provided
    $ch = getActiveChallenge();
    if ($ch) {
        $db->prepare("UPDATE challenges SET prop_firm=?, challenge_phase=?, starting_balance=?, current_balance=?, max_drawdown_pct=?, daily_loss_limit=?, risk_per_trade_pct=? WHERE id=? AND user_id=?")
           ->execute([
               $d['prop_firm'] ?? $ch['prop_firm'],
               $d['challenge_phase'] ?? $ch['challenge_phase'],
               num($d['starting_balance'] ?? $ch['starting_balance']),
               num($d['account_balance'] ?? $ch['current_balance']),
               num($d['max_drawdown_pct'] ?? $ch['max_drawdown_pct']),
               num($d['daily_loss_limit'] ?? $ch['daily_loss_limit']),
               num($d['risk_per_trade_pct'] ?? $ch['risk_per_trade_pct']),
               $ch['id'], $uid
           ]);
    }
    echo json_encode(['success' => true]); break;

// ══════════════════════════════════════════════════════════════
// CHALLENGES (Trading Accounts)
// ══════════════════════════════════════════════════════════════
case 'get_challenges':
    $s = $db->prepare("SELECT * FROM challenges WHERE user_id=? ORDER BY is_active DESC, created_at DESC");
    $s->execute([$uid]);
    echo json_encode($s->fetchAll()); break;

case 'get_active_challenge':
    $ch = getActiveChallenge();
    echo json_encode($ch ?: ['error' => 'No active challenge']); break;

case 'add_challenge':
    $d = json_decode(file_get_contents('php://input'), true);
    if (empty($d['name'])) { echo json_encode(['error' => 'Challenge name required']); break; }

    // If this is the first challenge or set_active requested, deactivate others
    $existing = $db->prepare("SELECT COUNT(*) FROM challenges WHERE user_id=?");
    $existing->execute([$uid]);
    $count = intval($existing->fetchColumn());
    $make_active = ($count === 0 || !empty($d['set_active']));

    if ($make_active) {
        $db->prepare("UPDATE challenges SET is_active=0 WHERE user_id=?")->execute([$uid]);
    }

    $db->prepare("INSERT INTO challenges (user_id, name, prop_firm, challenge_phase, starting_balance, current_balance, max_drawdown_pct, daily_loss_limit, risk_per_trade_pct, profit_target_pct, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $uid,
           trim($d['name']),
           $d['prop_firm'] ?? '',
           $d['challenge_phase'] ?? 'Phase 1',
           num($d['starting_balance'] ?? 10000),
           num($d['current_balance'] ?? $d['starting_balance'] ?? 10000),
           num($d['max_drawdown_pct'] ?? 10),
           num($d['daily_loss_limit'] ?? 500),
           num($d['risk_per_trade_pct'] ?? 0.5),
           num($d['profit_target_pct'] ?? 8),
           $make_active ? 1 : 0
       ]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]); break;

case 'update_challenge':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid challenge ID']); break; }
    if (empty($d['name'])) { echo json_encode(['error' => 'Challenge name required']); break; }

    $db->prepare("UPDATE challenges SET name=?, prop_firm=?, challenge_phase=?, starting_balance=?, current_balance=?, max_drawdown_pct=?, daily_loss_limit=?, risk_per_trade_pct=?, profit_target_pct=?, status=? WHERE id=? AND user_id=?")
       ->execute([
           trim($d['name']),
           $d['prop_firm'] ?? '',
           $d['challenge_phase'] ?? 'Phase 1',
           num($d['starting_balance'] ?? 10000),
           num($d['current_balance'] ?? 10000),
           num($d['max_drawdown_pct'] ?? 10),
           num($d['daily_loss_limit'] ?? 500),
           num($d['risk_per_trade_pct'] ?? 0.5),
           num($d['profit_target_pct'] ?? 8),
           $d['status'] ?? 'active',
           $id, $uid
       ]);
    echo json_encode(['success' => true]); break;

case 'delete_challenge':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid challenge ID']); break; }

    // Don't allow deleting the only challenge
    $count = $db->prepare("SELECT COUNT(*) FROM challenges WHERE user_id=?");
    $count->execute([$uid]);
    if (intval($count->fetchColumn()) <= 1) {
        echo json_encode(['error' => 'Cannot delete your only challenge. Create another one first.']); break;
    }

    // Check if it's the active one
    $ch = $db->prepare("SELECT is_active FROM challenges WHERE id=? AND user_id=?");
    $ch->execute([$id, $uid]);
    $was_active = $ch->fetch()['is_active'] ?? 0;

    // Delete the challenge and its trades
    $db->prepare("DELETE FROM trades WHERE challenge_id=? AND user_id=?")->execute([$id, $uid]);
    $db->prepare("DELETE FROM challenges WHERE id=? AND user_id=?")->execute([$id, $uid]);

    // If it was active, activate the next one
    if ($was_active) {
        $next = $db->prepare("SELECT id FROM challenges WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
        $next->execute([$uid]);
        $nextId = $next->fetchColumn();
        if ($nextId) {
            $db->prepare("UPDATE challenges SET is_active=1 WHERE id=?")->execute([$nextId]);
        }
    }
    echo json_encode(['success' => true]); break;

case 'switch_challenge':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid challenge ID']); break; }

    // Verify challenge belongs to user
    $ch = $db->prepare("SELECT id FROM challenges WHERE id=? AND user_id=?");
    $ch->execute([$id, $uid]);
    if (!$ch->fetch()) { echo json_encode(['error' => 'Challenge not found']); break; }

    // Deactivate all, activate selected
    $db->prepare("UPDATE challenges SET is_active=0 WHERE user_id=?")->execute([$uid]);
    $db->prepare("UPDATE challenges SET is_active=1 WHERE id=? AND user_id=?")->execute([$id, $uid]);
    echo json_encode(['success' => true]); break;

// ══════════════════════════════════════════════════════════════
// PAIRS
// ══════════════════════════════════════════════════════════════
case 'get_pairs':
    $s = $db->prepare("SELECT * FROM pairs WHERE user_id=? AND active=1 ORDER BY symbol");
    $s->execute([$uid]); echo json_encode($s->fetchAll()); break;

case 'add_pair':
    $d = json_decode(file_get_contents('php://input'), true);
    $sym = strtoupper(trim($d['symbol'] ?? ''));
    if (!$sym || strlen($sym) > 20) { echo json_encode(['error' => 'Valid symbol required']); break; }
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

// ══════════════════════════════════════════════════════════════
// TRADES (scoped to active challenge)
// ══════════════════════════════════════════════════════════════
case 'get_trades':
    $ch = getActiveChallenge();
    $chId = $ch['id'] ?? 0;
    $where = "WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL)";
    $params = [$uid, $chId];
    if (!empty($_GET['pair']))   { $where .= " AND pair=?";       $params[] = $_GET['pair']; }
    if (!empty($_GET['result'])) { $where .= " AND result=?";     $params[] = $_GET['result']; }
    if (!empty($_GET['from']))   { $where .= " AND trade_date>=?"; $params[] = $_GET['from']; }
    if (!empty($_GET['to']))     { $where .= " AND trade_date<=?"; $params[] = $_GET['to']; }
    $s = $db->prepare("SELECT * FROM trades $where ORDER BY trade_date DESC, id DESC");
    $s->execute($params); echo json_encode($s->fetchAll()); break;

case 'add_trade':
case 'update_trade':
    $ch = getActiveChallenge();
    $chId = $ch['id'] ?? null;

    $isForm = !empty($_FILES) || !empty($_POST);
    $d = $isForm ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

    $entry_price = num($d['entry_price'] ?? null, null);
    $stop_loss   = num($d['stop_loss'] ?? null, null);
    $take_profit = num($d['take_profit'] ?? null, null);
    $exit_price  = num($d['exit_price'] ?? null, null);
    $lot_size    = num($d['lot_size'] ?? null, null);
    $fees        = num($d['fees'] ?? 0);

    $pnl = 0;
    if ($exit_price && $entry_price && $lot_size) {
        $pnl = ($d['direction'] ?? '') === 'Long'
            ? ($exit_price - $entry_price) * $lot_size
            : ($entry_price - $exit_price) * $lot_size;
    }
    $net = $pnl - $fees;

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

    $screenshot = isset($d['screenshot']) && $d['screenshot'] ? $d['screenshot'] : null;
    $upload_result = handleScreenshot($uid);
    if ($upload_result === false) {
        echo json_encode(['error' => 'Screenshot upload failed — max 5MB, images only']);
        break;
    }
    if ($upload_result !== null) $screenshot = $upload_result;

    $cols = ['trade_date','session','time_in','time_out','pair','direction','entry_price','stop_loss','take_profit','exit_price','lot_size','risk_amount','fees','result','confidence','exec_score','fib_level','fsa_rules','notes'];

    if ($action === 'update_trade') {
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
        $vals2 = array_merge([$uid, $chId], $vals2, [round($pnl, 4), round($net, 4), $r, $screenshot]);
        $ph2 = implode(',', array_fill(0, count($cols) + 4, '?'));
        $allcols2 = implode(',', $cols) . ",pnl,net_pnl,r_multiple,screenshot";
        $db->prepare("INSERT INTO trades (user_id,challenge_id,$allcols2) VALUES (?,?,{$ph2})")->execute($vals2);
    }

    $dl_date = $d['trade_date'] ?? date('Y-m-d');
    $db->prepare("INSERT INTO daily_limits (user_id,log_date,daily_pnl,trades_count) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE daily_pnl=daily_pnl+?,trades_count=trades_count+1")
       ->execute([$uid, $dl_date, round($net, 4), round($net, 4)]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]); break;

case 'delete_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    $trade_id = validId($d['id'] ?? 0);
    if (!$trade_id) { echo json_encode(['error' => 'Invalid trade ID']); break; }
    $s = $db->prepare("SELECT screenshot FROM trades WHERE id=? AND user_id=?");
    $s->execute([$trade_id, $uid]); $t = $s->fetch();
    if ($t && $t['screenshot']) {
        $media_dir = safeMediaDir($uid);
        $filepath = $media_dir . basename($t['screenshot']);
        if (file_exists($filepath)) unlink($filepath);
    }
    $db->prepare("DELETE FROM trades WHERE id=? AND user_id=?")->execute([$trade_id, $uid]);
    echo json_encode(['success' => true]); break;

// ══════════════════════════════════════════════════════════════
// STATS (scoped to active challenge)
// ══════════════════════════════════════════════════════════════
case 'get_stats':
    $ch = getActiveChallenge();
    $chId = $ch['id'] ?? 0;
    $month = $_GET['month'] ?? null;
    $year  = $_GET['year']  ?? null;
    $where = "WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL)";
    $p = [$uid, $chId];
    if ($month && $year) { $where .= " AND MONTH(trade_date)=? AND YEAR(trade_date)=?"; $p[] = intval($month); $p[] = intval($year); }

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
    $starting_bal = floatval($ch['starting_balance'] ?? 10000);
    $running = 0; $peak = 0; $cum = [];
    foreach ($cum_trades as $i => $t) {
        $running += $t['net_pnl'];
        if ($running > $peak) $peak = $running;
        $dd = ($peak > 0 && $starting_bal > 0) ? (($peak - $running) / $starting_bal) * 100 : 0;
        $cum[] = ['trade' => $i + 1, 'net_pnl' => round($t['net_pnl'], 2), 'cumulative' => round($running, 2), 'drawdown' => round($dd, 2), 'date' => $t['trade_date']];
    }
    $stats['cumulative'] = $cum;
    $stats['max_drawdown_pct']     = count($cum) > 0 ? max(array_column($cum, 'drawdown')) : 0;
    $stats['current_drawdown_pct'] = count($cum) > 0 ? end($cum)['drawdown'] : 0;

    // Streak
    $chWhere = "WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL)";
    $all_results = $qa("SELECT result FROM trades $chWhere AND result IN ('Win','Loss') ORDER BY trade_date,id", [$uid, $chId]);
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

    // Hours + calendar
    $stats['by_hour'] = $qa("SELECT HOUR(time_in) as hour, COUNT(*) as trades, SUM(CASE WHEN result='Win' THEN 1 ELSE 0 END) as wins, COALESCE(SUM(net_pnl),0) as pnl FROM trades $chWhere AND time_in IS NOT NULL GROUP BY HOUR(time_in) ORDER BY hour", [$uid, $chId]);
    $stats['calendar'] = $qa("SELECT trade_date, COALESCE(SUM(net_pnl),0) as pnl, COUNT(*) as trades FROM trades $chWhere GROUP BY trade_date ORDER BY trade_date", [$uid, $chId]);

    // Daily loss check
    $today_pnl = $qv("SELECT COALESCE(SUM(net_pnl),0) FROM trades WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL) AND trade_date=CURDATE()", [$uid, $chId]);
    $daily_limit = floatval($ch['daily_loss_limit'] ?? 500);
    $stats['today_pnl'] = $today_pnl;
    $stats['daily_limit_pct'] = $daily_limit > 0 ? abs(min(0, $today_pnl)) / $daily_limit * 100 : 0;
    $stats['dd_pct'] = ($starting_bal > 0)
        ? abs(min(0, floatval($ch['current_balance'] ?? $starting_bal) - $starting_bal)) / $starting_bal * 100
        : 0;

    echo json_encode($stats); break;

// ══════════════════════════════════════════════════════════════
// RISK CALCULATOR
// ══════════════════════════════════════════════════════════════
case 'calculate_risk':
    $d = json_decode(file_get_contents('php://input'), true);
    $ch = getActiveChallenge();
    $balance  = num($d['balance'] ?? $ch['current_balance'] ?? 10000);
    $risk_pct = num($d['risk_pct'] ?? $ch['risk_per_trade_pct'] ?? 0.5);
    $entry    = num($d['entry'] ?? 0);
    $sl       = num($d['sl'] ?? 0);
    if ($entry <= 0 || $sl <= 0 || $entry == $sl) { echo json_encode(['error' => 'Invalid prices']); break; }
    $risk_amt = $balance * $risk_pct / 100;
    $sl_dist  = abs($entry - $sl);
    $lot_size = $risk_amt / $sl_dist;
    $tp = num($d['tp'] ?? 0);
    $rr = ($tp > 0 && $sl_dist > 0) ? abs($tp - $entry) / $sl_dist : 0;
    $potential_profit = $tp > 0 ? $lot_size * abs($tp - $entry) : 0;
    echo json_encode(['risk_amount' => round($risk_amt, 2), 'lot_size' => round($lot_size, 4), 'sl_distance' => round($sl_dist, 2), 'rr_ratio' => round($rr, 2), 'potential_profit' => round($potential_profit, 2), 'risk_pct' => $risk_pct]); break;

// ══════════════════════════════════════════════════════════════
// ALERTS (uses active challenge settings)
// ══════════════════════════════════════════════════════════════
case 'get_alerts':
    $ch = getActiveChallenge();
    $chId = $ch['id'] ?? 0;
    $alerts = [];

    $today_pnl_stmt = $db->prepare("SELECT COALESCE(SUM(net_pnl),0) FROM trades WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL) AND trade_date=CURDATE()");
    $today_pnl_stmt->execute([$uid, $chId]); $today = floatval($today_pnl_stmt->fetchColumn());

    $today_trades = $db->prepare("SELECT COUNT(*) FROM trades WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL) AND trade_date=CURDATE()");
    $today_trades->execute([$uid, $chId]); $tc = intval($today_trades->fetchColumn());

    $starting_bal = floatval($ch['starting_balance'] ?? 10000);
    $daily_limit  = floatval($ch['daily_loss_limit'] ?? 500);
    $max_dd_pct   = floatval($ch['max_drawdown_pct'] ?? 10);

    $dd_pct = ($starting_bal > 0) ? abs(min(0, floatval($ch['current_balance'] ?? $starting_bal) - $starting_bal)) / $starting_bal * 100 : 0;
    $daily_pct = ($daily_limit > 0) ? abs(min(0, $today)) / $daily_limit * 100 : 0;

    if ($daily_pct >= 100)     $alerts[] = ['type' => 'danger',  'icon' => '🛑', 'msg' => 'DAILY LOSS LIMIT REACHED — STOP TRADING TODAY'];
    elseif ($daily_pct >= 80)  $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'msg' => 'At ' . round($daily_pct) . '% of daily loss limit — be careful'];
    if ($dd_pct >= $max_dd_pct)       $alerts[] = ['type' => 'danger',  'icon' => '💀', 'msg' => 'MAX DRAWDOWN REACHED — Account at risk'];
    elseif ($dd_pct >= $max_dd_pct * 0.8) $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'msg' => 'Drawdown at ' . round($dd_pct, 1) . '% — approaching limit of ' . $max_dd_pct . '%'];
    if ($tc >= 2) $alerts[] = ['type' => 'info', 'icon' => 'ℹ️', 'msg' => 'You have taken ' . $tc . ' trades today — max recommended is 2'];

    $last3 = $db->prepare("SELECT result FROM trades WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL) AND result IN ('Win','Loss') ORDER BY trade_date DESC, id DESC LIMIT 3");
    $last3->execute([$uid, $chId]); $r3 = $last3->fetchAll();
    if (count($r3) >= 3 && array_sum(array_map(fn($r) => $r['result'] === 'Loss' ? 1 : 0, $r3)) >= 3)
        $alerts[] = ['type' => 'danger', 'icon' => '🚨', 'msg' => '3 consecutive losses — consider stopping for the day'];

    echo json_encode($alerts); break;

// ══════════════════════════════════════════════════════════════
// IMPORT
// ══════════════════════════════════════════════════════════════
case 'import_trades':
    $ch = getActiveChallenge();
    $chId = $ch['id'] ?? null;
    $d = json_decode(file_get_contents('php://input'), true);
    $trades = $d['trades'] ?? [];
    if (count($trades) > 500) {
        echo json_encode(['error' => 'Import limit is 500 trades per batch']);
        break;
    }
    $count = 0;
    foreach ($trades as $t) {
        if (empty($t['trade_date']) || empty($t['pair']) || empty($t['direction'])) continue;
        $pnl  = num($t['pnl'] ?? 0);
        $fees = num($t['fees'] ?? 0);
        $net  = $pnl - $fees;
        $stmt = $db->prepare("INSERT INTO trades (user_id,challenge_id,trade_date,session,pair,direction,entry_price,stop_loss,take_profit,exit_price,lot_size,pnl,fees,net_pnl,r_multiple,result,confidence,exec_score,fib_level,fsa_rules,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$uid, $chId, $t['trade_date'], $t['session'] ?? 'London', $t['pair'], $t['direction'], $t['entry_price'] ?? null, $t['stop_loss'] ?? null, $t['take_profit'] ?? null, $t['exit_price'] ?? null, $t['lot_size'] ?? null, $pnl, $fees, $net, num($t['r_multiple'] ?? 0), $t['result'] ?? null, $t['confidence'] ?? null, $t['exec_score'] ?? null, $t['fib_level'] ?? null, $t['fsa_rules'] ?? null, $t['notes'] ?? null]);
        $count++;
    }
    echo json_encode(['success' => true, 'imported' => $count]); break;

// ══════════════════════════════════════════════════════════════
// STRATEGY TESTS
// ══════════════════════════════════════════════════════════════
case 'get_strategy_trades':
    $s = $db->prepare("SELECT * FROM strategy_tests WHERE user_id=? ORDER BY created_at DESC");
    $s->execute([$uid]); echo json_encode($s->fetchAll()); break;

case 'get_strategy_stats':
    $total = $db->prepare("SELECT COUNT(*) FROM strategy_tests WHERE user_id=?");
    $total->execute([$uid]);
    $wins = $db->prepare("SELECT COUNT(*) FROM strategy_tests WHERE user_id=? AND result='Win'");
    $wins->execute([$uid]);
    $pnl = $db->prepare("SELECT COALESCE(SUM(net_pnl),0) FROM strategy_tests WHERE user_id=?");
    $pnl->execute([$uid]);
    $t = intval($total->fetchColumn());
    $w = intval($wins->fetchColumn());
    echo json_encode(['total' => $t, 'win_rate' => $t > 0 ? round($w / $t * 100, 1) : 0, 'net_pnl' => floatval($pnl->fetchColumn())]); break;

case 'add_strategy_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    $s = $db->prepare("INSERT INTO strategy_tests (user_id,strategy_name,timeframe,market,rule1,rule2,rule3,rule4,rule5,test_date,pair,direction,r1,r2,r3,r4,r5,result,fib_level,r_multiple,net_pnl,session,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([$uid, $d['strategy_name'] ?? '', $d['timeframe'] ?? '', $d['market'] ?? '', $d['rule1'] ?? '', $d['rule2'] ?? '', $d['rule3'] ?? '', $d['rule4'] ?? '', $d['rule5'] ?? '', date('Y-m-d'), $d['pair'] ?? '', $d['direction'] ?? 'Long', $d['r1'] ?? 'N', $d['r2'] ?? 'N', $d['r3'] ?? 'N', $d['r4'] ?? 'N', $d['r5'] ?? 'N', $d['result'] ?? null, $d['fib_level'] ?? null, num($d['r_multiple'] ?? 0), num($d['net_pnl'] ?? 0), $d['session'] ?? null, $d['notes'] ?? null]);
    echo json_encode(['success' => true]); break;

case 'delete_strategy_trade':
    $d = json_decode(file_get_contents('php://input'), true);
    $id = validId($d['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid ID']); break; }
    $db->prepare("DELETE FROM strategy_tests WHERE id=? AND user_id=?")->execute([$id, $uid]);
    echo json_encode(['success' => true]); break;

// ══════════════════════════════════════════════════════════════
// WEEKLY REVIEWS
// ══════════════════════════════════════════════════════════════
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

default:
    echo json_encode(['error' => 'Unknown action']);
}
?>
