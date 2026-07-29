<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// ----- Constants for WinGo_1M -----
define('API_URL', "https://draw.ar-lottery01.com/WinGo/WinGo_1M/GetHistoryIssuePage.json?ts=" . time());
define('SAVE_FILE', __DIR__ . "/WinGo_1M.json");

// ----- Load saved history -----
$savedList = [];
if (file_exists(SAVE_FILE)) {
    $content = file_get_contents(SAVE_FILE);
    $history = json_decode($content, true);
    
    if (isset($history['data']['list'])) {
        $savedList = $history['data']['list'];
    } elseif (is_array($history)) {
        $savedList = $history;
    }
}

// ----- Fetch LIVE API result -----
$ch = curl_init(API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
$apiResponse = curl_exec($ch);

if (curl_error($ch)) {
    echo json_encode([
        "status" => "error",
        "message" => "CURL Error: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$apiData = json_decode($apiResponse, true);

if (!$apiData || !isset($apiData["data"]["list"])) {
    // If API fails, return saved data
    if (!empty($savedList)) {
        echo json_encode([
            "status" => "success",
            "history" => $savedList,
            "source" => "cache"
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid API response and no cached data"
        ]);
    }
    exit;
}

$liveList = $apiData["data"]["list"];

// ----- Merge new items into savedList -----
$newItemsAdded = false;
foreach ($liveList as $item) {
    $exists = false;
    foreach ($savedList as $p) {
        if (isset($p["issueNumber"]) && isset($item["issueNumber"]) && $p["issueNumber"] == $item["issueNumber"]) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        // Remove dateTime if exists
        if (isset($item['dateTime'])) {
            unset($item['dateTime']);
        }
        $savedList[] = $item;
        $newItemsAdded = true;
    }
}

// ----- Remove dateTime from all items -----
foreach ($savedList as &$item) {
    if (isset($item['dateTime'])) {
        unset($item['dateTime']);
    }
}
unset($item); // Break reference

// ----- Sort saved list DESC by issueNumber -----
if (!empty($savedList)) {
    usort($savedList, function($a, $b) {
        // Extract numeric part for sorting
        $numA = isset($a['issueNumber']) ? intval($a['issueNumber']) : 0;
        $numB = isset($b['issueNumber']) ? intval($b['issueNumber']) : 0;
        return $numB - $numA;
    });
}

// ----- Limit to only 10 items -----
if (count($savedList) > 10) {
    $savedList = array_slice($savedList, 0, 10);
}

// ----- Save updated file (only if new items added or file doesn't exist) -----
if ($newItemsAdded || !file_exists(SAVE_FILE)) {
    file_put_contents(SAVE_FILE, json_encode($savedList, JSON_PRETTY_PRINT));
}

// ----- Prepare response data -----
$response = [
    "status" => "success",
    "history" => $savedList,
];

// ----- Output as JSON -----
echo json_encode($response, JSON_PRETTY_PRINT);
?>
