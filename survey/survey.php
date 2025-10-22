<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Zorg dat gebruiker ingelogd is en docent is
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Haal rol van gebruiker op
$role = null;
$res = mysqli_query($conn, "SELECT role FROM users WHERE id = " . intval($user_id));
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $role = $row['role'];
}

if ($role !== 'teacher') {
    echo '<div class="auth-container" style="max-width:800px;margin:2em auto;"><h2>Toegang geweigerd</h2><p>Alleen docenten kunnen enquêtes aanmaken.</p></div>';
    exit;
}

// Zorg dat er een 'survey' skill bestaat
$skill_id = null;
$q = mysqli_query($conn, "SELECT id FROM skills WHERE name = 'Survey' LIMIT 1");
if ($q && mysqli_num_rows($q) > 0) {
    $r = mysqli_fetch_assoc($q);
    $skill_id = $r['id'];
} else {
    mysqli_query($conn, "INSERT INTO skills (name, description) VALUES ('Survey','Automatisch aangemaakte skill voor enquêtes')");
    $skill_id = mysqli_insert_id($conn);
}

// Handel POST af: opslaan van survey + vragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(mysqli_real_escape_string($conn, $_POST['title'] ?? ''));
    $group_id = intval($_POST['group_id'] ?? 0);
    $anonymous = 1;
    $questions = $_POST['questions'] ?? [];
    $question_types = $_POST['question_types'] ?? [];
    $options = $_POST['options'] ?? [];

    $errors = [];
    if ($title === '') $errors[] = 'Vul een titel in.';
    if (empty($questions)) $errors[] = 'Voeg minimaal 1 vraag toe.';
    
    // Controleer of group_id bestaat als het niet 0 is
    if ($group_id > 0) {
        $check_group = mysqli_query($conn, "SELECT id FROM `groups` WHERE id = $group_id AND created_by = $user_id LIMIT 1");
        if (!$check_group || mysqli_num_rows($check_group) === 0) {
            $errors[] = 'Geselecteerde groep bestaat niet.';
        }
    }

    if (empty($errors)) {
        // Als group_id 0 is, maak dan een standaard groep aan
        if ($group_id === 0) {
            $default_group_name = "Algemene groep - " . date('d-m-Y H:i');
            $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, created_by) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $default_group_name, $user_id);
            mysqli_stmt_execute($stmt);
            $group_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }
        
        // Insert survey
        $stmt = mysqli_prepare($conn, "INSERT INTO surveys (title, group_id, created_by, anonymous) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'siii', $title, $group_id, $user_id, $anonymous);
        mysqli_stmt_execute($stmt);
        $survey_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Insert questions and link to survey
        $stmtQ = mysqli_prepare($conn, "INSERT INTO questions (skill_id, question_text, question_type, question_options) VALUES (?, ?, ?, ?)");
        $stmtLink = mysqli_prepare($conn, "INSERT INTO survey_questions (survey_id, question_id) VALUES (?, ?)");
        
        foreach ($questions as $index => $qtext) {
            $qtext = trim($qtext);
            if ($qtext === '') continue;
            
            $qtype = $question_types[$index] ?? 'scale';
            $qoptions = null;
            
            if ($qtype === 'choice' && isset($options[$index])) {
                $qoptions = trim($options[$index]);
            }
            
            mysqli_stmt_bind_param($stmtQ, 'isss', $skill_id, $qtext, $qtype, $qoptions);
            mysqli_stmt_execute($stmtQ);
            $question_id = mysqli_insert_id($conn);
            mysqli_stmt_bind_param($stmtLink, 'ii', $survey_id, $question_id);
            mysqli_stmt_execute($stmtLink);
        }
        mysqli_stmt_close($stmtQ);
        mysqli_stmt_close($stmtLink);

        header('Location: survey.php?created=1');
        exit;
    }
}

