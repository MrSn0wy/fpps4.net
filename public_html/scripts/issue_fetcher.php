<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$serverUsername = getenv('USERNAME');
require_once "/home/{$serverUsername}/domains/fpps4.net/config/config.php"; // import config file
$validSecret = ACCESS_SECRET;
$validArgument = ARGUMENT_SECRET;

// The secret query parameter is provided and matches the valid secret
if ((isset($_GET[$validArgument]) && $_GET[$validArgument] === $validSecret) || (isset($argv[1]) && $argv[1] === $validSecret)) {

$host = DATABASE_HOST;
$username = DATABASE_USERNAME;
$password = DATABASE_PASSWORD;
$database = DATABASE_NAME;
$githubToken = GITHUB_TOKEN;
$tmdbHash = TMDB_HASH;
$homebrewUseragent = HB_USERAGENT;

try {
    // get homebrew db for images
    $header = stream_context_create(["http" => ["header" => "User-Agent: $homebrewUseragent\r\n"]]);
    $response = file_get_contents("https://api.pkg-zone.com/api.php?db_check_hash=true", false, $header);
    if ($response !== false) {
        $data = json_decode($response);
        $hash = $data->hash;
        $md5Hash = md5_file("/home/{$serverUsername}/domains/fpps4.net/public_html/scripts/HBstore.db"); // will give warning on first download
        if ($hash === $md5Hash) {
            echo("the db is the same! response: $hash local: $md5Hash <br>");
        } else {
            echo("the db is diffrent! response: $hash local: $md5Hash downloading db <br>");
            $fileContent = file_get_contents("https://api.pkg-zone.com/store.db", false, $header);
            if ($fileContent !== false) {
                file_put_contents("/home/{$serverUsername}/domains/fpps4.net/public_html/scripts/HBstore.db", $fileContent) !== false ?  die("DB downloaded and saved successfully.<br>") : die("Error saving the downloaded DB.<br>");
            } else {
                die("Error downloading the DB from the URL.");
            }
        }
    } else {
        echo("Error verifying hash, ignoring for now.");
    }

    $dbFile = "/home/{$serverUsername}/domains/fpps4.net/public_html/scripts/HBstore.db";
    try {
        $homebrewDB = new PDO("sqlite:$dbFile");
        echo "Connected to the database successfully! <br>";
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage() . "<br>";
    }

    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->query("DROP TABLE IF EXISTS newIssues");

    // creating tables
    $sqlOld = "CREATE TABLE IF NOT EXISTS issues (
        id INT(5) PRIMARY KEY,
        cusaCode VARCHAR(10),
        title VARCHAR(130),
        tags VARCHAR(12),
        updatedDate VARCHAR(12)
    )";

    $sqlNew = "CREATE TABLE IF NOT EXISTS newIssues (
        id INT(5) PRIMARY KEY,
        cusaCode VARCHAR(10),
        title VARCHAR(130),
        tags VARCHAR(12),
        updatedDate VARCHAR(12)
    )";

    $sqlSkips = "CREATE TABLE IF NOT EXISTS GameSkips (
        code VARCHAR(130) PRIMARY KEY
    )";

    $conn->exec($sqlOld);
    $conn->exec($sqlNew);
    $conn->exec($sqlSkips);
    echo "Tables created successfully.<br>";
    // die("Tables created successfully.<br>");
    
} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
//- creating tables

function get_open_issues_count() {
    global $githubToken;
    global $gh_api_total;

    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, 'https://api.github.com/repos/red-prig/fpps4-game-compatibility');
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github+json', 'Authorization: Bearer '.$githubToken));
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "php/curl");
    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    $data = json_decode($buffer, true);

    $gh_api_total++;
    echo "Open issues counted successfully.<br>";
    return $data['open_issues_count'];
}

function get_open_issues($page) {
    global $githubToken;
    global $gh_api_total;

    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, 'https://api.github.com/repos/red-prig/fpps4-game-compatibility/issues?page='.$page.'&per_page=100&state=open&direction=ASC');
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github+json', 'Authorization: Bearer '.$githubToken));
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "php/curl");
    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    
    // echo "processed page $page.<br>";
    $gh_api_total++;
    return $buffer;
}

