<?php
  // V2 or something idk

  $startTime = microtime(true); // timer

/// connecting to database
  $dir = dirname(__DIR__, 2);
  require_once "$dir/_config/config.php"; // import config file

  $host = DATABASE_HOST;
  $database = DATABASE_NAME;
  $username = DATABASE_USERNAME;
  $password = DATABASE_PASSWORD;
  try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
  } catch (PDOException $e) {
    echo "The server got itself into trouble, please try to refresh.<br>";
  }
 
/// processing user input
  $searchQuery = htmlspecialchars(isset($_GET['q']) ? $_GET['q'] : "");
  $tags = htmlspecialchars(isset($_GET['tag']) ? $_GET['tag'] : "");
  $page = filter_var(isset($_GET['page']) ? $_GET['page'] : 1, FILTER_VALIDATE_INT); 
  $oldest = filter_var(isset($_GET['oldest']) ? $_GET['oldest'] : false, FILTER_VALIDATE_BOOLEAN);
  $giveStats = filter_var(isset($_GET['stats']) ? true : false, FILTER_VALIDATE_BOOLEAN);

  if ($page < 0 || $page === 0) {
    $page = 1;
  }

/// defining stuff lmao
  $maxResults = (!empty($searchQuery)) ? 10 : 20;
  $pageNumber = ($page - 1) * $maxResults;
  $sqlQuery = "SELECT * FROM issues";
  $params = array();
  $conditions = array();


/// if there is an search query in the request
  if (!empty($searchQuery)) {
    $searchQuery = strtolower($searchQuery);
    if (strpos($searchQuery, 'cusa') !== false) {
      $conditions[] = "code LIKE :searchQuery";
  } else {
      $conditions[] = "title LIKE :searchQuery";
  }
    $params[':searchQuery'] = $searchQuery . '%';
  }
  

/// if there are tags in the request
  if (!empty($tags)) {
    $tagFilters = explode(',', $tags);
    $tagConditions = [];
    $tagParams = [];

    foreach ($tagFilters as $index => $tag) {
        $tagParam = ":tagFilter{$index}";
        $tagConditions[] = "(CONCAT(',', tags, ',') LIKE CONCAT('%,', {$tagParam}, ',%'))";
        $tagParams[$tagParam] = "{$tag}";
    }
    
    if (!empty($tagConditions)) {
        $conditions[] = "(" . implode(" OR ", $tagConditions) . ")";
        $params = array_merge($params, $tagParams);
    }
  }

  if (!empty($conditions)) {
    $sqlQuery .= " WHERE " . implode(" AND ", $conditions);
  }

  
/// gets the total amount of issues based on the search query
  $sqlQueryForTotal = "SELECT COUNT(*) AS total FROM ($sqlQuery) AS totalIssues";
  $stmtTotal = $conn->prepare($sqlQueryForTotal);

  foreach ($params as $param => &$value) {
    $stmtTotal->bindParam($param, $value, PDO::PARAM_STR);
  }

  $stmtTotal->execute();
  $totalIssuesAmount = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
  $totalPages = ceil($totalIssuesAmount / $maxResults);


/// forming and executing sql query
  $sqlQuery .= " ORDER BY id " . ($oldest ? "ASC" : "DESC");
  $sqlQuery .= " LIMIT :offset, :limit";
  $params[':offset'] = $pageNumber;
  $params[':limit'] = $maxResults;

  $stmt = $conn->prepare($sqlQuery);

  foreach ($params as $param => &$value) {
    if (is_int($value)) {
        $stmt->bindParam($param, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindParam($param, $value, PDO::PARAM_STR);
    }
  }

  $stmt->execute();
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);


/// Outputing results
header('Content-Type: application/json');

$games = array();
$stats = array();

  foreach ($result as $game) {
    $cusaCode = $game['code'];
    $image = $game['type'] === "HB" ? (file_exists("$dir/public_html/_images/HB/{$game['title']}.avif") ? true : false) : (file_exists("$dir/public_html/_images/CUSA/{$cusaCode}.avif") ? true : false);
    $games[] = array(
      "id" => $game['id'],
      "title" => $game['title'],
      "code" => $game['code'],
      "type" => $game['type'],
      "tag" => $game['tags'],
      "upDate" => $game['updatedDate'],
      "image" => $image
    );
  }

  /// stats on the compatibility list
  if ($giveStats === true) {
    $availableTags = ['Nothing', 'Boots', 'Menus', 'Ingame', 'Playable'];
    $tagPercentages = [];

    $naTag = "N/A";
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM issues WHERE tags = :tag");
    $stmt->bindParam(':tag', $naTag, PDO::PARAM_STR);
    $stmt->execute();
    $naCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $result = $conn->query("SELECT COUNT(*) FROM issues");
    $total = $result->fetchColumn();

    $totalIssuesWithoutNA = $total - $naCount;

    foreach ($availableTags as $tag) {
      $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM issues WHERE tags = :tag");
      $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
      $stmt->execute();
      
      $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
      $percentage = ($total > 0) ? ($count / $total) * 100 : 0;
      $tagPercentages[$tag] = $percentage;
      $tagCount[$tag] = $count;
    }

    $amount = (100 - array_sum($tagPercentages)) / count($tagPercentages);

    foreach ($availableTags as $tag) {
      $percentage = $tagPercentages[$tag];
      $count = $tagCount[$tag];
      $percentage += $amount;
      $percentage = round($percentage, 2);
      $stats[] = array(
        "tag" => $tag,
        "percent" => $percentage,
        "count" => $count
      );
    }
  }

  $executionTime = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds

  $info = array(
    "issues" => $totalIssuesAmount,
    "pages" => $totalPages,
    "time" => $executionTime
  );

  $data = array(
    "info" => $info,
    "games" => $games,
    "stats" => $stats,
  );


  /// end
  $jsonData = json_encode($data);
  // $jsonData = json_encode($data, JSON_PRETTY_PRINT);
  echo $jsonData;
  $conn = null; //exit connection
?>
