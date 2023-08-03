<?php
$serverUsername = getenv('USERNAME');
require_once "/home/{$serverUsername}/domains/fpps4.net/config/config.php"; // import config file
$validSecret = BOT_ACCESS_SECRET;
$validArgument = BOT_ARGUMENT_SECRET;

// The secret query parameter is provided and matches the valid secret
if ((isset($_GET[$validArgument]) && $_GET[$validArgument] === $validSecret) || (isset($argv[1]) && $argv[1] === $validSecret)) {
echo "you got the password right, nice!";
} else {
  http_response_code(404);
  include("/home/{$serverUsername}/domains/fpps4.net/public_html/404.php");
}?>