function insert_issue($issue, $conn) {
    $id = $issue['number'];
    $title = $issue['title'];
    $cusaCode = extract_cusaCode($title);
    $tags = implode(', ', array_column($issue['labels'], 'name'));
    $updatedDate = date('d/m/Y', strtotime($issue['updated_at']));

    $id = filter_var($id, FILTER_VALIDATE_INT);
    $title = filter_var($title,  FILTER_SANITIZE_STRING);
    $cusaCode = filter_var($cusaCode,  FILTER_SANITIZE_STRING);
    $tags = filter_var($tags,  FILTER_SANITIZE_STRING);
    $updatedDate = filter_var($updatedDate,  FILTER_SANITIZE_STRING);

    // Handle homebrews and remove CUSA code from the title
    if (in_array("app-homebrew", array_column($issue['labels'], 'name'))) {
        $cusaCode = "HOMEBREW";
        $clean_title = preg_replace('/\s-\s.*$/', '', $title);
        $clean_title = trim(explode('(Homebrew)', $clean_title)[0]);
        $clean_title = trim(explode('Homebrew', $clean_title)[0]);
        //$clean_title = trim(explode('Game', $clean_title)[0]);
        $clean_title = rtrim($clean_title, ' ');
    } else if (in_array("app-system-fw505", array_column($issue['labels'], 'name'))){
        $cusaCode = "SYSTEM APP";
        $clean_title = preg_replace('/\s-\s.*$/', '', $title);
    } else if (in_array("app-ps2game", array_column($issue['labels'], 'name'))){
        $cusaCode = "PS2 GAME";
        $clean_title = preg_replace('/\s-\s.*$/', '', $title);
    } else {
        $clean_title = preg_replace('/\b' . preg_quote($cusaCode, '/') . '\b/', '', $title);
        $clean_title = preg_replace('/\s+-\s+/', ' - ', $clean_title);
        $clean_title = rtrim($clean_title, '- ');
        $clean_title = rtrim($clean_title, ' ');
        $clean_title = rtrim($clean_title, '[]');
    }

    // Filter labels and give them the correct names
    $filteredTags = array_filter($issue['labels'], function ($label) {
        return in_array($label['name'], ['status-nothing', 'status-boots', 'status-ingame', 'status-menus', 'status-playable']);
    });

    $tagNames = array_map(function ($label) {
        return ucwords(str_replace('status-', '', $label['name']));
    }, $filteredTags);

    if (empty($tagNames)) {
        $tagNames = ['N/A'];
    } else {
        // Define the order of the tags
        $tagOrder = [
            'status-playable',
            'status-ingame',
            'status-menus',
            'status-boots',
            'status-nothing'
        ];

        // Sort the tags
        usort($tagNames, function ($a, $b) use ($tagOrder) {
            return array_search($a, $tagOrder) <=> array_search($b, $tagOrder);
        });

        // Take the best tag
        $tagNames = array_slice($tagNames, 0, 1);
    }

    $tags = implode(', ', $tagNames);

    // Use prepared statement to insert data into the database
    $insert_query = "INSERT INTO newIssues (id, cusaCode, title, tags, updatedDate) VALUES (:id, :cusaCode, :clean_title, :tags, :updatedDate)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':cusaCode', $cusaCode, PDO::PARAM_STR);
    $stmt->bindParam(':clean_title', $clean_title, PDO::PARAM_STR);
    $stmt->bindParam(':tags', $tags, PDO::PARAM_STR);
    $stmt->bindParam(':updatedDate', $updatedDate, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        die("Error inserting issue: " . $stmt->errorInfo()[2]);
    }
}

function extract_cusaCode($title) {
    preg_match('/CUSA[0-9]{5,}/', $title, $matches);
    if (!empty($matches)) {
        return $matches[0];
    }

    return null;
}

