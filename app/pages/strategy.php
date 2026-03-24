
<!-- ── STRATEGY TESTER ── -->
<div class="page" id="page-strategy">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div class="card" style="padding:12px 18px;display:inline-flex;gap:20px;align-items:center">
      <div><div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:1px">Tests</div><div style="font-family:var(--font-head);font-size:20px;color:var(--blue2)" id="st-total">0</div></div>
      <div><div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:1px">Win Rate</div><div style="font-family:var(--font-head);font-size:20px;color:var(--green)" id="st-wr">0%</div></div>
      <div><div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:1px">Net P&amp;L</div><div style="font-family:var(--font-head);font-size:20px" id="st-pnl">$0</div></div>
    </div>
    <button class="btn btn-success" onclick="document.getElementById('strategy-modal').classList.add('open')">+ Log Test Trade</button>
  </div>
  <div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Strategy</th><th>Pair</th><th>Dir</th><th>Rules</th><th>Score</th><th>Result</th><th>Fib</th><th>R</th><th>P&amp;L</th><th>Session</th><th>Notes</th><th></th></tr></thead>
    <tbody id="strategy-tbody"></tbody>
  </table></div></div>
</div>
