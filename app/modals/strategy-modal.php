
<!-- ══ STRATEGY MODAL ══ -->
<div class="modal-overlay" id="strategy-modal">
  <div class="modal">
    <h3>🧪 LOG STRATEGY TEST TRADE</h3>
    <div class="form-grid">
      <div class="form-group"><label>Strategy Name</label><input type="text" id="st-strategy_name" value="Fib + FVG + S/R"></div>
      <div class="form-group"><label>Timeframe</label><input type="text" id="st-timeframe" value="1H/15M"></div>
      <div class="form-group"><label>Market</label><input type="text" id="st-market" value="BTCUSD"></div>
      <div class="form-group full" style="background:var(--bg3);padding:12px;border-radius:8px">
        <label style="color:var(--purple);margin-bottom:8px;display:block">Rules</label>
        <div class="form-grid">
          <div class="form-group"><label>R1</label><input type="text" id="st-rule1" value="Clear Trend"></div>
          <div class="form-group"><label>R2</label><input type="text" id="st-rule2" value="Fib Level"></div>
          <div class="form-group"><label>R3</label><input type="text" id="st-rule3" value="FVG Present"></div>
          <div class="form-group"><label>R4</label><input type="text" id="st-rule4" value="Candle Confirmation"></div>
          <div class="form-group"><label>R5</label><input type="text" id="st-rule5" value="At S/R Level"></div>
        </div>
      </div>
      <div class="form-group"><label>Pair</label><select id="st-pair" class="pair-select"></select></div>
      <div class="form-group"><label>Direction</label><select id="st-direction"><option>Long</option><option>Short</option></select></div>
      <div class="form-group"><label>Session</label><select id="st-session"><option>London</option><option>New York</option><option>Asia</option></select></div>
      <div class="form-group full" style="background:var(--bg3);padding:12px;border-radius:8px">
        <label style="color:var(--text3);margin-bottom:8px;display:block">Rules Met (Y/N)</label>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px">
          <?php foreach([1,2,3,4,5] as $r): ?>
          <div><div style="font-size:10px;color:var(--text3);margin-bottom:3px;text-align:center">R<?= $r ?></div>
          <select id="st-r<?= $r ?>" style="width:100%;background:var(--card);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:7px;font-size:13px">
            <option value="N">N</option><option value="Y">Y</option>
          </select></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group"><label>Result</label><select id="st-result"><option value="">—</option><option>Win</option><option>Loss</option><option>Break Even</option></select></div>
      <div class="form-group"><label>Fib Level</label><select id="st-fib_level"><option value="">—</option><option>0.382</option><option>0.5</option><option>0.618</option><option>0.705</option><option>0.786</option></select></div>
      <div class="form-group"><label>R Multiple</label><input type="number" step="0.01" id="st-r_multiple"></div>
      <div class="form-group"><label>Net P&amp;L ($)</label><input type="number" step="0.01" id="st-net_pnl"></div>
      <div class="form-group full"><label>Notes</label><textarea id="st-notes" rows="2"></textarea></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-ghost" onclick="document.getElementById('strategy-modal').classList.remove('open')">Cancel</button>
      <button class="btn btn-primary" onclick="saveStratTrade()">Save Test</button>
    </div>
  </div>
</div>
