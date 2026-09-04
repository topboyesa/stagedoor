<?php
// Include this at the top of any creator/*.php page that requires login.
// Assumes session_start() has already been called by the including file.

function require_creator_login() {
    if (empty($_SESSION['creator_id'])) {
        header('Location: login.php');
        exit;
    }
}

function current_creator_id() {
    return $_SESSION['creator_id'] ?? null;
}
