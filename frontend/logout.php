<?php
// frontend/logout.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logout();
redirect('/login.php');
?>
