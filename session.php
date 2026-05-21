<?php
// ─────────────────────────────────────────────
// SAFE SESSION HANDLER (NO BLANK PAGE VERSION)
// ─────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────
// LOGIN SESSION START
// ─────────────────────────────────────────────
function startSession($result) {

    $session_timeout = 10; // 10 seconds (recommended)

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $result['id'] ?? null;
    $_SESSION['username'] = $result['username'] ?? null;

    $_SESSION['LAST_ACTIVITY'] = time();
    $_SESSION['SESSION_TIMEOUT'] = $session_timeout;
}

// ─────────────────────────────────────────────
// SESSION CHECK (SAFE VERSION)
// ─────────────────────────────────────────────
function checkSession() {

    // NOT LOGGED IN → REDIRECT
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }

    // SAFE DEFAULTS (PREVENT BLANK PAGE)
    $timeout = $_SESSION['SESSION_TIMEOUT'] ?? 600;
    $last    = $_SESSION['LAST_ACTIVITY'] ?? time();

    // SESSION EXPIRED
    if ((time() - $last) > $timeout) {

        session_unset();
        session_destroy();

        header("Location: login.php?expired=1");
        exit();
    }

    // UPDATE ACTIVITY TIME
    $_SESSION['LAST_ACTIVITY'] = time();
}
?>