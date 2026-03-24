
<!-- ══ PAIRS MODAL ══ -->
<div class="modal-overlay" id="pairs-modal">
  <div class="modal" style="max-width:400px">
    <h3>⚙️ MANAGE PAIRS</h3>
    <div id="pairs-list" style="margin-bottom:14px"></div>
    <div style="display:flex;gap:8px">
      <input type="text" id="new-pair-input" placeholder="e.g. SOLUSDT" style="flex:1" onkeydown="if(event.key==='Enter')addPair()">
      <button class="btn btn-success" onclick="addPair()">Add</button>
    </div>
    <div class="form-actions"><button class="btn btn-ghost" onclick="document.getElementById('pairs-modal').classList.remove('open')">Close</button></div>
  </div>
</div>
