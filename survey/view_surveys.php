<?php
// survey/view_surveys.php
include '../includes/header.php';
include '../includes/db.php';

// Haal alle vragenlijsten op die door de huidige gebruiker zijn aangemaakt
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

if (isset($_POST['action']) && $_POST['action'] === 'delete_survey' && isset($_POST['survey_id'])) {
    $survey_id = intval($_POST['survey_id']);
    
    // Delete survey questions first
    $stmt = $conn->prepare("DELETE FROM survey_questions WHERE survey_id = ?");
    $stmt->bind_param('i', $survey_id);
    $stmt->execute();
    
    // Delete survey
    $stmt = $conn->prepare("DELETE FROM surveys WHERE id = ? AND created_by = ?");
    $stmt->bind_param('ii', $survey_id, $user_id);
    $stmt->execute();
    
    header('Location: view_surveys.php?deleted=1');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'edit_survey' && isset($_POST['survey_id'])) {
    $survey_id = intval($_POST['survey_id']);
    $title = trim($_POST['title'] ?? '');
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? intval($_POST['group_id']) : null;
    
    // Update survey
    $stmt = $conn->prepare("UPDATE surveys SET title = ?, group_id = ? WHERE id = ? AND created_by = ?");
    $stmt->bind_param('siii', $title, $group_id, $survey_id, $user_id);
    $stmt->execute();
    
    // First get all question IDs for this survey
    $stmt = $conn->prepare("SELECT question_id FROM survey_questions WHERE survey_id = ?");
    $stmt->bind_param('i', $survey_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $question_ids = [];
    while ($row = $result->fetch_assoc()) {
        $question_ids[] = $row['question_id'];
    }
    
    // Delete survey_questions links
    $stmt = $conn->prepare("DELETE FROM survey_questions WHERE survey_id = ?");
    $stmt->bind_param('i', $survey_id);
    $stmt->execute();
    
    // Delete the actual questions
    if (!empty($question_ids)) {
        $ids_str = implode(',', array_map('intval', $question_ids));
        $conn->query("DELETE FROM questions WHERE id IN ($ids_str)");
    }
    
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        $skill_id = 1; // Default skill for surveys
        
        foreach ($_POST['questions'] as $q) {
            $q_text = trim($q['text'] ?? '');
            $q_type = trim($q['type'] ?? 'scale');
            if ($q_text !== '') {
                // Insert into questions table
                $stmt = $conn->prepare("INSERT INTO questions (skill_id, question_text, question_type) VALUES (?, ?, ?)");
                $stmt->bind_param('iss', $skill_id, $q_text, $q_type);
                $stmt->execute();
                $question_id = $conn->insert_id;
                
                // Link to survey in survey_questions table
                $stmt = $conn->prepare("INSERT INTO survey_questions (survey_id, question_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $survey_id, $question_id);
                $stmt->execute();
            }
        }
    }
    
    header('Location: view_surveys.php?updated=1');
    exit;
}

$sql = "SELECT s.id, s.title, s.created_at, s.group_id, g.name as group_name 
        FROM surveys s 
        LEFT JOIN `groups` g ON s.group_id = g.id 
        WHERE s.created_by = ? 
        ORDER BY s.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$groups_sql = "SELECT id, name FROM `groups` WHERE created_by = ? ORDER BY name";
$groups_stmt = $conn->prepare($groups_sql);
$groups_stmt->bind_param('i', $user_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();
$groups = [];
while ($g = $groups_result->fetch_assoc()) {
    $groups[] = $g;
}
?>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f5f7fb;color:#1f2937;line-height:1.6;}
.container{max-width:1400px;margin:2rem auto;padding:0 2rem;}
h2{color:#0f63d4;font-size:2rem;margin-bottom:2rem;font-weight:600;}
.success-msg{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:1rem;border-radius:8px;margin-bottom:1.5rem;}
.table-wrapper{width:100%;overflow-x:auto;background:#fff;border-radius:12px;}
.survey-table{width:100%;border-collapse:collapse;min-width:800px;}
.survey-table thead{background:#0f63d4;color:#fff;}
.survey-table th{padding:1.2rem 1.5rem;text-align:left;font-weight:600;font-size:1rem;white-space:nowrap;}
.survey-table td{padding:1.2rem 1.5rem;border-bottom:1px solid #e6eefc;vertical-align:middle;}
.survey-table tbody tr:hover{background:#f8fbff;}
.survey-table tbody tr:last-child td{border-bottom:none;}
.actions{display:flex;gap:0.5rem;justify-content:flex-start;}
.icon-btn{width:38px;height:38px;border-radius:8px;border:2px solid #e5e7eb;background:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0;transform:translateY(-9px);}
.icon-btn:hover{transform:translateY(-11px);box-shadow:0 4px 12px rgba(0,0,0,0.15);}
.icon-btn.edit{border-color:#0f63d4;color:#0f63d4;}
.icon-btn.edit:hover{background:#0f63d4;color:#fff;border-color:#0f63d4;}
.icon-btn.delete{border-color:#ef4444;color:#ef4444;}
.icon-btn.delete:hover{background:#ef4444;color:#fff;border-color:#ef4444;}
.icon-btn svg{width:18px;height:18px;}

/* Modal styles */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;}
.modal.active{display:flex;}
.modal-content{background:#fff;border-radius:16px;padding:2.5rem;max-width:900px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;padding-bottom:1rem;border-bottom:2px solid #e6eefc;}
.modal-header h3{color:#0f63d4;font-size:1.75rem;font-weight:600;}
.close-btn{background:none;border:none;font-size:1.8rem;cursor:pointer;color:#6b7280;padding:0;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:all .2s;}
.close-btn:hover{background:#f3f4f6;color:#1f2937;}
.form-group{margin-bottom:1.8rem;}
.form-group label{display:block;margin-bottom:0.6rem;color:#374151;font-weight:600;font-size:0.95rem;}
.form-group input,.form-group select{width:100%;padding:0.8rem 1rem;border-radius:10px;border:2px solid #e6eefc;background:#f8fbff;font-size:0.95rem;transition:all .2s;font-family:inherit;}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#0f63d4;background:#fff;box-shadow:0 0 0 3px rgba(15,99,212,0.1);}
.form-group select{cursor:pointer;}
.questions-section{margin-top:2.5rem;padding-top:2rem;border-top:2px solid #e6eefc;}
.questions-section h4{color:#1f2937;margin-bottom:1.5rem;font-size:1.2rem;font-weight:600;}
.question-item{background:#f8fbff;border:2px solid #e6eefc;border-radius:12px;padding:1.5rem;margin-bottom:1.2rem;display:flex;gap:1rem;align-items:start;transition:all .2s;}
.question-item:hover{border-color:#0f63d4;box-shadow:0 2px 8px rgba(15,99,212,0.1);}
.question-item-content{flex:1;display:flex;flex-direction:column;gap:0.8rem;}
.question-item input{margin-bottom:0;padding:0.8rem 1rem;border-radius:10px;border:2px solid #e6eefc;background:#fff;font-size:0.95rem;transition:all .2s;}
.question-item input:focus{outline:none;border-color:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.1);}
.question-item select{width:auto;padding:0.6rem 1rem;font-size:0.9rem;border-radius:8px;border:2px solid #e6eefc;background:#fff;cursor:pointer;transition:all .2s;}
.question-item select:focus{outline:none;border-color:#0f63d4;box-shadow:0 0 0 3px rgba(15,99,212,0.1);}
.remove-question-btn{background:#fff;border:2px solid #ef4444;color:#ef4444;width:40px;height:40px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.5rem;font-weight:300;transition:all .2s;}
.remove-question-btn:hover{background:#ef4444;color:#fff;transform:scale(1.05);}
.add-question-btn{background:#fff;border:2px solid #0f63d4;color:#0f63d4;padding:0.8rem 1.5rem;border-radius:10px;cursor:pointer;font-size:0.95rem;font-weight:600;display:inline-flex;align-items:center;gap:0.6rem;transition:all .2s;}
.add-question-btn:hover{background:#0f63d4;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(15,99,212,0.2);}
.add-question-btn svg{flex-shrink:0;}
.modal-actions{display:flex;gap:1rem;margin-top:2.5rem;padding-top:2rem;border-top:2px solid #e6eefc;justify-content:flex-end;}
.btn{padding:0.8rem 1.8rem;border-radius:10px;border:none;cursor:pointer;font-size:0.95rem;font-weight:600;transition:all .2s;}
.btn-primary{background:#0f63d4;color:#fff;}
.btn-primary:hover{background:#0d52b0;transform:translateY(-2px);box-shadow:0 6px 16px rgba(15,99,212,0.3);}
.btn-secondary{background:#fff;color:#6b7280;border:2px solid #e5e7eb;}
.btn-secondary:hover{background:#f9fafb;border-color:#d1d5db;}
.btn-danger{background:#ef4444;color:#fff;}
.btn-danger:hover{background:#dc2626;transform:translateY(-2px);box-shadow:0 6px 16px rgba(239,68,68,0.3);}
.confirm-modal .modal-content{max-width:500px;padding:2rem;}
.confirm-modal p{color:#6b7280;margin-bottom:1.5rem;line-height:1.6;}
.confirm-modal strong{color:#1f2937;}
.empty-state{text-align:center;padding:3rem 1rem;color:#6b7280;}
.empty-state svg{width:64px;height:64px;margin-bottom:1rem;color:#cbd5e1;}
</style>

<div class="container">
    <h2>Mijn Vragenlijsten</h2>
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="success-msg">Vragenlijst succesvol verwijderd!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
        <div class="success-msg">Vragenlijst succesvol bijgewerkt!</div>
    <?php endif; ?>
    
    <div class="table-wrapper">
        <table class="survey-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Groep</th>
                    <th>Aangemaakt op</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p>Je hebt nog geen vragenlijsten aangemaakt.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo $row['group_name'] ? htmlspecialchars($row['group_name']) : '<em style="color:#9ca3af;">Algemene groep</em>'; ?></td>
                        <td><?php echo date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="actions">
                                <button class="icon-btn edit" onclick="openEditModal(<?php echo $row['id']; ?>)" title="Bewerken">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button class="icon-btn delete" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['title'])); ?>')" title="Verwijderen">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        <line x1="10" y1="11" x2="10" y2="17"/>
                                        <line x1="14" y1="11" x2="14" y2="17"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Vragenlijst Bewerken</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="action" value="edit_survey">
            <input type="hidden" name="survey_id" id="edit_survey_id">
            
            <div class="form-group">
                <label>Titel van de vragenlijst</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            
            <div class="form-group">
                <label>Selecteer een groep</label>
                <select name="group_id" id="edit_group_id">
                    <option value="">Algemene groep (alle studenten)</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="questions-section">
                <h4>Vragen</h4>
                <div id="editQuestionsContainer"></div>
                <button type="button" class="add-question-btn" onclick="addEditQuestion()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Vraag toevoegen
                </button>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Annuleren</button>
                <button type="submit" class="btn btn-primary">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal confirm-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Vragenlijst Verwijderen</h3>
            <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
        </div>
        <p>Weet je zeker dat je de vragenlijst "<strong id="delete_survey_title"></strong>" wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.</p>
        <form id="deleteForm" method="POST">
            <input type="hidden" name="action" value="delete_survey">
            <input type="hidden" name="survey_id" id="delete_survey_id">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuleren</button>
                <button type="submit" class="btn btn-danger">Verwijderen</button>
            </div>
        </form>
    </div>
</div>

<script>
let editQuestionCounter = 0;

function openEditModal(surveyId) {
    fetch(`get_survey_data.php?id=${surveyId}`)
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.json();
        })
        .then(data => {
            document.getElementById('edit_survey_id').value = surveyId;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_group_id').value = data.group_id || '';
            
            const container = document.getElementById('editQuestionsContainer');
            container.innerHTML = '';
            editQuestionCounter = 0;
            
            if (data.questions && data.questions.length > 0) {
                data.questions.forEach(q => {
                    addEditQuestion(q.question_text, q.question_type);
                });
            } else {
                addEditQuestion();
            }
            
            document.getElementById('editModal').classList.add('active');
        })
        .catch(error => {
            console.error('Error fetching survey data:', error);
            alert('Er is een fout opgetreden bij het laden van de vragenlijst. Probeer het opnieuw.');
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function addEditQuestion(text = '', type = 'scale') {
    const container = document.getElementById('editQuestionsContainer');
    const div = document.createElement('div');
    div.className = 'question-item';
    div.innerHTML = `
        <div class="question-item-content">
            <input type="text" name="questions[${editQuestionCounter}][text]" placeholder="Vraag ${editQuestionCounter + 1}" value="${text}" required>
            <select name="questions[${editQuestionCounter}][type]">
                <option value="scale" ${type === 'scale' ? 'selected' : ''}>Schaal (1-5)</option>
                <option value="text" ${type === 'text' ? 'selected' : ''}>Tekst</option>
                <option value="choice" ${type === 'choice' ? 'selected' : ''}>Meerkeuze</option>
                <option value="boolean" ${type === 'boolean' ? 'selected' : ''}>Ja/Nee</option>
            </select>
        </div>
        <button type="button" class="remove-question-btn" onclick="this.parentElement.remove()">−</button>
    `;
    container.appendChild(div);
    editQuestionCounter++;
}

function openDeleteModal(surveyId, title) {
    document.getElementById('delete_survey_id').value = surveyId;
    document.getElementById('delete_survey_title').textContent = title;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}
</script>
