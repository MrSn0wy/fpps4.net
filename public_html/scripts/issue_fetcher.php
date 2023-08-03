<?php
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

try {
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
        cusaCode VARCHAR(10) PRIMARY KEY
    )";

    $conn->exec($sqlOld);
    $conn->exec($sqlNew);
    $conn->exec($sqlSkips);
    echo "Tables created successfully.<br>";
    
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

    // Remove CUSA code from the title
    $clean_title = preg_replace('/\b' . preg_quote($cusaCode, '/') . '\b/', '', $title);
    $clean_title = preg_replace('/\s+-\s+/', ' - ', $clean_title);
    $clean_title = rtrim($clean_title, '- ');
    $clean_title = rtrim($clean_title, ' ');
    $clean_title = rtrim($clean_title, '[]');

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
        // Define the order of preference for tags
        $tagOrder = [
            'status-playable',
            'status-ingame',
            'status-menus',
            'status-boots',
            'status-nothing'
        ];

        // Sort the tags based on their preference
        usort($tagNames, function ($a, $b) use ($tagOrder) {
            return array_search($a, $tagOrder) <=> array_search($b, $tagOrder);
        });

        // Take the first (best) tag
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
function get_image($cusaCode, $conn, $serverUsername) {
    global $tmdbHash;
    $key = hex2bin($tmdbHash);
    $hashme = $cusaCode . '_00';
    $hash = strtoupper(hash_hmac('sha1', $hashme, $key));
    $url = "https://tmdb.np.dl.playstation.net/tmdb2/{$cusaCode}_00_{$hash}/{$cusaCode}_00.json";

    $headers = get_headers($url);
    if ($headers !== false && strpos($headers[0], '200') !== false) {
        $response = file_get_contents($url);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['icons']) && is_array($data['icons']) && count($data['icons']) > 0) {
                $iconUrl = $data['icons'][0]['icon'];
                $httpsIconUrl = str_replace('http://', 'https://', $iconUrl);
                $imagick = new Imagick();

                $avifIconURL = "/home/{$serverUsername}/domains/fpps4.net/public_html/beta/images/CUSA/{$cusaCode}.avif";
                $result = null;
                if ($imagick->readImage($httpsIconUrl)) {
                    $imageFormat = $imagick->getImageFormat();
                    if ($imageFormat == 'JPEG' || $imageFormat == 'PNG') {
                        $imagick->setImageFormat('avif');
                        $imagick->setCompressionQuality(75);
                        $imagick->setImageProperty('avif:effort', '10');
                        $imagick->setImageProperty('avif:speed', '8');
                        $imagick->thumbnailImage(128, 128, true);
                        $imagick->setImageProperty('avif:strip', '');
                        // Compress and save the image
                        if ($imagick->writeImage($avifIconURL)) {
                            $result = $avifIconURL;
                        } else {
                            $result = null;
                        }
                    } else {
                        $result = null;
                    }
                } else {
                    $result = null;
                }
                $imagick->clear();
                $imagick->destroy();
                return $result;
            }
        }
    }
    // Write the skipped $cusaCode to the database
    $skippedCode = $conn->quote($cusaCode);
    $insertQuery = "INSERT INTO gameSkips (cusaCode) VALUES ($skippedCode)";
    $conn->exec($insertQuery);
    return null;
}
//- Images logic

$gh_api_total = 0;
$open_issues_count = get_open_issues_count($gh_api_total);
$total_pages = ceil($open_issues_count / 100);
$total_processed = 0;
$total_skipped = 0;
$CUSA_total_skipped = 0;
$images_downloaded = 0;
$images_skiped = 0;

for ($page = 1; $page <= $total_pages; $page++) {
    $issues_data = get_open_issues($page);
    $issues_array = json_decode($issues_data, true);

    foreach ($issues_array as $issue) {
        $title = $issue['title'];
        $cusaCode = extract_cusaCode($title);
        $avifIconURL = "/home/{$serverUsername}/domains/fpps4.net/public_html/beta/images/CUSA/{$cusaCode}.avif";

        // Check if cusa code is empty
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
                $query = "SELECT cusaCode FROM GameSkips WHERE cusaCode = :cusaCode";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':cusaCode', $cusaCode, PDO::PARAM_STR);
                $stmt->execute();
            
                if ($stmt->rowCount() > 0) {
                    $images_skiped++;
                    continue;
                } else {
                    get_image($cusaCode, $conn, $serverUsername); // Download image
                    $images_downloaded++;
                }
            } else {
                $images_skiped++;
            }
            
        } else {
            $CUSA_total_skipped++;
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
print "<br>Total duplicates: " . $total_skipped;
print "<br>Total images downloaded: " . $images_downloaded;
print "<br>Total images skipped: " . $images_skiped;
print "<br>Total github api requests: " . $gh_api_total;

// Fake 404 page vv
} else {
    http_response_code(404); 
    include("/home/{$serverUsername}/domains/fpps4.net/public_html/404.php");
}?>