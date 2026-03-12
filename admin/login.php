<?php
// Redirect to unified login page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
redirect(BASE_URL . '/login.php');