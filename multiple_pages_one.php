<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to scrape data from a single page
function scrapePage($currentUrl)
{
    // Initialize cURL session
    $ch = curl_init($currentUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL session and get the content
    $html = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        // If there's a cURL error, log the error and return an error response
        $error = 'Curl error: ' . curl_error($ch);
        error_log($error);
        return [
            "success" => false,
            "error" => $error,
        ];
    }

    // Close cURL session
    curl_close($ch);

    // Check if the HTML content is not empty
    if (empty($html)) {
        // If HTML content is empty, log an error and return an error response
        $error = 'HTML content is empty for URL: ' . $currentUrl;
        error_log($error);
        return [
            "success" => false,
            "error" => $error,
        ];
    }

    // Create a DOMDocument object
    $dom = new DOMDocument;
    libxml_use_internal_errors(true); // Disable libxml errors

    // Load HTML content into the DOMDocument
    if (!$dom->loadHTML($html)) {
        // If loading HTML content fails, return an error response
        return [
            "success" => false,
            "error" => 'Failed to load HTML content into DOMDocument.',
        ];
    }

    // Use DOMXPath to query the DOMDocument
    $xpath = new DOMXPath($dom);

    // Specify the class name you want to target
    $classname = 'post-inner';

    // Use XPath query to select elements by class name
    $entries = $xpath->query("//*[contains(@class, '$classname')]");

    // Iterate through each entry
    $scrapedData = [];
    foreach ($entries as $entry) {
        // Extract data based on the structure of the HTML
        $titleElement = $entry->getElementsByTagName('h2')->item(0);
        $title = $titleElement ? strip_tags(trim($titleElement->textContent)) : '';

        $summaryElement = $entry->getElementsByTagName('p')->item(2);
        $summary = $summaryElement ? strip_tags(trim($summaryElement->textContent)) : '';

        $link = $entry->getElementsByTagName('a')->item(0)->getAttribute('href');
        $image = $entry->getElementsByTagName('img')->item(0)->getAttribute('src');
        $category = $entry->getElementsByTagName('p')->item(0)->textContent;
        $date = $entry->getElementsByTagName('time')->item(0)->getAttribute('datetime');

        // Add the extracted data to the array
        $scrapedData[] = [
            'Title' => $title,
            'Summary' => $summary,
            'Link' => $link,
            'Image' => $image,
            'Category' => $category,
            'Date' => $date,
        ];
    }

    // Clear libxml errors
    libxml_clear_errors();

    // Extract the URL for the next page (if available)
    $nextPageLink = $xpath->query("//li[@class='next right']/a")->item(0);
    $prevPageLink = $xpath->query("//li[@class='prev left']/a")->item(0);
    $nextPageUrl = $nextPageLink ? $nextPageLink->getAttribute('href') : null;
    $prevPageUrl = $prevPageLink ? $prevPageLink->getAttribute('href') : null;

    // Extract the page number from the next page URL
    $nextPageNumber = $nextPageUrl ? intval(preg_replace('/[^0-9]/', '', $nextPageUrl)) : null;
    $prevPageNumber = $prevPageUrl ? intval(preg_replace('/[^0-9]/', '', $prevPageUrl)) : null;

    // Obtain the full domain dynamically from the server request
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $domain = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

    // Use parse_url to extract the path and file name
    $parsedUrl = parse_url($baseUrl);
    $pathAndFile = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

    // Create the next page query URL
    $nextPageQueryUrl = $nextPageNumber ? $domain.$pathAndFile . '?page=' . $nextPageNumber : null;

     // Create the prev page query URL
     $prevPageQueryUrl = $prevPageNumber ? $domain.$pathAndFile . '?page=' . $prevPageNumber : null;


    // Create the response array
    $response = [
        "success" => true,
        "current_url" => $currentUrl,
        "next_page_number" => $nextPageNumber,
        "next_page_url" => $nextPageUrl,
        "next_page_query_url" => $nextPageQueryUrl,
        "prev_page_number" => $prevPageNumber,
        "prev_page_url" => $prevPageUrl,
        "prev_page_query_url" => $prevPageQueryUrl,
        "data" => $scrapedData,
    ];

    return $response;
}

// Function to get the current page number from the query string
function getPageNumber()
{
    return isset($_GET['page']) ? intval($_GET['page']) : 1;
}

// Function to set the base URL based on the query parameter
function setBaseUrl($baseUrl, $currentPage)
{
    // If it's the first page, return the base URL
    if ($currentPage === 1) {
        return $baseUrl;
    }

    // // If it's not the first page, append the page number to the base URL
    return $baseUrl . 'page/' . $currentPage . '/';
}

// URL to scrape (replace with the URL of the first page)
// URL to scrape (replace with the URL of the first page)
$baseUrl = 'https://haxnode.net/';

// Get the current page number
$currentPage = getPageNumber();

// Set the base URL dynamically
$currentUrl = setBaseUrl($baseUrl, $currentPage);

// Scrape data from the current page
$scrapedData = scrapePage($currentUrl);

// Convert the array to JSON for the current page
$jsonResponse = json_encode($scrapedData, JSON_PRETTY_PRINT);

// Output the JSON response for the current page
header('Content-Type: application/json');
echo $jsonResponse;
?>