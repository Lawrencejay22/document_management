<?php
session_start();
session_destroy();
header("Location: ../login form/login.php");
exit();
?>
