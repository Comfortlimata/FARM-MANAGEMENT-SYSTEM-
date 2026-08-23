<?php
session_start();
session_unset();
session_destroy();
header('Location: /FARM-MANAGEMENT-SYSTEM-/auth/login.php');
exit;
