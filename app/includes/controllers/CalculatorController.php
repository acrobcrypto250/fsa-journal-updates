<?php
/**
 * FundedControl — Calculator Controller
 * Handles: calculate_risk
 */
class CalculatorController {
    public function calculate() {
        $d = jsonInput();
        $ch = getActiveChallenge();
        $balance  = num($d['balance'] ?? $ch['current_balance'] ?? 10000);
        $risk_pct = num($d['risk_pct'] ?? $ch['risk_per_trade_pct'] ?? 0.5);
        $entry    = num($d['entry'] ?? 0);
        $sl       = num($d['sl'] ?? 0);
        if ($entry <= 0 || $sl <= 0 || $entry == $sl) jsonError('Invalid prices');
        $risk_amt = $balance * $risk_pct / 100;
        $sl_dist  = abs($entry - $sl);
        $lot_size = $risk_amt / $sl_dist;
        $tp = num($d['tp'] ?? 0);
        $rr = ($tp > 0 && $sl_dist > 0) ? abs($tp - $entry) / $sl_dist : 0;
        $potential_profit = $tp > 0 ? $lot_size * abs($tp - $entry) : 0;
        jsonResponse([
            'risk_amount' => round($risk_amt, 2), 'lot_size' => round($lot_size, 4),
            'sl_distance' => round($sl_dist, 2), 'rr_ratio' => round($rr, 2),
            'potential_profit' => round($potential_profit, 2), 'risk_pct' => $risk_pct
        ]);
    }
}
