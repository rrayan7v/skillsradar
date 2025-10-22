<?php
session_start();
include '../includes/db.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = strtolower(trim($_SESSION['user_role'] ?? ''));

// Check if user is a student
if (!in_array($user_role, ['student', 'leerling'])) {
    header("Location: ../dashboard/dashboard.php");
    exit();
}

// Get all groups the student is a member of
$groups_query = "SELECT g.id, g.name, u.name as teacher_name
                 FROM `groups` g
                 INNER JOIN group_members gm ON g.id = gm.group_id
                 LEFT JOIN users u ON g.created_by = u.id
                 WHERE gm.user_id = ?
                 ORDER BY g.name ASC";
$stmt = mysqli_prepare($conn, $groups_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$groups_result = mysqli_stmt_get_result($stmt);
$groups = mysqli_fetch_all($groups_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Groepen - Skillsradar</title>
    <link rel="stylesheet" href="/Skillradar/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/Skillradar/assets/img/favicon.png">
    <style>
        /* Updated styling to match other pages with blue theme */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            line-height: 1.6;
        }
        .groups-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        /* Updated page-header styling to match view_survey.php exactly */
        .page-header {
            background: #fff;
            padding: 2em;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2em;
        }
        .page-header h2 {
            color: #0f63d4;
            margin: 0 0 0.5em 0;
            font-size: 2em;
        }
        .page-header p {
            color: #6b7280;
            margin: 0;
        }
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        .group-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-top: 4px solid #0f63d4;
        }
        .group-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(15,99,212,0.15);
        }
        .group-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f8ff;
        }
        .group-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0f63d4, #4a90e2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .group-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }
        .group-info h3 {
            font-size: 1.4rem;
            color: #1f2937;
            margin-bottom: 0.3rem;
        }
        .group-info p {
            font-size: 0.9rem;
            color: #6b7280;
        }
        .members-section {
            margin-top: 1.5rem;
        }
        .members-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0f63d4;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .members-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        .member-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: #f8fbff;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .member-item:hover {
            background: #eef6ff;
        }
        .member-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #0f63d4, #4a90e2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .member-name {
            font-weight: 500;
            color: #1f2937;
        }
        .member-you {
            margin-left: auto;
            background: #10b981;
            color: #fff;
            padding: 0.2rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .no-groups {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .no-groups svg {
            width: 80px;
            height: 80px;
            fill: #d1d5db;
            margin-bottom: 1.5rem;
        }
        .no-groups h3 {
            font-size: 1.5rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .no-groups p {
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="groups-container">
        <!-- Changed h1 to h2 to match view_survey.php -->
        <div class="page-header">
            <h2>Mijn Groepen</h2>
            <p>Bekijk je groepsleden en werk samen aan je skills</p>
        </div>

        <?php if (empty($groups)): ?>
            <div class="no-groups">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
                <h3>Je bent nog niet toegevoegd aan een groep</h3>
                <p>Vraag je docent om je toe te voegen aan een groep</p>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                    <?php
                    // Get all members of this group
                    $members_query = "SELECT u.id, u.name, u.email 
                                     FROM users u
                                     INNER JOIN group_members gm ON u.id = gm.user_id
                                     WHERE gm.group_id = ?
                                     ORDER BY u.name";
                    $stmt = mysqli_prepare($conn, $members_query);
                    mysqli_stmt_bind_param($stmt, "i", $group['id']);
                    mysqli_stmt_execute($stmt);
                    $members_result = mysqli_stmt_get_result($stmt);
                    $members = mysqli_fetch_all($members_result, MYSQLI_ASSOC);
                    mysqli_stmt_close($stmt);
                    ?>
                    
                    <div class="group-card">
                        <div class="group-header">
                            <div class="group-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                            </div>
                            <div class="group-info">
                                <h3><?= htmlspecialchars($group['name']) ?></h3>
                                <p>Docent: <?= htmlspecialchars($group['teacher_name'] ?? 'Onbekend') ?></p>
                            </div>
                        </div>

                        <div class="members-section">
                            <div class="members-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                Groepsleden (<?= count($members) ?>)
                            </div>
                            <div class="members-list">
                                <?php foreach ($members as $member): ?>
                                    <div class="member-item">
                                        <div class="member-avatar">
                                            <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                        </div>
                                        <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                                        <?php if ($member['id'] == $user_id): ?>
                                            <span class="member-you">Jij</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
