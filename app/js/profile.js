
/**
 * FundedControl — Profile Module (v3.0.0 Phase 2)
 * Profile settings CRUD
 */

async function loadProfile(){
    const u=await api('get_user');
    currentUser=u;
    document.getElementById('prof-display_name').value=u.display_name||'';
    document.getElementById('prof-avatar_color').value=u.avatar_color||'#4f7cff';
    document.getElementById('prof-bio').value=u.bio||'';
    // Preview
    const av=document.getElementById('profile-avatar-preview');
    if(av){av.style.background=u.avatar_color||'#4f7cff';av.textContent=(u.display_name||u.username||'U')[0].toUpperCase();}
    document.getElementById('profile-name-preview').textContent=u.display_name||u.username||'Trader';
    document.getElementById('profile-bio-preview').textContent=u.bio||'No bio yet';
}

async function saveProfile(){
    const data={
        display_name: document.getElementById('prof-display_name')?.value||null,
        avatar_color: document.getElementById('prof-avatar_color')?.value||'#4f7cff',
        bio: document.getElementById('prof-bio')?.value||'',
    };
    const cp=document.getElementById('prof-current_password')?.value;
    const np=document.getElementById('prof-new_password')?.value;
    if(np){
        if(!cp){toast('Enter current password to change it','error');return;}
        data.current_password=cp;
        data.new_password=np;
    }
    const r=await api('update_profile','POST',data);
    if(r.error){toast(r.error,'error');return;}
    currentUser={...currentUser,...data};
    // Update sidebar
    const av=document.getElementById('sidebar-avatar');
    if(av){av.style.background=data.avatar_color;av.textContent=(data.display_name||'U')[0].toUpperCase();}
    const un=document.getElementById('sidebar-username');
    if(un) un.textContent=data.display_name||currentUser.username;
    // Clear password fields
    document.getElementById('prof-current_password').value='';
    document.getElementById('prof-new_password').value='';
    toast('Profile saved! ✅');
    loadProfile();
}
