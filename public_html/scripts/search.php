<?php
  // I try to make my code as easy to understand as possible.
  // When there is a comment "//" that useally means that that part of the code starts there until the next comment.

/// timer
  $startTime = microtime(true);


/// connecting to database
  $serverUsername = getenv('USERNAME');
  require_once "/home/{$serverUsername}/domains/fpps4.net/config/config.php"; // import config file

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
  $searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
  $tags = isset($_GET['tag']) ? $_GET['tag'] : '';
  $page = isset($_GET['page']) ? $_GET['page'] : 1;
  $oldest = isset($_GET['oldest']) ? $_GET['oldest'] : false;
  $giveStats = isset($_GET['stats']) ? true : false;

  $searchQuery = filter_var($searchQuery, FILTER_SANITIZE_STRING);
  $tags = filter_var($tags, FILTER_SANITIZE_STRING);
  $page = filter_var($page, FILTER_VALIDATE_INT);
  $oldest = filter_var($oldest, FILTER_VALIDATE_BOOLEAN);
  $giveStats = filter_var($giveStats, FILTER_VALIDATE_BOOLEAN);


/// defining stuff lmao
  $maxResults = (!empty($searchQuery)) ? 10 : 20;
  $pageNumber = ($page - 1) * $maxResults;
  $sqlQuery = "SELECT * FROM issues";
  $params = array();
  $conditions = array();


/// if there is an search query in the request
  if (!empty($searchQuery)) {
    $searchQuery = strtolower($searchQuery);
    $conditions[] = strpos($searchQuery, 'cusa') ? "cusaCode LIKE :searchQuery" : "title LIKE :searchQuery";
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


/// im too lazy to write a comment
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
  echo "<div id='totalPages' data='{$totalPages}'></div>";
  if ($totalIssuesAmount < 1) {
    echo "<p class='noResultsText'>No results found based on your query</p>";
    echo "<p class='noResultsEmoji'>¯\_(ツ)_/¯</p>";
  }

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
  foreach ($result as $row) {
    $id = $row['id'];
    $cusaCode = $row['cusaCode'];
    $clean_title = $row['title'];
    $tags = $row['tags'];
    $updatedAt = $row['updatedDate'];
    
    $dataCusa = '';
    $avifIconURL = "../images/CUSA/{$cusaCode}.avif";
    if (!file_exists($avifIconURL)) {
      $dataCusa = null; // Skip image
    } else {
      $dataCusa = $cusaCode;
    }

    echo "<div class='gameContainer'>";
    echo "<a data-id='$id' class='gameImageLink'>";
    echo "<img class='gameImage' data-cusa='$dataCusa' alt='$clean_title'>";
    echo "</a>";
    echo "<div class='gameSeparator'></div>";
    echo "<div class='gameDetails'>";
    echo "<p class='gameName'>$clean_title</p>";
    echo "<p data='$updatedAt' class='gameCusa'>$cusaCode</p>";
    echo "<p class='gameStatus'>$tags</p>";
    echo "</div>";
    echo "</div>";
  }


/// stats on the compatibility list
  if ($giveStats === true) {
    $availableTags = ['N/A', 'Nothing', 'Boots', 'Menus', 'Ingame', 'Playable'];
    $tagPercentages = [];

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM issues");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

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

    foreach ($tagPercentages as $tag => $percentage) {
      $percentage += $amount;
      $percentage = round($percentage, 2);
      echo "<div id='$tag' data='$percentage+$tagCount[$tag]'></div>";
    }
  }


/// end
  $conn = null; //exit connection
  $endTime = microtime(true);
  $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
  echo "<h4 class= 'totalTimeText'>$totalIssuesAmount results in {$executionTime}ms </h4>";
?>