// Haal beschikbare groepen op
$groups = [];
$gq = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE created_by = $user_id ORDER BY name");
if ($gq) {
    while ($gr = mysqli_fetch_assoc($gq)) $groups[] = $gr;
}

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vragenlijst Maken - Gilde Skillsradar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    * { box-sizing: border-box; }
    
    body {
        background: #f5f7fb;
        min-height: 100vh;
        font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #1f2937;
        margin: 0;
        padding: 0;
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 2.5em 1.2em;
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(15,99,212,0.04);
        overflow: hidden;
        margin-bottom: 2em;
    }

    .card-header {
        background: linear-gradient(135deg, #0f63d4 0%, #0a4fa8 100%);
        padding: 2.5em 2em;
        text-align: center;
        color: white;
    }

    .card-header h1 {
        margin: 0 0 0.5em;
        font-size: 1.8em;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5em;
    }

    .card-header p {
        margin: 0;
        opacity: 0.95;
        font-size: 1em;
    }

    .card-body {
        padding: 2.5em;
    }

    .alert {
        padding: 1.2em 1.5em;
        border-radius: 12px;
        margin-bottom: 2em;
        display: flex;
        align-items: flex-start;
        gap: 1em;
    }

    .alert-success {
        background: #d1fae5;
        border-left: 4px solid #10b981;
        color: #065f46;
    }

    .alert-error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    .form-group {
        margin-bottom: 2em;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 0.5em;
        font-weight: 600;
        color: #0f63d4;
        margin-bottom: 0.7em;
        font-size: 1.05em;
    }

    .form-input, .form-select {
        width: 100%;
        padding: 0.9em 1.1em;
        border: 1.8px solid #e6eefc;
        border-radius: 10px;
        font-size: 0.95em;
        transition: all 0.3s ease;
        background: #f8fbff;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #0f63d4;
        box-shadow: 0 6px 18px rgba(15,99,212,0.06);
        background: #fff;
    }

    .form-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%230f63d4' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1.2em center;
        padding-right: 3em;
    }

    .form-hint {
        display: block;
        margin-top: 0.6em;
        font-size: 0.9em;
        color: #6b7280;
    }

    .form-hint a {
        color: #0f63d4;
        text-decoration: none;
        font-weight: 600;
    }

    .form-hint a:hover {
        text-decoration: underline;
    }

    .info-box {
        background: #f0f9ff;
        border: 2px solid #bae6fd;
        border-radius: 12px;
        padding: 1.2em 1.5em;
        display: flex;
        align-items: flex-start;
        gap: 1em;
    }

    .info-box-icon {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        background: #0f63d4;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.85em;
    }

    .info-box-content {
        color: #075985;
        line-height: 1.6;
        font-size: 0.95em;
    }

    .section-title {
        font-size: 1.4em;
        font-weight: 700;
        color: #0f63d4;
        margin: 2.5em 0 1.5em;
        display: flex;
        align-items: center;
        gap: 0.5em;
    }

    .question-card {
        background: #f8fbff;
        border: 2px solid #e6eefc;
        border-radius: 12px;
        padding: 2em;
        margin-bottom: 1.5em;
        transition: all 0.3s ease;
    }

    .question-card:hover {
        border-color: #0f63d4;
        box-shadow: 0 6px 18px rgba(15,99,212,0.08);
        transform: translateY(-2px);
    }

    .question-header {
        display: flex;
        gap: 1em;
        margin-bottom: 1.5em;
        align-items: flex-start;
    }

    .question-number {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        background: #0f63d4;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.1em;
    }

    .question-inputs {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1em;
    }

    .question-textarea {
        width: 100%;
        padding: 1em;
        border: 1.8px solid #e6eefc;
        border-radius: 10px;
        font-size: 0.95em;
        resize: vertical;
        min-height: 80px;
        transition: all 0.3s ease;
        background: #fff;
    }

    .question-textarea:focus {
        outline: none;
        border-color: #0f63d4;
        box-shadow: 0 6px 18px rgba(15,99,212,0.06);
    }

    .question-type-select {
        padding: 0.8em 1em;
        border: 1.8px solid #e6eefc;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
        font-size: 0.95em;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%230f63d4' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1em center;
        padding-right: 2.5em;
    }

    .question-type-select:focus {
        outline: none;
        border-color: #0f63d4;
        box-shadow: 0 6px 18px rgba(15,99,212,0.06);
    }

    .question-options-input {
        display: none;
        margin-top: 1em;
    }

    .options-list {
        display: flex;
        flex-direction: column;
        gap: 0.8em;
    }

    .option-item {
        display: flex;
        gap: 0.8em;
        align-items: center;
    }

    .option-item input {
        flex: 1;
        padding: 0.8em 1em;
        border: 1.8px solid #e6eefc;
        border-radius: 10px;
        font-size: 0.95em;
        background: #fff;
        transition: all 0.3s ease;
    }

    .option-item input:focus {
        outline: none;
        border-color: #0f63d4;
        box-shadow: 0 6px 18px rgba(15,99,212,0.06);
    }

    .btn-remove-option {
        background: #fee2e2;
        color: #dc2626;
        border: none;
        padding: 0.6em 0.8em;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .btn-remove-option:hover {
        background: #dc2626;
        color: white;
    }

    .btn-add-option {
        background: #eef6ff;
        color: #0f63d4;
        border: 2px dashed #0f63d4;
        padding: 0.8em 1.2em;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5em;
        margin-top: 0.5em;
    }

    .btn-add-option:hover {
        background: #0f63d4;
        color: white;
    }

    .preview-choice-options {
        display: flex;
        flex-direction: column;
        gap: 0.8em;
    }

    .preview-choice-option {
        padding: 1em;
        border: 2px solid #e6eefc;
        border-radius: 10px;
        background: #f8fbff;
        display: flex;
        align-items: center;
        gap: 0.8em;
    }

    .preview-choice-option input[type="radio"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .scale-options {
        display: flex;
        gap: 0.8em;
        justify-content: center;
        flex-wrap: wrap;
    }

    .scale-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
    }

    .scale-option input[type="radio"] {
        display: none;
    }

    .scale-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #f6f9ff;
        border: 2px solid #e6eefc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3em;
        font-weight: 700;
        color: #0f63d4;
        transition: all 0.3s ease;
    }

    .scale-option:hover .scale-circle {
        background: #eef6ff;
        border-color: #0f63d4;
        transform: scale(1.1);
        box-shadow: 0 6px 18px rgba(15,99,212,0.12);
    }

    .scale-label {
        margin-top: 0.5em;
        font-size: 0.85em;
        color: #6b7280;
        font-weight: 500;
    }

    .boolean-options {
        display: flex;
        gap: 1em;
    }

    .boolean-option {
        flex: 1;
        padding: 1.2em;
        border: 2px solid #e6eefc;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8em;
        font-size: 1.1em;
        font-weight: 600;
        color: #6b7280;
        background: #f6f9ff;
    }

    .boolean-option:hover {
        border-color: #0f63d4;
        background: #eef6ff;
        transform: translateY(-2px);
    }

    .boolean-option:active {
        transform: translateY(0);
    }

    .preview-text textarea {
        width: 100%;
        padding: 1em;
        border: 1.8px solid #e6eefc;
        border-radius: 10px;
        resize: vertical;
        min-height: 100px;
        background: #f8fbff;
    }

    .preview-choice {
        color: #6b7280;
        font-style: italic;
        text-align: center;
        padding: 2em;
    }

    .btn-remove {
        background: #fee2e2;
        color: #dc2626;
        border: none;
        padding: 0.8em 1.5em;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5em;
        margin-top: 1em;
    }

    .btn-remove:hover {
        background: #dc2626;
        color: white;
        transform: translateY(-2px);
    }

    .btn-remove:active {
        transform: translateY(0);
    }

    .btn-add-question {
        width: 100%;
        padding: 1.2em;
        background: #eef6ff;
        border: 2px dashed #0f63d4;
        border-radius: 12px;
        color: #0f63d4;
        font-weight: 700;
        font-size: 1.05em;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5em;
        margin: 2em 0;
    }

    .btn-add-question:hover {
        background: #0f63d4;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(15,99,212,0.12);
    }

    .form-actions {
        margin-top: 3em;
        padding-top: 2em;
        border-top: 2px solid #f3f4f6;
    }

    .btn-submit {
        background: #0f63d4;
        color: white;
        border: none;
        padding: 1.2em 3em;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1em;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(15,99,212,0.12);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(15,99,212,0.20);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    @media(max-width:768px){
        .container{padding:1.5em 1em;}
        .card-body{padding:1.5em;}
        .question-header{flex-direction:column;}
        .scale-options{gap:0.5em;}
        .scale-circle{width:48px;height:48px;font-size:1.1em;}
    }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Nieuwe Vragenlijst Maken
            </h1>
            <p>Stel vragen samen voor je groep en verzamel waardevolle feedback</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <div>
                        <div style="font-weight:600;margin-bottom:0.5em">Er zijn enkele problemen:</div>
                        <ul style="margin:0;padding-left:1.5em">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <div>
                        <div style="font-weight:600">Vragenlijst succesvol aangemaakt!</div>
                        <div style="margin-top:0.3em">Je vragenlijst is opgeslagen en klaar om te delen met je groep.</div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label class="form-label">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                        Titel van de vragenlijst
                    </label>
                    <input type="text" name="title" class="form-input" placeholder="Bijv. Tussentijdse evaluatie groepswerk" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        Selecteer een groep
                    </label>
                    <select name="group_id" class="form-select">
                        <option value="0">Maak automatisch een nieuwe algemene groep aan</option>
                        <?php foreach ($groups as $gr): ?>
                            <option value="<?= $gr['id'] ?>"><?= htmlspecialchars($gr['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">
                        <?php if (empty($groups)): ?>
                            Je hebt nog geen groepen. <a href="groups.php">Klik hier om een groep aan te maken</a>, of laat het systeem automatisch een algemene groep aanmaken.
                        <?php else: ?>
                            Kies een bestaande groep of laat automatisch een nieuwe algemene groep aanmaken.
                        <?php endif; ?>
                    </span>
                </div>

                <div class="form-group">
                    <div class="info-box">
                        <div class="info-box-icon">i</div>
                        <div class="info-box-content">
                            <strong>Privacy & Anonimiteit:</strong> Studenten vullen de vragenlijst anoniem in voor elkaar. 
                            Jij als docent kunt altijd zien wie welke antwoorden heeft gegeven voor evaluatiedoeleinden.
                        </div>
                    </div>
                </div>

                <div class="section-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Vragen
                </div>

                <div id="questions-wrap"></div>

                <button type="button" id="add-question" class="btn-add-question">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nieuwe vraag toevoegen
                </button>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:0.5em">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Vragenlijst opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addQuestion() {
    const wrap = document.getElementById('questions-wrap');
    const currentCount = wrap.querySelectorAll('.question-card').length;
    const html = createQuestionHTML(currentCount);
    wrap.insertAdjacentHTML('beforeend', html);
    
    // Add event listeners to option inputs
    const newCard = wrap.lastElementChild;
    const optionInputs = newCard.querySelectorAll('.option-input');
    optionInputs.forEach(input => {
        input.addEventListener('input', function() {
            updateChoicePreview(this);
        });
    });
}

function createQuestionHTML(index) {
    return `
        <div class="question-card" data-index="${index}">
            <div class="question-header">
                <div class="question-number">${index + 1}</div>
                <div class="question-inputs">
                    <textarea name="questions[]" class="question-textarea" placeholder="Typ hier je vraag..." rows="2" required></textarea>
                    <select name="question_types[]" class="question-type-select" onchange="updateQuestionPreview(this)">
                        <option value="scale">Schaal (1-5)</option>
                        <option value="text">Open antwoord</option>
                        <option value="choice">Meerkeuze</option>
                        <option value="boolean">Ja/Nee</option>
                    </select>
                </div>
            </div>
            
            <div class="question-options-input">
                <div class="options-list" data-question-index="${index}">
                    <div class="option-item">
                        <input type="text" class="option-input" placeholder="Optie 1" data-option-index="0">
                        <button type="button" class="btn-remove-option" onclick="removeOption(this)" style="visibility:hidden">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-add-option" onclick="addOption(this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Optie toevoegen
                </button>
                <input type="hidden" name="options[]" class="options-hidden">
            </div>
            
            <div class="question-preview">
                <div style="font-size:0.9em;color:#6b7280;margin-bottom:1em;display:flex;align-items:center;gap:0.5em">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Preview voor studenten
                </div>
                <div class="preview-scale">
                    <div class="scale-options">
                        ${[1,2,3,4,5].map(i => `
                            <label class="scale-option">
                                <input type="radio" name="preview_${index}" disabled>
                                <div class="scale-circle">${i}</div>
                                <span class="scale-label">${i === 1 ? 'Slecht' : i === 5 ? 'Uitstekend' : ''}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
                <div class="preview-text" style="display:none">
                    <textarea disabled placeholder="Studenten kunnen hier hun antwoord typen..." rows="3"></textarea>
                </div>
                <div class="preview-choice" style="display:none">
                    <div class="preview-choice-options"></div>
                </div>
                <div class="preview-boolean" style="display:none">
                    <div class="boolean-options">
                        <label class="boolean-option">
                            <input type="radio" name="bool_preview_${index}" disabled>
                            <span>Ja</span>
                        </label>
                        <label class="boolean-option">
                            <input type="radio" name="bool_preview_${index}" disabled>
                            <span>Nee</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn-remove" onclick="removeQuestion(this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Verwijder vraag
            </button>
        </div>
    `;
}

function addOption(btn) {
    const optionsList = btn.previousElementSibling;
    const questionIndex = optionsList.dataset.questionIndex;
    const currentOptions = optionsList.querySelectorAll('.option-item');
    const newIndex = currentOptions.length;
    
    const optionHTML = `
        <div class="option-item">
            <input type="text" class="option-input" placeholder="Optie ${newIndex + 1}" data-option-index="${newIndex}">
            <button type="button" class="btn-remove-option" onclick="removeOption(this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    `;
    
    optionsList.insertAdjacentHTML('beforeend', optionHTML);
    
    // Add event listener to new input
    const newInput = optionsList.lastElementChild.querySelector('.option-input');
    newInput.addEventListener('input', function() {
        updateChoicePreview(this);
    });
    
    // Update preview
    updateChoicePreview(newInput);
}

function removeOption(btn) {
    const optionItem = btn.closest('.option-item');
    const optionsList = optionItem.closest('.options-list');
    const options = optionsList.querySelectorAll('.option-item');
    
    // Don't allow removing if only one option left
    if (options.length <= 1) return;
    
    optionItem.remove();
    
    // Update preview
    const firstInput = optionsList.querySelector('.option-input');
    if (firstInput) updateChoicePreview(firstInput);
}

function updateChoicePreview(input) {
    const card = input.closest('.question-card');
    const optionsList = card.querySelector('.options-list');
    const previewContainer = card.querySelector('.preview-choice-options');
    const hiddenInput = card.querySelector('.options-hidden');
    
    // Collect all option values
    const options = [];
    optionsList.querySelectorAll('.option-input').forEach(inp => {
        const val = inp.value.trim();
        if (val) options.push(val);
    });
    
    // Update hidden input with JSON
    hiddenInput.value = JSON.stringify(options);
    
    // Update preview
    if (options.length === 0) {
        previewContainer.innerHTML = '<div style="color:#6b7280;font-style:italic;text-align:center;padding:2em">Voeg opties toe om de preview te zien...</div>';
    } else {
        previewContainer.innerHTML = options.map((opt, i) => `
            <label class="preview-choice-option">
                <input type="radio" name="choice_preview_${card.dataset.index}" disabled>
                <span>${opt}</span>
            </label>
        `).join('');
    }
}

function updateQuestionPreview(select) {
    const card = select.closest('.question-card');
    const type = select.value;
    const optionsInput = card.querySelector('.question-options-input');
    const previews = {
        scale: card.querySelector('.preview-scale'),
        text: card.querySelector('.preview-text'),
        choice: card.querySelector('.preview-choice'),
        boolean: card.querySelector('.preview-boolean')
    };

    // Hide all previews
    Object.values(previews).forEach(p => p.style.display = 'none');
    
    // Show relevant preview
    if (previews[type]) previews[type].style.display = 'block';
    
    // Show/hide options input for multiple choice
    optionsInput.style.display = type === 'choice' ? 'block' : 'none';
    
    if (type === 'choice') {
        const firstInput = optionsInput.querySelector('.option-input');
        if (firstInput) updateChoicePreview(firstInput);
    }
}

function removeQuestion(btn) {
    const card = btn.closest('.question-card');
    card.style.opacity = '0';
    card.style.transform = 'scale(0.95)';
    card.style.transition = 'all 0.3s ease';
    setTimeout(() => {
        card.remove();
        updateQuestionNumbers();
    }, 300);
}

function updateQuestionNumbers() {
    const cards = document.querySelectorAll('.question-card');
    cards.forEach((card, index) => {
        const numberEl = card.querySelector('.question-number');
        if (numberEl) numberEl.textContent = index + 1;
        card.dataset.index = index;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to add question button
    document.getElementById('add-question').addEventListener('click', addQuestion);
    
    // Add first question on page load
    addQuestion();
    
    // Add event listener for option inputs
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('option-input')) {
            updateChoicePreview(e.target);
        }
    });
});
</script>
</body>
</html>
