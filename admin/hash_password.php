<?php
// hash_password.php - Run this once to generate your new password hash

$new_password = 'Qwerty@1234#'; // Replace with your actual password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "Your new password hash is: " . $hashed_password . "\n";
echo "Copy this hash and use it in the SQL update below.\n";
?>