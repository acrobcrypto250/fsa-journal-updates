
<?php
/**
 * FundedControl — Strategy Controller
 * Handles: get_strategy_trades, get_strategy_stats, add_strategy_trade, delete_strategy_trade
 */
class StrategyController {
    private $db;
    private $uid;

    public function __construct() {
        $this->db = getDB();
        $this->uid = uid();
    }

    public function getAll() {
        $s = $this->db->prepare("SELECT * FROM strategy_tests WHERE user_id=? ORDER BY created_at DESC");
        $s->execute([$this->uid]);
        jsonResponse($s->fetchAll());
    }

    public function getStats() {
        $total = $this->db->prepare("SELECT COUNT(*) FROM strategy_tests WHERE user_id=?");
        $total->execute([$this->uid]);
        $wins = $this->db->prepare("SELECT COUNT(*) FROM strategy_tests WHERE user_id=? AND result='Win'");
        $wins->execute([$this->uid]);
        $pnl = $this->db->prepare("SELECT COALESCE(SUM(net_pnl),0) FROM strategy_tests WHERE user_id=?");
        $pnl->execute([$this->uid]);
        $t = intval($total->fetchColumn());
        $w = intval($wins->fetchColumn());
        jsonResponse(['total' => $t, 'win_rate' => $t > 0 ? round($w / $t * 100, 1) : 0, 'net_pnl' => floatval($pnl->fetchColumn())]);
    }

    public function add() {
        $d = jsonInput();
        $this->db->prepare("INSERT INTO strategy_tests (user_id,strategy_name,timeframe,market,rule1,rule2,rule3,rule4,rule5,test_date,pair,direction,r1,r2,r3,r4,r5,result,fib_level,r_multiple,net_pnl,session,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$this->uid, $d['strategy_name'] ?? '', $d['timeframe'] ?? '', $d['market'] ?? '', $d['rule1'] ?? '', $d['rule2'] ?? '', $d['rule3'] ?? '', $d['rule4'] ?? '', $d['rule5'] ?? '', date('Y-m-d'), $d['pair'] ?? '', $d['direction'] ?? 'Long', $d['r1'] ?? 'N', $d['r2'] ?? 'N', $d['r3'] ?? 'N', $d['r4'] ?? 'N', $d['r5'] ?? 'N', $d['result'] ?? null, $d['fib_level'] ?? null, num($d['r_multiple'] ?? 0), num($d['net_pnl'] ?? 0), $d['session'] ?? null, $d['notes'] ?? null]);
        jsonResponse(['success' => true]);
    }

    public function delete() {
        $d = jsonInput();
        $id = validId($d['id'] ?? 0);
        if (!$id) jsonError('Invalid ID');
        $this->db->prepare("DELETE FROM strategy_tests WHERE id=? AND user_id=?")->execute([$id, $this->uid]);
        jsonResponse(['success' => true]);
    }
}
