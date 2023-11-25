<?php
  $startTime = microtime(true);
  echo "Started on: " . date("H:i d/m/Y") . "<br><br>";
  $dir = dirname(__DIR__, 2);
  require_once "$dir/_config/config.php"; // import config file

  if ((isset($_GET[ARGUMENT_SECRET]) && $_GET[ARGUMENT_SECRET] === ACCESS_SECRET) || (isset($argv[1]) && $argv[1] === ARGUMENT_SECRET)) {
    // echo"<style>@import url('https://fonts.googleapis.com/css2?family=Rubik:wght@400;500&display=swap'); * {font-family: 'Rubik', sans-serif;font-weight: 400;}</style>";
    function ErrorHandler($errLvl, $errMesg, $errFile, $errLine) {
      $position = strpos($errMesg, "/");
      $errMesg = $position !== false ? substr($errMesg,0, $position) : $errMesg; // check for an "/" and remove stuff

      switch ($errLvl) {
        case E_ERROR || E_USER_ERROR:
          $error = "Error: $errMesg [line: $errLine]";
          break;
        case E_WARNING || E_USER_WARNING:
          $error = "Warning: $errMesg [line: $errLine]";
          break;
        case E_NOTICE || E_USER_NOTICE:
          $error = "Notice: $errMesg [line: $errLine]";
          break;
        default:
          $error = "Unknown error: $errMesg [line: $errLine]";
        break;
      }
      $fullError = "<span style='font-weight: 500;'>Something went wrong => $error </span><br>";
      throw new Exception("$fullError");
    }
    set_error_handler("ErrorHandler");

    $host = DATABASE_HOST;
    $database = DATABASE_NAME;
    $username = DATABASE_USERNAME;
    $password = DATABASE_PASSWORD;

    $githubToken = GITHUB_TOKEN;
    $tmdbHash = TMDB_HASH;
    $HBheader = stream_context_create(["http" => ["header" => "User-Agent: " . HB_USERAGENT ."\r\n"]]);
    
    // get homebrew db for images
    try {
      if (date("i") > 58 || date("i") < 3) {
        $response = file_get_contents("https://api.pkg-zone.com/api.php?db_check_hash=true", false, $HBheader);
        $hash = json_decode($response)->hash; //get hash from json
        $md5Hash = file_exists("$dir/_config/HBstore.db") ? md5_file("$dir/_config/HBstore.db") : "0";

        if ($hash !== $md5Hash) {
          $fileContent = file_get_contents("https://api.pkg-zone.com/store.db", false, $HBheader);
          file_put_contents("$dir/_config/HBstore.db", $fileContent);
        }
      }
    } catch (Exception $e) {
      print($e);
      file_exists("$dir/_config/HBstore.db") ? print("Ignoring for now <br>") : die("Critical error, stopping <br>");

    } finally {print("Homebrew database download task "); date("i") > 58 || date("i") < 3 ? print("completed! <br>") : print("skipped! <br>");}


    // database connection and table creation
    try {
      $homebrewDB = new PDO("sqlite:$dir/_config/HBstore.db");
      $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
      $conn->query("DROP TABLE IF EXISTS newIssues");

      // creating tables
      $sqlQuerys = [
        "CREATE TABLE IF NOT EXISTS issues (
          id INT(5) PRIMARY KEY,
          code VARCHAR(10),
          title VARCHAR(130),
          tags VARCHAR(200),
          type VARCHAR(6),
          updatedDate VARCHAR(12),
          createdDate VARCHAR(12)
        )",
        "CREATE TABLE IF NOT EXISTS newIssues (
          id INT(5) PRIMARY KEY,
          code VARCHAR(10),
          title VARCHAR(130),
          tags VARCHAR(200),
          type VARCHAR(6),
          updatedDate VARCHAR(12),
          createdDate VARCHAR(12)
        )",
        "CREATE TABLE IF NOT EXISTS GameSkips (
          code VARCHAR(130) PRIMARY KEY
        )"
      ];

      foreach ($sqlQuerys as $sql) {
        $conn->exec($sql);
      }

    } catch (Exception $e) {
      print($e);
      die("Critical error, stopping <br>");
    } finally {echo"Database connection and table creation task completed! <br>";}


    function githubRequest(string $token, int $count, string $url) {
      $curl_handle = curl_init();
      curl_setopt($curl_handle, CURLOPT_URL, $url);
      curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github+json', 'Authorization: Bearer '.$token));
      curl_setopt($curl_handle, CURLOPT_USERAGENT, "fpPS4.net");
      curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
      $data = curl_exec($curl_handle);
      curl_close($curl_handle);

      $count++;
      return json_decode($data, true);
    }

    function imageDownloader(string $url, $header, string $location) {
      $httpsUrl = str_replace('http://', 'https://', $url);
      $imageData = file_get_contents($httpsUrl, false, $header);

      $imagick = new Imagick();
      if ($imagick->readImageBlob($imageData)) {
          $imageFormat = $imagick->getImageFormat();
          if ($imageFormat == 'JPEG' || $imageFormat == 'PNG') {
              $imagick->setImageFormat('avif');
              $imagick->setCompressionQuality(75);
              $imagick->setImageProperty('avif:effort', '10');
              $imagick->setImageProperty('avif:speed', '1');
              $imagick->thumbnailImage(256, 256, true);
              $imagick->stripImage();
              $imagick->writeImage($location); // save the image
          }
      }
      $imagick->clear();
      $imagick->destroy();
    }

    try {
      $ghRequests = 0;
      $issueCount = githubRequest($githubToken, $ghRequests, "https://api.github.com/repos/red-prig/fpps4-game-compatibility")['open_issues_count'];
      $pageCount = ceil($issueCount / 100);
      echo "Total Issues: " . $issueCount . "<br>";
      echo "Total Pages: " . $pageCount . "<br><br>";
      $issuesProcessed = 0;
      $noImage = 0;

      for ($page = 1; $page <= $pageCount; $page++) {
        $issues = githubRequest($githubToken, $ghRequests, "https://api.github.com/repos/red-prig/fpps4-game-compatibility/issues?page=$page&per_page=100&state=open&direction=ASC");

        foreach ($issues as $issue) {
          $id = filter_var($issue['number'], FILTER_VALIDATE_INT);
          $title = htmlspecialchars($issue['title']);
          $tags = htmlspecialchars(implode(', ', array_column($issue['labels'], 'name')));
          $createdDate = htmlspecialchars(date('d/m/Y', strtotime($issue['created_at'])));
          $updatedDate = htmlspecialchars(date('d/m/Y', strtotime($issue['updated_at'])));
          $code = "";
          $type = "";

          // skip invalid issues
          if (str_contains($tags,"question") || str_contains($tags,"invalid")) {
            continue;
          }

          // look for an code in the title, for example: CUSA12345 or NPXS00000 and do some processing
          preg_match('/[a-zA-Z]{4}[0-9]{5}/', $title, $matches);
          if ($matches) {
            str_starts_with($matches[0], "CUSA") ? ($code = $matches[0]) && ($type = "CUSA") : "";
            str_contains($tags, "app-homebrew") ? ($code = $matches[0]) && ($type = "HB") : "";
            str_contains($tags, "app-system-fw505") ? ($code = $matches[0]) && ($type = "SYS") : "";
            str_contains($tags, "app-ps2game") ? ($code = "PS2 GAME") : "";
            $title = str_replace(["- " . $matches[0], "-" . $matches[0], $matches[0]], "", $title);
          } else {
            str_contains($tags, "app-homebrew") ? ($code = "HOMEBREW") && ($type = "HB") : "";
          }
          
          $title = str_ireplace(["(Homebrew)", "- HOMEBREW", "Homebrew", "[]"], "", $title);
          $title = rtrim($title, " -");

          // if (empty($code)) {
          //   echo "skipping: $title <br>";
          //   continue;
          // }
          $issuesProcessed++;

          // Now put it into a database :3
          try {

            // tag bullshit AHHHHH
            if ($tags) {
              $tags = str_ireplace(["status-", "app-homebrew", "app-system-fw505", "app-ps2game"], '', $tags);
            
              $priority = [
                'playable',
                'ingame',
                'menus',
                'boots',
                'nothing'
              ];
              
              $tagArray = array_map('trim', explode(', ', $tags));
              
              // headache, prioritizes tags
              usort($tagArray, function ($a, $b) use ($priority) {
                $priorityA = array_search($a, $priority);
                $priorityB = array_search($b, $priority);
            
                $priorityA = ($priorityA === false) ? 0 : $priorityA;
                $priorityB = ($priorityB === false) ? 0 : $priorityB;
            
                return $priorityB <=> $priorityA;
              });

                // Take the best tag and capitalize it
                // $highestPriority = ucfirst($tagArray[0]);
                $tagArray = array_slice($tagArray, 0, 1);

                // // Remove tags that are in the priority array
                // $tagArray = array_filter($tagArray, function ($tag) use ($priority) {
                //   return !in_array($tag, $priority);
                // });

                // // Add the best tag to the beginning of the array
                // array_unshift($tagArray, $highestPriority);

                $tags = ucfirst(implode(', ', $tagArray));
                
            } else {$tags = "N/A";}


            $insertQuery = "INSERT INTO newIssues (id, code, title, tags, type, updatedDate, createdDate) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);

            $stmt->execute([$id, $code, $title, $tags, $type, $updatedDate, $createdDate]);
          } catch (Exception $e) {
            echo("Error inserting issue: $e");
          }


          // Now download images :3
          try {
            $cusaDir = "$dir/public_html/_images/CUSA/$code.avif";
            $hbDir = "$dir/public_html/_images/HB/$title.avif";

            if ($type === "CUSA" && !file_exists($cusaDir)) {
              $stmt = $conn->prepare('SELECT * FROM GameSkips WHERE code = :code COLLATE utf8mb4_general_ci'); // check if it needs to be skipped
              $stmt->execute(['code' => $code]);
              $result = $stmt->fetch(PDO::FETCH_ASSOC);
              
              if(!$result)
              {
                $key = hex2bin($tmdbHash);
                $hash = strtoupper(hash_hmac("sha1", "{$code}_00", $key));
                $url = "https://tmdb.np.dl.playstation.net/tmdb2/{$code}_00_{$hash}/{$code}_00.json";
                $userAgent = "Mozilla/5.0 (PlayStation; PlayStation 4/11.00) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Safari/605.1.15";
                $PS4header = stream_context_create(["http" => ["header" => "User-Agent: $userAgent\r\n"]]); // ps4 useragent cuz im silly
                $response = file_get_contents($url, false, $PS4header);
                $data = json_decode($response, true);
                $url = $data['icons'][0]['icon'];
  
                usleep(rand(200000, 500000)); // delay between 200 - 500 ms
                imageDownloader($url, $PS4header, $cusaDir);
              }
            } else if ($type === "HB" && !file_exists($hbDir)) {
              $stmt = $conn->prepare('SELECT * FROM GameSkips WHERE code = :title COLLATE utf8mb4_general_ci'); // check if it needs to be skipped
              $stmt->execute(['title' => $title]);
              $result = $stmt->fetch(PDO::FETCH_ASSOC);

              if(!$result) {
                $stmt = $homebrewDB->prepare('SELECT image FROM homebrews WHERE name = :title COLLATE NOCASE');
                $stmt->execute(['title' => $title]);
                $url = $stmt->fetch()[0];

                usleep(rand(200000, 500000)); // delay between 200 - 500 ms
                imageDownloader($url, $HBheader, $hbDir);
              }
            }
          } catch (Exception $e) {
            $noImage++;
            echo "No image found for: $title <br>";

            // insert issue in database to be skipped.
            $insertQuery = "INSERT INTO GameSkips (code) VALUES (:value1)";
            $stmt = $conn->prepare($insertQuery);

            if ($type === "CUSA") {
              $stmt->execute(['value1' => $code]);
            } else if ($type === "HB") {
              $stmt->execute(['value1' => $title]);
            }
          }
        }
      }
  } catch (Exception $e) {
    echo $e;
  } finally {
    print("<br>$issuesProcessed issues processed! <br>");
    print("$noImage images not found! <br>");
    print("Issue processing, Image downloading and Database insertion task completed! <br>");
  }

  // Move newIssues table to normal issues table
  try {
    // Check the newIssues table
    $result = $conn->query("SELECT COUNT(*) FROM newIssues");
    $newTotal = $result->fetchColumn();

    $result2 = $conn->query("SELECT COUNT(*) FROM issues");
    $oldTotal = $result2->fetchColumn();

    // remove 5% from old issue cound
    $minNumber = $oldTotal - (0.05 * $oldTotal);

    if ($newTotal > $minNumber) {
      // empty issues table
      $conn->query("DELETE FROM issues");

      // copy newIssues to issues table
      $conn->query("INSERT INTO issues (id, code, title, tags, type, updatedDate, createdDate) SELECT id, code, title, tags, type, updatedDate, createdDate FROM newIssues");

      // drop newIssues table
      $conn->query("DROP TABLE IF EXISTS newIssues");
    } else {
      print "<br>Something is wrong with newIssues, skipping update.";
    }
    
  } catch (Exception $e) {
    echo $e;
  } finally {print("Database transfer task completed! <br>");}
  

    $conn = null;
    $homebrewDB = null;
    echo "<br>" . round((microtime(true) - $startTime) * 1000, 2) . "ms";

  } else {
    http_response_code(404); 
    include("$dir/document_errors/404.html");
  }
?>