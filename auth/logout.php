<?php

session_start();
$_SESSION = array();
session_destroy();


if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 42000, '/');
}

header("Location: ../index.php");
exit;
?>