// Images logic
function get_image($cusaCode, $homebrewDB) {
    global $issue;
    global $serverUsername;
    global $conn;
    global $homebrewUseragent;
    //global $homebrewDB;
    $iconUrl = '';
    $avifIconURL = '';
    echo "           DEBUG: $cusaCode";

    try {
            if (in_array("app-homebrew", array_column($issue['labels'], 'name'))) {

                $query = "SELECT image FROM homebrews WHERE name = :name";
                $statement = $homebrewDB->prepare($query);
                $statement->bindParam(':name', $cusaCode);
                $statement->execute();
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                //var_dump("image link: $result <br>");
                //echo "image link: $result <br>" ;
//                if ($result !== false) {
                    if (!empty($result)) {
                        $iconUrl = $result['image'];
                        //echo "Image link: $iconUrl<br>";
                        var_dump("image link: $iconUrl <br>");
                        $avifIconURL = "/home/{$serverUsername}/domains/fpps4.net/public_html/images/HOMEBREW/{$cusaCode}.avif";
                        $header = stream_context_create(["http" => ["header" => "User-Agent: $homebrewUseragent\r\n"]]);
                        $httpsIconUrl = str_replace('http://', 'https://', $iconUrl);
                        $imageData = file_get_contents($httpsIconUrl, false, $header);
                    } else {
                        echo "No record found for the given name.<br>";
                        throw new Exception();
                    }
//                } else {throw new Exception();}
            } else {
                global $tmdbHash;
                $key = hex2bin($tmdbHash);
                $hashme = $cusaCode . '_00';
                $hash = strtoupper(hash_hmac('sha1', $hashme, $key));
                $url = "https://tmdb.np.dl.playstation.net/tmdb2/{$cusaCode}_00_{$hash}/{$cusaCode}_00.json";
                $userAgent = 'Mozilla/5.0 (PlayStation; PlayStation 4/10.71) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Safari/605.1.15';
                $PS4header = stream_context_create(["http" => ["header" => "User-Agent: $userAgent\r\n"]]); // ps4 useragent cuz im silly
                $headers = get_headers($url, false, $PS4header);
                if ($headers !== false && strpos($headers[0], '200') !== false) {
                    $response = file_get_contents($url, false, $PS4header);
                    if ($response !== false) {
                        $data = json_decode($response, true);
                        if (isset($data['icons']) && is_array($data['icons']) && count($data['icons']) > 0) {
                            $iconUrl = $data['icons'][0]['icon'];
                            $avifIconURL = "/home/{$serverUsername}/domains/fpps4.net/public_html/images/CUSA/{$cusaCode}.avif";
                            $httpsIconUrl = str_replace('http://', 'https://', $iconUrl);
                            $imageData = file_get_contents($httpsIconUrl, false, $PS4header);
                        } else {throw new Exception();}
                    } else {throw new Exception();}
                } else {throw new Exception();} 
            }


            $imagick = new Imagick();
            if ($imagick->readImageBlob($imageData)) {
                $imageFormat = $imagick->getImageFormat();
                if ($imageFormat == 'JPEG' || $imageFormat == 'PNG') {
                    $imagick->setImageFormat('avif');
                    $imagick->setCompressionQuality(75);
                    $imagick->setImageProperty('avif:effort', '10');
                    $imagick->setImageProperty('avif:speed', '8');
                    $imagick->thumbnailImage(128, 128, true);
                    $imagick->setImageProperty('avif:strip', '');
                    $imagick->writeImage($avifIconURL); // save the image
                }
            }
            $imagick->clear();
            $imagick->destroy();

        } catch (Exception $e) {
                echo "<p style='color: red;'>$cusaCode got an error while downloading, $e </p><br>";
                    $columnName = "code";
                    $insertQuery = "INSERT INTO GameSkips ($columnName) VALUES (:value1)";
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bindParam(':value1', $cusaCode, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        die("Error inserting issue: " . $stmt->errorInfo()[2]);
                    }          
        }
}
//- Images logic

$gh_api_total = 0;
$open_issues_count = get_open_issues_count();
$total_pages = ceil($open_issues_count / 100);
$total_processed = 0;
$total_skipped = 0;
$CUSA_total_skipped = 0;
$images_downloaded = 0;
$images_skiped = 0;
$homebrewProcessed = 0;
$systemProcessed = 0;
$ps2Processed = 0;
$HB_images_downloaded = 0;
$HB_images_skiped = 0;

