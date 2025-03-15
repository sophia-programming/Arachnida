#!/usr/bin/env php
<?php

/**
 * Spider - Web Scraping Tool
 * 
 * A program to extract images from websites recursively
 */

// Default values
$recursion = false;
$maxDepth = 5;
$savePath = './data/';
$url = null;

// Parse command line arguments
$options = getopt('rlp:', ['r', 'l:', 'p:']);
$args = array_slice($argv, 1);

// Process arguments
foreach ($args as $i => $arg) {
    // Handle options
    if (substr($arg, 0, 1) === '-') {
        continue; // Skip options as they are already processed by getopt
    } else {
        // This must be the URL
        $url = $arg;
        break;
    }
}

// Process options
if (isset($options['r'])) {
    $recursion = true;
}

if (isset($options['l'])) {
    $maxDepth = (int)$options['l'];
}

if (isset($options['p'])) {
    $savePath = rtrim($options['p'], '/') . '/';
}

// Validate URL
if (!$url) {
    echo "Error: URL is required.\n";
    echo "Usage: ./spider [-rlp] URL\n";
    echo "  -r               : recursively downloads images\n";
    echo "  -r -l [N]        : indicates the maximum depth level (default: 5)\n";
    echo "  -p [PATH]        : indicates the path where files will be saved (default: ./data/)\n";
    exit(1);
}

// Create save directory if it doesn't exist
if (!file_exists($savePath)) {
    mkdir($savePath, 0777, true);
}

// Supported image extensions
$supportedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp'];

// Track visited URLs to avoid infinite loops
$visitedUrls = [];

/**
 * Main function to start crawling
 */
function crawl($url, $depth = 0) {
    global $recursion, $maxDepth, $savePath, $supportedExtensions, $visitedUrls;
    
    // Check if URL has already been visited
    if (isset($visitedUrls[$url])) {
        return;
    }
    
    // Mark URL as visited
    $visitedUrls[$url] = true;
    
    // Check if depth limit is reached
    if ($depth > $maxDepth) {
        return;
    }
    
    echo "Crawling: $url (Depth: $depth)\n";
    
    // Get HTML content
    $html = fetchUrl($url);
    if (!$html) {
        echo "Failed to fetch: $url\n";
        return;
    }
    
    // Extract and download images
    $images = extractImages($html, $url);
    foreach ($images as $imageUrl) {
        downloadImage($imageUrl, $savePath);
    }
    
    // If recursion is enabled, extract links and crawl them
    if ($recursion && $depth < $maxDepth) {
        $links = extractLinks($html, $url);
        foreach ($links as $link) {
            crawl($link, $depth + 1);
        }
    }
}

/**
 * Fetch URL content
 */
function fetchUrl($url) {
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'Mozilla/5.0 (compatible; Spider/1.0)',
            'timeout' => 30,
        ],
    ]);
    
    return @file_get_contents($url, false, $context);
}

/**
 * Extract image URLs from HTML
 */
function extractImages($html, $baseUrl) {
    global $supportedExtensions;
    
    $images = [];
    $pattern = '/<img[^>]+src=["\'](.*?)["\'][^>]*>/i';
    
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $src) {
            // Resolve relative URLs
            $imageUrl = resolveUrl($src, $baseUrl);
            
            // Check if the URL has a supported extension
            $extension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
            if (in_array('.' . $extension, $supportedExtensions)) {
                $images[] = $imageUrl;
            }
        }
    }
    
    return $images;
}

/**
 * Extract links from HTML
 */
function extractLinks($html, $baseUrl) {
    $links = [];
    $pattern = '/<a[^>]+href=["\'](.*?)["\'][^>]*>/i';
    
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $href) {
            // Skip fragment and javascript links
            if (preg_match('/^#|javascript:/i', $href)) {
                continue;
            }
            
            // Resolve relative URLs
            $linkUrl = resolveUrl($href, $baseUrl);
            
            // Make sure the link is to the same domain to avoid crawling the entire internet
            $baseUrlHost = parse_url($baseUrl, PHP_URL_HOST);
            $linkUrlHost = parse_url($linkUrl, PHP_URL_HOST);
            
            if ($baseUrlHost === $linkUrlHost) {
                $links[] = $linkUrl;
            }
        }
    }
    
    return $links;
}

/**
 * Resolve relative URL to absolute URL
 */
function resolveUrl($url, $baseUrl) {
    // If URL is already absolute
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    
    $parsedBase = parse_url($baseUrl);
    
    // If URL starts with //
    if (substr($url, 0, 2) === '//') {
        return $parsedBase['scheme'] . ':' . $url;
    }
    
    // If URL starts with /
    if (substr($url, 0, 1) === '/') {
        return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $url;
    }
    
    // Otherwise it's relative to the current path
    $path = isset($parsedBase['path']) ? $parsedBase['path'] : '/';
    $path = preg_replace('/\/[^\/]*$/', '/', $path); // Remove filename from path
    
    return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $path . $url;
}

/**
 * Download and save an image
 */
function downloadImage($url, $savePath) {
    $filename = basename($url);
    $filename = preg_replace('/[^a-zA-Z0-9\.\-]/', '_', $filename); // Sanitize filename
    
    echo "Downloading: $url\n";
    
    $imageData = fetchUrl($url);
    if (!$imageData) {
        echo "Failed to download: $url\n";
        return;
    }
    
    $saveTo = $savePath . $filename;
    if (file_put_contents($saveTo, $imageData)) {
        echo "Saved to: $saveTo\n";
    } else {
        echo "Failed to save: $saveTo\n";
    }
}

// Start crawling from the given URL
crawl($url);

echo "Done.\n"; 