<?php
echo "<h1>URL Test</h1>";
echo "<ul>";
echo "<li><a href='index.php'>index.php (direct)</a></li>";
echo "<li><a href='./'>Home (directory)</a></li>";
echo "<li><a href='about'>about (clean)</a></li>";
echo "<li><a href='about.php'>about.php (direct)</a></li>";
echo "<li><a href='contact'>contact (clean)</a></li>";
echo "</ul>";

echo "<h2>Server Info</h2>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "<br>";
?>