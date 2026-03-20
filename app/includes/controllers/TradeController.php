
<?php
/**
 * FundedControl — Trade Controller
 * Handles: get_trades, add_trade, update_trade, delete_trade
 * All trades scoped to active challenge.
 */
class TradeController {
    private $db;
    private $uid;

    public function __construct() {
        $this->db = getDB();
        $this->uid = uid();
    }

    public function getAll() {
        $ch = getActiveChallenge();
        $chId = $ch['id'] ?? 0;
        $where = "WHERE user_id=? AND (challenge_id=? OR challenge_id IS NULL)";
        $params = [$this->uid, $chId];
        if (!empty($_GET['pair']))   { $where .= " AND pair=?";       $params[] = $_GET['pair']; }
        if (!empty($_GET['result'])) { $where .= " AND result=?";     $params[] = $_GET['result']; }
        if (!empty($_GET['from']))   { $where .= " AND trade_date>=?"; $params[] = $_GET['from']; }
        if (!empty($_GET['to']))     { $where .= " AND trade_date<=?"; $params[] = $_GET['to']; }
        $s = $this->db->prepare("SELECT * FROM trades $where ORDER BY trade_date DESC, id DESC");
        $s->execute($params);
        jsonResponse($s->fetchAll());
    }

    public function add()    { $this->saveTrade(false); }
    public function update() { $this->saveTrade(true); }

    private function saveTrade($isUpdate) {
        $ch = getActiveChallenge();
        $chId = $ch['id'] ?? null;

        $isForm = !empty($_FILES) || !empty($_POST);
        $d = $isForm ? $_POST : jsonInput();

        $entry_price = num($d['entry_price'] ?? null, null);
        $stop_loss   = num($d['stop_loss'] ?? null, null);
        $exit_price  = num($d['exit_price'] ?? null, null);
        $lot_size    = num($d['lot_size'] ?? null, null);
        $fees        = num($d['fees'] ?? 0);

        // Calculate P&L
        $pnl = 0;
        if ($exit_price && $entry_price && $lot_size) {
            $pnl = ($d['direction'] ?? '') === 'Long'
                ? ($exit_price - $entry_price) * $lot_size
                : ($entry_price - $exit_price) * $lot_size;
        }
        $net = $pnl - $fees;

        // Calculate R-multiple
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

        // Handle screenshot
        $screenshot = isset($d['screenshot']) && $d['screenshot'] ? $d['screenshot'] : null;
        $upload_result = handleScreenshot($this->uid);
        if ($upload_result === false) jsonError('Screenshot upload failed — max 5MB, images only');
        if ($upload_result !== null) $screenshot = $upload_result;

        $cols = ['trade_date','session','time_in','time_out','pair','direction','entry_price','stop_loss','take_profit','exit_price','lot_size','risk_amount','fees','result','confidence','exec_score','fib_level','fsa_rules','notes'];

        if ($isUpdate) {
            $trade_id = validId($d['id'] ?? 0);
            if (!$trade_id) jsonError('Invalid trade ID');
            $update_vals = array_map(fn($k) => ($d[$k] ?? null) ?: null, $cols);
            $update_vals[] = round($pnl, 4);
            $update_vals[] = round($net, 4);
            $update_vals[] = $r;
            $update_vals[] = $screenshot;
            $update_vals[] = $trade_id;
            $update_vals[] = $this->uid;
            $sets = implode(',', array_map(fn($c) => "$c=?", $cols));
            $sets .= ",pnl=?,net_pnl=?,r_multiple=?,screenshot=?";
            $this->db->prepare("UPDATE trades SET $sets WHERE id=? AND user_id=?")->execute($update_vals);
        } else {
            $vals = array_map(fn($k) => ($d[$k] ?? null) ?: null, $cols);
            $vals = array_merge([$this->uid, $chId], $vals, [round($pnl, 4), round($net, 4), $r, $screenshot]);
            $ph = implode(',', array_fill(0, count($cols) + 4, '?'));
            $allcols = implode(',', $cols) . ",pnl,net_pnl,r_multiple,screenshot";
            $this->db->prepare("INSERT INTO trades (user_id,challenge_id,$allcols) VALUES (?,?,{$ph})")->execute($vals);
        }

        // Update daily limits
        $dl_date = $d['trade_date'] ?? date('Y-m-d');
        $this->db->prepare("INSERT INTO daily_limits (user_id,log_date,daily_pnl,trades_count) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE daily_pnl=daily_pnl+?,trades_count=trades_count+1")
            ->execute([$this->uid, $dl_date, round($net, 4), round($net, 4)]);

        jsonResponse(['success' => true, 'id' => $this->db->lastInsertId()]);
    }

    public function delete() {
        $d = jsonInput();
        $trade_id = validId($d['id'] ?? 0);
        if (!$trade_id) jsonError('Invalid trade ID');

        $s = $this->db->prepare("SELECT screenshot FROM trades WHERE id=? AND user_id=?");
        $s->execute([$trade_id, $this->uid]);
        $t = $s->fetch();
        if ($t && $t['screenshot']) {
            $filepath = safeMediaDir($this->uid) . basename($t['screenshot']);
            if (file_exists($filepath)) unlink($filepath);
        }
        $this->db->prepare("DELETE FROM trades WHERE id=? AND user_id=?")->execute([$trade_id, $this->uid]);
        jsonResponse(['success' => true]);
    }
}
