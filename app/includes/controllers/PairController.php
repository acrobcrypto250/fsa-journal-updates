<?php
/**
 * FundedControl — Pair Controller
 * Handles: get_pairs, add_pair, delete_pair
 */
class PairController {
    private $db;
    private $uid;

    public function __construct() {
        $this->db = getDB();
        $this->uid = uid();
    }

    public function getAll() {
        $s = $this->db->prepare("SELECT * FROM pairs WHERE user_id=? AND active=1 ORDER BY symbol");
        $s->execute([$this->uid]);
        jsonResponse($s->fetchAll());
    }

    public function add() {
        $d = jsonInput();
        $sym = strtoupper(trim($d['symbol'] ?? ''));
        if (!$sym || strlen($sym) > 20) jsonError('Valid symbol required');
        if (!preg_match('/^[A-Z0-9\/\.\-_]+$/', $sym)) jsonError('Invalid symbol format');
        $check = $this->db->prepare("SELECT id FROM pairs WHERE user_id=? AND symbol=?");
        $check->execute([$this->uid, $sym]);
        if ($check->fetch()) jsonError('Pair already exists');
        $this->db->prepare("INSERT INTO pairs (user_id,symbol) VALUES (?,?)")->execute([$this->uid, $sym]);
        jsonResponse(['success' => true, 'id' => $this->db->lastInsertId()]);
    }

    public function delete() {
        $d = jsonInput();
        $id = validId($d['id'] ?? 0);
        if (!$id) jsonError('Invalid pair ID');
        $this->db->prepare("UPDATE pairs SET active=0 WHERE id=? AND user_id=?")->execute([$id, $this->uid]);
        jsonResponse(['success' => true]);
    }
}
