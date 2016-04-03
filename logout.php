<?php
//Remove error reporting in final version!
ini_set('display_errors', 1);
error_reporting(~0);
require_once("ssas.php");
$ssas = new Ssas();
$ssas -> logout();
header("Location: index.php");
?>
