<?php
session_start();

// Alle sessievariabelen leegmaken
$_SESSION = [];

// Sessie vernietigen
session_destroy();

// Sessiecookie ongeldig maken (veiligheid)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Terug naar de homepagina
header("Location: ../index.php");
exit();
?>
