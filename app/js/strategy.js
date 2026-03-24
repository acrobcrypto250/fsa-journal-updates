
/**
 * FundedControl — Strategy Module (v3.0.0 Phase 2)
 * Strategy tester CRUD
 */

async function loadStrategyTrades(){
    stratTrades=await api('get_strategy_trades');
    const stats=await api('get_strategy_stats');
    document.getElementById('st-total').textContent=stats.total;
    document.getElementById('st-wr').textContent=fmtPct(stats.win_rate||0);
    document.getElementById('st-pnl').textContent=fmt(stats.net_pnl||0);
    const tbody=document.getElementById('strategy-tbody');
    if(!stratTrades.length){tbody.innerHTML='<tr><td colspan="12"><div class="empty"><div class="empty-icon">🧪</div><p>No tests yet</p></div></td></tr>';return;}
    tbody.innerHTML=stratTrades.map(t=>{
        const rules=[t.r1,t.r2,t.r3,t.r4,t.r5].filter(r=>r==='Y').length;
        return `<tr>
            <td style="font-size:11px">${t.strategy_name||'—'}</td><td>${t.pair||'—'}</td>
            <td>${t.direction==='Long'?'<span class="badge badge-long">Long</span>':'<span class="badge badge-short">Short</span>'}</td>
            <td>${['r1','r2','r3','r4','r5'].map((r,i)=>`<span style="padding:1px 5px;border-radius:3px;font-size:10px;font-weight:700;background:${t[r]==='Y'?'rgba(0,212,160,0.2)':'rgba(255,77,109,0.2)'};color:${t[r]==='Y'?'var(--green)':'var(--red)'}">R${i+1}</span>`).join(' ')}</td>
            <td style="font-size:11px;color:${rules===5?'var(--green)':'var(--orange)'}">${rules}/5${rules===5?' ✅':''}</td>
            <td>${resultBadge(t.result)}</td><td style="color:var(--purple)">${t.fib_level||'—'}</td>
            <td style="font-family:var(--font-head);font-size:11px;color:${parseFloat(t.r_multiple||0)>=0?'var(--green)':'var(--red)'}">${t.r_multiple?t.r_multiple+'R':'—'}</td>
            <td><span class="${pnlCls(t.net_pnl||0)}">${fmt(t.net_pnl||0)}</span></td>
            <td>${t.session||'—'}</td><td style="font-size:11px;color:var(--text3);max-width:120px;overflow:hidden;text-overflow:ellipsis">${t.notes||'—'}</td>
            <td><button class="btn btn-danger btn-sm" onclick="deleteStratTrade(${t.id})">🗑</button></td>
        </tr>`;
    }).join('');
}

async function deleteStratTrade(id){if(!confirm('Delete?'))return;await api('delete_strategy_trade','POST',{id});toast('Deleted');loadStrategyTrades();}

async function saveStratTrade(){
    const data={};
    ['strategy_name','timeframe','market','rule1','rule2','rule3','rule4','rule5','pair','direction','r1','r2','r3','r4','r5','result','fib_level','r_multiple','net_pnl','session','notes'].forEach(k=>{ data[k]=document.getElementById('st-'+k)?.value||null; });
    await api('add_strategy_trade','POST',data);
    toast('Test saved! ✅');
    document.getElementById('strategy-modal').classList.remove('open');
    loadStrategyTrades();
}
