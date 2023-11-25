<?php
$serverUsername = getenv('USERNAME');
require_once "/home/{$serverUsername}/domains/fpps4.net/config/config.php"; // import config file
$validSecret = API_ACCESS_SECRET;
$validArgument = API_ARGUMENT_SECRET;
$headers = apache_request_headers();

/// makes it so the api is accessible by: headers, browser query and by command line arguments :3
if ((isset($_GET[$validArgument]) && $_GET[$validArgument] === $validSecret) || (isset($argv[1]) && $argv[1] === $validSecret) || isset($_SERVER['HTTP_AUTHORIZATION']) === $validSecret) {
/// processing user input
$cusaCodes = isset($_GET['cusa']) ? $_GET['cusa'] : '';
$homebrews = isset($_GET['homebrew']) ? $_GET['homebrew'] : '';
$cusaCodes = filter_var($cusaCodes, FILTER_SANITIZE_STRING);
$homebrews = filter_var($homebrews, FILTER_SANITIZE_STRING);

if ($cusaCodes || $homebrews) {
  /// make connection to database
  $host = DATABASE_HOST;
  $database = DATABASE_NAME;
  $username = DATABASE_USERNAME;
  $password = DATABASE_PASSWORD;
  try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
  } catch (PDOException $e) {
    die("The server got itself into trouble, sorry for that.");
  }
  
  /// variables
  $cusacodeArray = array();
  $homebrewArray = array();
  $cusacode = explode(',', $cusaCodes);
  $homebrew = explode(',', $homebrews);
  /// handles cusa codes and gets status
  if ($cusaCodes) {
    foreach ($cusacode as $code) {
      $query = "SELECT tags FROM issues WHERE cusacode = :cusacode";
      $stmt = $conn->prepare($query);
      $stmt->bindParam(':cusacode', $code, PDO::PARAM_STR);
      $stmt->execute();
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($result) {
        $tag = $result["0"]["tags"];
      } else {
        $tag = "N/A";
      }

      $cusacodeArray[] = array(
        "id" => $code,
        "tag" => $tag
      );
    }
  }
  if ($homebrews) {
    foreach ($homebrew as $code) {
      $query = "SELECT tags FROM issues WHERE title = :homebrew";
      $stmt = $conn->prepare($query);
      $stmt->bindParam(':homebrew', $code, PDO::PARAM_STR);
      $stmt->execute();
      $HBresult = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($HBresult) {
        $tag = $HBresult["0"]["tags"];
      } else {
        $tag = "N/A";
      }
      $homebrewArray[] = array(
        "title" => $code,
        "tag" => $tag
      );
    }
  }
  $data = array(
    "cusacode" => $cusacodeArray,
    "homebrew" => $homebrewArray
  );
  /// end
  header('Content-Type: application/json');
    $jsonData = json_encode($data);
  //$jsonData = json_encode($data, JSON_PRETTY_PRINT);

  echo $jsonData;
  $conn = null; //exit connection
}
} else {
  http_response_code(404);
  include("/home/{$serverUsername}/domains/fpps4.net/public_html/404.php");
}?>