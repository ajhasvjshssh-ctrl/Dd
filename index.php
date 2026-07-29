<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// ----- Constants for WinGo_1M -----
define('API_URL', "https://draw.ar-lottery01.com/WinGo/WinGo_1M/GetHistoryIssuePage.json?ts=" . time());
define('SAVE_FILE', __DIR__ . "/WinGo_1M.json");

// ----- Load saved history (Cache) -----
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

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://draw.ar-lottery01.com/',
        'Origin: https://draw.ar-lottery01.com'
    ],
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);

$apiResponse = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

// ----- Handle cURL Connection Error -----
if ($curlError) {
    if (!empty($savedList)) {
        echo json_encode([
            "status" => "success",
            "history" => $savedList,
            "source" => "cache_on_curl_error"
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "CURL Error: " . $curlError
        ], JSON_PRETTY_PRINT);
    }
    exit;
}

// ----- Decode API Response -----
$apiData = json_decode($apiResponse, true);

// ----- Fallback if Upstream API Response is Invalid -----
if (!$apiData || !isset($apiData["data"]["list"])) {
    if (!empty($savedList)) {
        echo json_encode([
            "status" => "success",
            "history" => $savedList,
            "source" => "cache"
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid API response and no cached data",
            "debug_raw_response" => $apiResponse // Displays raw output if API blocks request
        ], JSON_PRETTY_PRINT);
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
unset($item); // Unset reference

// ----- Sort saved list DESC by issueNumber -----
if (!empty($savedList)) {
    usort($savedList, function($a, $b) {
        $numA = isset($a['issueNumber']) ? intval($a['issueNumber']) : 0;
        $numB = isset($b['issueNumber']) ? intval($b['issueNumber']) : 0;
        return $numB - $numA;
    });
}

// ----- Limit to top 10 items -----
if (count($savedList) > 10) {
    $savedList = array_slice($savedList, 0, 10);
}

// ----- Save updated cache file -----
if ($newItemsAdded || !file_exists(SAVE_FILE)) {
    file_put_contents(SAVE_FILE, json_encode($savedList, JSON_PRETTY_PRINT));
}

// ----- Output Final JSON -----
echo json_encode([
    "status" => "success",
    "history" => $savedList
], JSON_PRETTY_PRINT);
?>
