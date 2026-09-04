<?php
session_start();
unset($_SESSION['creator_id'], $_SESSION['creator_name']);
header('Location: login.php');
exit;
