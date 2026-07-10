<?php
require_once __DIR__ . '/auth.php';
doLogout();
header('Location: sieu-nhi-atgt-ai.php');
exit;
