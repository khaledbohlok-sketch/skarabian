<?php
// Prints a new random encryption key for config/config.php (app.key).
echo base64_encode(random_bytes(32)), PHP_EOL;
