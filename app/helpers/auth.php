<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function isLoggedIn() {
    return isset($_SESSION['usuario']);
}
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'usuarios/login');
        exit;
    }
}
