<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// -------- Handle requests -------- //
function send_toast($message, $success = true) {
    $_SESSION['toast_message'] = $message;
    $_SESSION['toast_success'] = $success;
}

// Delete group
if (isset($_POST['action']) && $_POST['action'] === 'delete_group' && isset($_POST['save_group_id'])) {
    $delete_id = intval($_POST['save_group_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM `groups` WHERE id = ? AND created_by = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $delete_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, "DELETE FROM group_members WHERE group_id = " . intval($delete_id));
    send_toast('Groep succesvol verwijderd.');
    header('Location: groups.php?tab=manage'); exit;
}

// Remove member
if ((isset($_GET['remove_member']) && isset($_GET['group_id'])) || 
    (isset($_POST['action']) && $_POST['action']==='remove_member' && isset($_POST['remove_member_id']))) {
    if (isset($_GET['remove_member'])) {
        $member_id = intval($_GET['remove_member']);
        $group_id_r = intval($_GET['group_id']);
    } else {
        $member_id = intval($_POST['remove_member_id']);
        $group_id_r = intval($_POST['remove_group_id']);
    }
    $chk = mysqli_query($conn, "SELECT 1 FROM `groups` WHERE id = $group_id_r AND created_by = $user_id LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM group_members WHERE id = ? AND group_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $member_id, $group_id_r);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        send_toast('Lid succesvol verwijderd.');
    }
    header('Location: groups.php'); exit;
}

// Create group
if (isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $group_name = trim(mysqli_real_escape_string($conn, $_POST['group_name'] ?? ''));
    $leden = $_POST['leden'] ?? [];
    if ($group_name !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, created_by) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'si', $group_name, $user_id);
        mysqli_stmt_execute($stmt);
        $group_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $added = 0; $not_found = [];
        foreach ($leden as $email) {
            $email = trim(mysqli_real_escape_string($conn, $email));
            if ($email === '') continue;
            $uQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
            if ($uQ && mysqli_num_rows($uQ) > 0) {
                $uRow = mysqli_fetch_assoc($uQ);
                $member_id = intval($uRow['id']);
                $exists = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $group_id AND user_id = $member_id");
                if ($exists && mysqli_num_rows($exists) === 0) {
                    $ins = mysqli_prepare($conn, "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($ins, 'ii', $group_id, $member_id);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                    $added++;
                }
            } else $not_found[] = $email;
        }
        send_toast("Groep aangemaakt! ($added leden toegevoegd)" . ($not_found ? " Niet gevonden: " . implode(', ', $not_found) : ''));
    }
    header('Location: groups.php'); exit;
}

// Save edits for a group
if (isset($_POST['action']) && $_POST['action'] === 'save_group' && isset($_POST['save_group_id'])) {
    $save_group_id = intval($_POST['save_group_id']);
    $chk = mysqli_query($conn, "SELECT 1 FROM `groups` WHERE id = $save_group_id AND created_by = $user_id LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $new_name = trim(mysqli_real_escape_string($conn, $_POST['group_name_edit'] ?? ''));
        if ($new_name !== '') {
            $stmt = mysqli_prepare($conn, "UPDATE `groups` SET name = ? WHERE id = ? AND created_by = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $new_name, $save_group_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $submitted_member_ids = [];
        $member_ids = $_POST['member_ids'] ?? [];
        $member_emails = $_POST['member_emails'] ?? [];
        
        if (is_array($member_ids) && is_array($member_emails)) {
            foreach ($member_ids as $idx => $m_id_raw) {
                $m_id = intval($m_id_raw);
                $email = trim(mysqli_real_escape_string($conn, $member_emails[$idx] ?? ''));
                if ($m_id <= 0 || $email === '') continue;
                
                $submitted_member_ids[] = $m_id;
                
                $uQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
                if ($uQ && mysqli_num_rows($uQ) > 0) {
                    $uRow = mysqli_fetch_assoc($uQ);
                    $new_user_id = intval($uRow['id']);
                    $curQ = mysqli_query($conn, "SELECT user_id FROM group_members WHERE id = $m_id AND group_id = $save_group_id LIMIT 1");
                    if ($curQ && mysqli_num_rows($curQ) > 0) {
                        $cur = mysqli_fetch_assoc($curQ);
                        $cur_user_id = intval($cur['user_id']);
                        if ($cur_user_id !== $new_user_id) {
                            $dup = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $save_group_id AND user_id = $new_user_id");
                            if ($dup && mysqli_num_rows($dup) === 0) {
                                $up = mysqli_prepare($conn, "UPDATE group_members SET user_id = ? WHERE id = ? AND group_id = ?");
                                mysqli_stmt_bind_param($up, 'iii', $new_user_id, $m_id, $save_group_id);
                                mysqli_stmt_execute($up);
                                mysqli_stmt_close($up);
                            } else {
                                $del = mysqli_prepare($conn, "DELETE FROM group_members WHERE id = ? AND group_id = ?");
                                mysqli_stmt_bind_param($del, 'ii', $m_id, $save_group_id);
                                mysqli_stmt_execute($del);
                                mysqli_stmt_close($del);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($submitted_member_ids)) {
            $ids_str = implode(',', array_map('intval', $submitted_member_ids));
            mysqli_query($conn, "DELETE FROM group_members WHERE group_id = $save_group_id AND id NOT IN ($ids_str)");
        } else {
            // If no members submitted, delete all existing members
            mysqli_query($conn, "DELETE FROM group_members WHERE group_id = $save_group_id");
        }

        $new_members = $_POST['new_members'] ?? [];
        $added = 0; $not_found = [];
        if (is_array($new_members)) {
            foreach ($new_members as $email) {
                $email = trim(mysqli_real_escape_string($conn, $email));
                if ($email === '') continue;
                $uQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");
                if ($uQ && mysqli_num_rows($uQ) > 0) {
                    $uRow = mysqli_fetch_assoc($uQ);
                    $member_id = intval($uRow['id']);
                    $exists = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $save_group_id AND user_id = $member_id");
                    if ($exists && mysqli_num_rows($exists) === 0) {
                        $ins = mysqli_prepare($conn, "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                        mysqli_stmt_bind_param($ins, 'ii', $save_group_id, $member_id);
                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);
                        $added++;
                    }
                } else $not_found[] = $email;
            }
        }
        send_toast("Wijzigingen opgeslagen! ($added nieuwe leden toegevoegd)" . ($not_found ? " Niet gevonden: " . implode(', ', $not_found) : ''));
    }
    header('Location: groups.php?tab=manage&group=' . $save_group_id); exit;
}

// Load groups & members
$groups = [];
$res = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE created_by = $user_id ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $leden = [];
    $ledenQ = mysqli_query($conn, "SELECT gm.id as gm_id, u.id as user_id, u.email FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ".intval($row['id'])." ORDER BY u.email ASC");
    while ($l = mysqli_fetch_assoc($ledenQ)) $leden[] = $l;
    $row['leden'] = $leden;
    $groups[] = $row;
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Groepen - Beheer</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
*{box-sizing:border-box;} 
body{font-family:Inter,sans-serif;background:#f5f7fb;margin:0;padding:0;color:#1f2937;} 
.auth-container{max-width:1100px;margin:2.5em auto;padding:1.2em;} 
.header{text-align:center;margin-bottom:1.2em;} 
h2{color:#0f63d4;margin:0;font-size:1.6rem;font-weight:700;}
.tab-btns{display:flex;gap:0.8em;justify-content:center;margin:1.2em 0;}
.tab-btns button{background:#eef6ff;color:#0f63d4;border:none;padding:0.7em 1.6em;border-radius:999px;font-weight:700;cursor:pointer;transition:all .15s;}
.tab-btns button.active{background:#0f63d4;color:#fff;box-shadow:0 6px 20px rgba(15,99,212,0.12);}
.tab-content{display:none;}
.tab-content.active{display:block;}
.grid{display:grid;grid-template-columns:320px 1fr;gap:1.2rem;align-items:start;}
.groups-list{display:flex;flex-direction:column;gap:0.75rem;}
.group-item{background:#fff;border-radius:12px;padding:0.9rem 1rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:0.6rem;box-shadow:0 3px 10px rgba(15,99,212,0.06);transition:transform .12s,box-shadow .12s;border:1px solid transparent;}
.group-item:hover{transform:translateY(-3px);box-shadow:0 8px 22px rgba(15,99,212,0.08);}
.group-item.active{background:linear-gradient(180deg,#ffffff,#f3f8ff);border-color:rgba(15,99,212,0.08);box-shadow:0 10px 30px rgba(15,99,212,0.10);}
.group-item .meta{font-size:0.95rem;color:#0f63d4;font-weight:600;}
.group-item .count{font-size:0.85rem;color:#6b7280;}
.panel{background:#fff;border-radius:12px;padding:1.2rem;box-shadow:0 6px 20px rgba(15,99,212,0.04);min-height:320px;}
.placeholder{color:#6b7280;text-align:center;padding:3rem 1rem;}
.form-row{display:flex;flex-direction:column;margin-bottom:0.8rem;}
.label{font-weight:600;color:#0f63d4;margin-bottom:0.35rem;}
.input{width:100%;padding:0.7rem 0.9rem;border-radius:10px;border:1.8px solid #e6eefc;background:#f8fbff;font-size:0.95rem;}
.input:focus{outline:none;border-color:#0f63d4;box-shadow:0 6px 18px rgba(15,99,212,0.06);background:#fff;}
.members-list,.new-members{display:flex;flex-direction:column;gap:0.5rem;margin:0.25rem 0 1rem 0;}
/* Perfect vertical alignment - ensuring input and button have identical heights and box models */
.member-row{display:flex;gap:0.5rem;align-items:center;width:100%;}
.member-email{
  flex:1;
  min-width:0;
  height:44px;
  padding:0 0.8rem !important;
  border-radius:8px;
  background:#f6f9ff;
  border:2px solid #e6eefc !important;
  font-size:0.95rem;
  line-height:44px;
  box-sizing:border-box;
}
.member-email:focus{border-color:#0f63d4 !important;background:#fff;outline:none;}
/* Modern, professional button styling with exact same height as input */
.small-btn{
  width:44px;
  height:44px;
  min-width:44px;
  flex-shrink:0;
  border-radius:10px;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:1.4rem;
  line-height:1;
  font-weight:400;
  transition:all .2s;
  margin:0;
  padding:0;
  background:#fff;
  border:2px solid #e5e7eb;
  color:#6b7280;
  box-sizing:border-box;
  vertical-align:middle;
  /* Increased upward shift from -8px to -9px for perfect alignment */
  transform:translateY(-9px);
}
.small-btn:hover{
  border-color:#9ca3af;
  color:#374151;
  /* Adjusted hover transform to -10px to maintain upward shift */
  transform:translateY(-10px);
  box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
.add-btn{border-color:#0f63d4;color:#0f63d4;background:#f0f7ff;}
.add-btn:hover{border-color:#0a4fa8;color:#0a4fa8;background:#e6f2ff;box-shadow:0 4px 12px rgba(15,99,212,0.15);}
.remove-btn{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.remove-btn:hover{border-color:#dc2626;color:#dc2626;background:#fee2e2;box-shadow:0 4px 12px rgba(239,68,68,0.15);}
.small-btn:active{
  /* Adjusted active transform to -8px to maintain upward shift */
  transform:translateY(-8px) scale(.96);
}
.btn-container{display:flex;justify-content:center;margin-top:0.6rem;}
.actions{display:flex;gap:0.6rem;justify-content:flex-end;margin-top:1rem;}
.btn{padding:0.7rem 1.1rem;border-radius:10px;border:none;cursor:pointer;font-weight:700;}
.save-btn{background:#0f63d4;color:#fff;}
.danger-btn{background:#ef4444;color:#fff;}
.toast{position:fixed;top:20px;right:20px;background:#0f63d4;color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 6px 20px rgba(15,99,212,0.15);opacity:0;pointer-events:none;transition:opacity .3s,transform .3s;}
.toast.show{opacity:1;transform:translateY(0);}
.toast.hide{opacity:0;transform:translateY(-10px);}
@media(max-width:880px){.grid{grid-template-columns:1fr;}.groups-list{flex-direction:row;overflow:auto;padding-bottom:0.5rem;}.group-item{min-width:200px;flex:0 0 auto;}.actions{justify-content:stretch;flex-direction:column;}}
/* Added custom confirmation modal styling */
.modal-overlay{
  display:none;
  position:fixed;
  top:0;
  left:0;
  right:0;
  bottom:0;
  background:rgba(0,0,0,0.5);
  z-index:1000;
  align-items:center;
  justify-content:center;
}
.modal-overlay.show{display:flex;}
.modal{
  background:#fff;
  border-radius:16px;
  padding:2rem;
  max-width:420px;
  width:90%;
  box-shadow:0 20px 60px rgba(0,0,0,0.3);
  animation:modalSlideIn .2s ease-out;
}
@keyframes modalSlideIn{
  from{transform:translateY(-20px);opacity:0;}
  to{transform:translateY(0);opacity:1;}
}
.modal-title{
  font-size:1.3rem;
  font-weight:700;
  color:#1f2937;
  margin:0 0 0.5rem 0;
}
.modal-message{
  color:#6b7280;
  margin:0 0 1.5rem 0;
  line-height:1.5;
}
.modal-actions{
  display:flex;
  gap:0.75rem;
  justify-content:flex-end;
}
.modal-btn{
  padding:0.65rem 1.3rem;
  border-radius:8px;
  border:none;
  cursor:pointer;
  font-weight:600;
  transition:all .15s;
}
.modal-btn-cancel{
  background:#f3f4f6;
  color:#6b7280;
}
.modal-btn-cancel:hover{
  background:#e5e7eb;
  color:#374151;
}
.modal-btn-confirm{
  background:#ef4444;
  color:#fff;
}
.modal-btn-confirm:hover{
  background:#dc2626;
}
</style>
</head>
<body>
<main class="auth-container">
  <div class="header">
    <h2>Groepen beheren</h2>
    <div style="color:#6b7280;margin-top:6px;font-size:0.95rem">Klik op een groep links om details te bekijken en bewerken.</div>
  </div>

  <div class="tab-btns" role="tablist" aria-label="tabs">
    <button id="tab-create" class="active" onclick="showTab('create')">Nieuwe groep</button>
    <button id="tab-manage" onclick="showTab('manage')">Groepen beheren</button>
  </div>

  <div id="pane-create" class="tab-content active">
    <div class="panel" style="max-width:820px;margin:0 auto;">
      <form method="post" onsubmit="return validateCreateForm()">
        <input type="hidden" name="action" value="create_group">
        <div>
          <label class="label">Groepsnaam</label>
          <input class="input" type="text" name="group_name" placeholder="Bijv. Klas 1A - Projectgroep" required>
        </div>

        <div style="margin-top:12px;">
          <label class="label">Leden toevoegen (e-mailadressen)</label>
          <div id="create-members" class="new-members">
            <div class="member-row">
              <input class="member-email input" type="email" name="leden[]" placeholder="e-mailadres (bijv. r.jansen@...)" required>
            </div>
          </div>
          <div class="btn-container">
            <button type="button" class="small-btn add-btn" onclick="create_addRow()">+</button>
          </div>
          <div style="color:#6b7280;font-size:0.9rem;margin-top:6px;">Alleen bestaande gebruikers worden toegevoegd aan de groep.</div>
        </div>

        <div class="actions">
          <button type="submit" class="btn save-btn">Maak groep</button>
        </div>
      </form>
    </div>
  </div>

  <div id="pane-manage" class="tab-content" style="margin-top:1.2rem;">
    <div class="grid">
      <div>
        <div class="groups-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
          <div style="font-weight:700;color:#0f63d4">Mijn groepen</div>
          <div style="color:#6b7280;font-size:0.9rem"><?= count($groups) ?> groepen</div>
        </div>
        <div class="groups-list">
          <?php foreach($groups as $g): ?>
            <div class="group-item" data-group-id="<?= intval($g['id']) ?>" onclick="selectGroup(<?= intval($g['id']) ?>, this)">
              <div class="meta"><?= htmlspecialchars($g['name']) ?></div>
              <div class="count"><?= count($g['leden']) ?> leden</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel" id="group-detail">
        <div class="placeholder">Selecteer een groep om de leden te bekijken en bewerken.</div>
      </div>
    </div>
  </div>
</main>

<!-- Added custom confirmation modal -->
<div id="confirmModal" class="modal-overlay">
  <div class="modal">
    <h3 class="modal-title">Groep verwijderen</h3>
    <p class="modal-message">Weet je zeker dat je deze groep wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.</p>
    <div class="modal-actions">
      <button type="button" class="modal-btn modal-btn-cancel" onclick="hideConfirmModal()">Annuleren</button>
      <button type="button" class="modal-btn modal-btn-confirm" onclick="confirmDelete()">Verwijderen</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
// Tabs
function showTab(tab) {
  document.querySelectorAll('.tab-btns button').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(p=>p.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  document.getElementById('pane-'+tab).classList.add('active');
}

// Create form add row
function create_addRow(){
  const container = document.getElementById('create-members');
  const row = document.createElement('div'); row.className='member-row';
  row.innerHTML='<input class="member-email input" type="email" name="leden[]" placeholder="e-mailadres"><button type="button" class="small-btn remove-btn" onclick="removeRow(this)">−</button>';
  container.appendChild(row);
}

function validateCreateForm(){return true;}

// Toast
function showToast(msg){
  const t=document.getElementById('toast');
  t.innerText=msg; t.classList.add('show'); t.classList.remove('hide');
  setTimeout(()=>{t.classList.add('hide'); t.classList.remove('show')},3500);
}

// Display PHP toast
<?php if(isset($_SESSION['toast_message'])): ?>
showToast("<?= addslashes($_SESSION['toast_message']) ?>");
<?php unset($_SESSION['toast_message'], $_SESSION['toast_success']); endif; ?>

// Manage groups
let selectedGroupId=null;
function selectGroup(groupId, el){
  selectedGroupId=groupId;
  document.querySelectorAll('.group-item').forEach(g=>g.classList.remove('active'));
  el.classList.add('active');
  const groupData=<?= json_encode($groups) ?>.find(g=>g.id==groupId);
  if(!groupData){document.getElementById('group-detail').innerHTML='<div class="placeholder">Geen data.</div>';return;}
  let html='<form method="post" id="editGroupForm">';
  html+='<input type="hidden" name="action" value="save_group">';
  html+='<input type="hidden" name="save_group_id" value="'+groupId+'">';
  html+='<div><label class="label">Groepsnaam</label><input class="input" name="group_name_edit" value="'+groupData.name+'"></div>';
  html+='<div style="margin-top:12px;"><label class="label">Leden</label><div class="members-list">';
  groupData.leden.forEach((l)=>{
    html+='<div class="member-row"><input type="hidden" name="member_ids[]" value="'+l.gm_id+'">';
    html+='<input class="member-email input" name="member_emails[]" value="'+l.email+'">';
    html+='<button type="button" class="small-btn remove-btn" onclick="removeRow(this)">−</button></div>';
  });
  html+='</div><div class="new-members"></div>';
  html+='<div class="btn-container"><button type="button" class="small-btn add-btn" onclick="addNewMemberRow(this)">+</button></div>';
  html+='<div style="color:#6b7280;font-size:0.9rem;margin-top:6px;">Alleen bestaande gebruikers worden toegevoegd aan de groep.</div>';
  html+='<div class="actions" style="margin-top:0.8rem;">';
  html+='<button type="submit" class="btn save-btn">Opslaan</button> ';
  html+='<button type="button" class="btn danger-btn" onclick="showConfirmModal(document.getElementById(\'editGroupForm\'));document.querySelector(\'#editGroupForm input[name=action]\').value=\'delete_group\';">Verwijder groep</button>';
  html+='</div></form>';
  document.getElementById('group-detail').innerHTML=html;
}

function removeRow(btn){btn.parentNode.remove();}
function addNewMemberRow(btn){
  const form = btn.closest('form');
  const container = form.querySelector('.new-members');
  const row=document.createElement('div'); row.className='member-row';
  row.innerHTML='<input class="member-email input" type="email" name="new_members[]" placeholder="e-mailadres"><button type="button" class="small-btn remove-btn" onclick="removeRow(this)">−</button>';
  container.appendChild(row);
}

window.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const tab = urlParams.get('tab');
  const groupId = urlParams.get('group');
  
  if (tab === 'manage') {
    showTab('manage');
    
    // If group ID is specified, select that group
    if (groupId) {
      setTimeout(() => {
        const groupElement = document.querySelector(`.group-item[data-group-id="${groupId}"]`);
        if (groupElement) {
          groupElement.click();
        }
      }, 100);
    }
  }
});

let deleteFormToSubmit = null;

function showConfirmModal(form) {
  deleteFormToSubmit = form;
  document.getElementById('confirmModal').classList.add('show');
  return false;
}

function hideConfirmModal() {
  document.getElementById('confirmModal').classList.remove('show');
  // Reset the action back to save_group if user cancels
  if (deleteFormToSubmit) {
    const actionInput = deleteFormToSubmit.querySelector('input[name="action"]');
    if (actionInput && actionInput.value === 'delete_group') {
      actionInput.value = 'save_group';
    }
  }
  deleteFormToSubmit = null;
}

function confirmDelete() {
  if (deleteFormToSubmit) {
    deleteFormToSubmit.submit();
  }
  hideConfirmModal();
}

// Close modal when clicking outside
document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) {
    hideConfirmModal();
  }
});
</script>
</body>
</html>
