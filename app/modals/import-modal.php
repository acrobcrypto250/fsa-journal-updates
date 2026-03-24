
<!-- ══ IMPORT MODAL ══ -->
<div class="modal-overlay" id="import-modal">
  <div class="modal" style="max-width:460px">
    <h3>📥 IMPORT FROM EXCEL</h3>
    <p style="color:var(--text2);font-size:13px;margin-bottom:16px">Upload your Trading Journal Excel file. The app will read the Trade Log sheet and import all trades to your active challenge.</p>
    <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:var(--text3)">
      <strong style="color:var(--text2)">Expected columns:</strong> Date, Session, Pair, Direction, Entry, Stop Loss, Take Profit, Exit Price, Lot Size, P&L $, Fees $, R Multiple, Result, Confidence, Fib Level, Notes
    </div>
    <div class="form-group"><label>Select Excel File (.xlsx)</label><input type="file" id="import-file" accept=".xlsx,.xls,.csv" style="padding:8px"></div>
    <div class="form-actions">
      <button class="btn btn-ghost" onclick="document.getElementById('import-modal').classList.remove('open')">Cancel</button>
      <button class="btn btn-primary" onclick="importExcel()">Import Trades</button>
    </div>
  </div>
</div>
