<?php
function startSession($result) {
    $session_timeout = 10; // 5 seconds idle timeout
    $_SESSION['logged_in']       = true;
    $_SESSION['user_id']         = $result['id'];
    $_SESSION['username']        = $result['username'];
    $_SESSION['LAST_ACTIVITY']   = time();
    $_SESSION['SESSION_TIMEOUT'] = $session_timeout;
}

function checkSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }

    $timeout = $_SESSION['SESSION_TIMEOUT'];
    $last    = $_SESSION['LAST_ACTIVITY'];

    if ((time() - $last) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}
?>