for ($page = 1; $page <= $total_pages; $page++) {
    $issues_data = get_open_issues($page);
    $issues_array = json_decode($issues_data, true);

    foreach ($issues_array as $issue) {
        $title = $issue['title'];
        $cusaCode = extract_cusaCode($title);
        $avifIconURL = "/home/{$serverUsername}/domains/fpps4.net/public_html/images/CUSA/{$cusaCode}.avif";

        // Check if cusa code is not empty
        if ($cusaCode) {
            $query = "SELECT cusaCode FROM newIssues WHERE cusaCode = :cusaCode";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':cusaCode', $cusaCode, PDO::PARAM_STR);
            $stmt->execute();
            $check_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($check_result) > 0) {
                $total_skipped++;
                // echo "Duplicate found: " . $cusaCode . "<br>"; // echo duplicates
                continue;
            }
            $total_processed++;
            insert_issue($issue, $conn);

            if (!file_exists($avifIconURL)) {   // check for images
                // ECHO "<br> DEBUG: I AM GETTING CHECKED. $cusaCode <br>";
                $query = "SELECT code FROM GameSkips WHERE code = :code";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':code', $cusaCode, PDO::PARAM_STR);
                $stmt->execute();
                // ECHO "<br> DEBUG: I HAVE BEEN CHECKED. $cusaCode <br>";
            
                if ($stmt->rowCount() > 0) {
                    $images_skiped++;
                    continue;
                    // ECHO "<br> DEBUG: I HAVE BEEN SKIPPED. $cusaCode <br>";
                } else {
                    get_image($cusaCode, $homebrewDB); // Download image
                    $images_downloaded++;
                    // ECHO "<br> DEBUG: I HAVE BEEN DOWNLOADED. $cusaCode <br>";
                }
            } else {
                $images_skiped++;
            }

            // HOMEBREW GAMES/APPS
        } else if (in_array("app-homebrew", array_column($issue['labels'], 'name'))) {
            $hb_title = preg_replace('/\s-\s.*$/', '', $title);
            $hb_title = trim(explode('(Homebrew)', $hb_title)[0]);
            $hb_title = trim(explode('Homebrew', $hb_title)[0]);
            //$hb_title = trim(explode('Game', $hb_title)[0]);
            $hb_title = rtrim($hb_title, ' ');

            $query = "SELECT title FROM newIssues WHERE title = :title";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':title', $hb_title, PDO::PARAM_STR);
            $stmt->execute();
            $checkTitle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($checkTitle) > 0) {
                $total_skipped++;
                continue;
            }

            if (!file_exists("/home/{$serverUsername}/domains/fpps4.net/public_html/images/HOMEBREW/{$hb_title}.avif")) {  // check for images
                // $query = "SELECT cusaCode FROM GameSkips WHERE homeBrew = :homeBrew";
                $query = "SELECT code FROM GameSkips WHERE code = :code";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':code', $hb_title, PDO::PARAM_STR);
                $stmt->execute();
                $check_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($check_result) > 0) {
                    $HB_images_skiped++;
                    // echo "$hb_title is skipped <br>";
                } else {
                    get_image($hb_title, $homebrewDB); // Download image
                    $HB_images_downloaded++;
                    // echo "$hb_title getting downloaded <br>";
                }
            } else {
                $HB_images_skiped++;
                // echo "$hb_title exists <br>";
            }

            insert_issue($issue, $conn);
            $total_processed++;
            $homebrewProcessed++;

        } else if (in_array("app-system-fw505", array_column($issue['labels'], 'name'))){
            $query = "SELECT title FROM newIssues WHERE title = :title";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->execute();
            $checkTitle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($checkTitle) > 0) {
                $total_skipped++;
                continue;
            }
            $total_processed++;
            $systemProcessed++;
            insert_issue($issue, $conn);
            
        } else if (in_array("app-ps2game", array_column($issue['labels'], 'name'))){
            $query = "SELECT title FROM newIssues WHERE title = :title";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->execute();
            $checkTitle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($checkTitle) > 0) {
                $total_skipped++;
                continue;
            }
            $total_processed++;
            $ps2Processed++;
            insert_issue($issue, $conn);
        } else {
            $CUSA_total_skipped++;
            //echo "<a href='https://github.com/red-prig/fpps4-game-compatibility/issues/" . $issue['number'] . "' target='_blank'>https://github.com/red-prig/fpps4-game-compatibility/issues/" . $issue['number'] . "</a><br>";
            continue;
        }
    }
}

// Check if newIssues table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM newIssues");
if ($result === false) {
    die("Error checking table: " . $conn->errorInfo()[2]);
}
echo "newIssues checked succesfully<br>";

$data_count = $result->fetchColumn();
if ($data_count > 0) {
    echo "newIssues is full<br>";

    $dropOldTable = $conn->query("DELETE FROM issues");
    if ($dropOldTable === false) {
        die("Error dropping table: " . $conn->errorInfo()[2]);
    }
    echo "oldIssues emptied succesfully<br>";

    // move new issues to old issues + cleanup
    $moveResult = $conn->query("INSERT INTO issues (id, cusaCode, title, tags, updatedDate) SELECT id, cusaCode, title, tags, updatedDate FROM newIssues");
    if ($moveResult === false) {
        die("Error moving data to issues table: " . $conn->errorInfo()[2]);
    }
    echo "Moved all issues succesfully<br>";

    // drop newIssues table
    $dropNewTable = $conn->query("DROP TABLE IF EXISTS newIssues");
    if ($dropNewTable === false) {
        die("Error dropping table: " . $conn->errorInfo()[2]);
    }
    echo "newIssues emptied succesfully<br>";
} else {
    print "<br>error: No issues found in newIssues. ";
}
$conn = null;

// print debug stuff
print "<br>done :D";
print "<br>Total pages: " . $total_pages;
print "<br>Total open issues: " . $open_issues_count;
print "<br>Total issues without CUSA: " . $CUSA_total_skipped;
print "<br>Total issues processed: " . $total_processed;
print "<br>Homebrew's processed: " . $homebrewProcessed;
print "<br>System Apps processed: " . $systemProcessed;
print "<br>PS2 Games processed: " . $ps2Processed;
print "<br>Total duplicates: " . $total_skipped;
print "<br>Total images downloaded: " . $images_downloaded;
print "<br>Total images skipped: " . $images_skiped;
print "<br>Total homebrew images downloaded: " . $HB_images_downloaded;
print "<br>Total homebrew images skipped: " . $HB_images_skiped;
print "<br>Total github api requests: " . $gh_api_total;

// Fake 404 page vv
} else {
    http_response_code(404); 
    include("/home/{$serverUsername}/domains/fpps4.net/public_html/404.php");
}?>