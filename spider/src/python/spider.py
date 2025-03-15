#!/usr/bin/env python3
import argparse
import os
import re
import requests
from urllib.parse import urljoin, urlparse
from bs4 import BeautifulSoup

# Supported image extensions
SUPPORTED_EXTENSIONS = ('.jpg', '.jpeg', '.png', '.gif', '.bmp')

# Track visited URLs to avoid infinite loops
visited_urls = set()

def parse_arguments():
    """Parse command line arguments"""
    parser = argparse.ArgumentParser(description='Spider - Web Scraping Tool')
    parser.add_argument('url', help='URL to crawl')
    parser.add_argument('-r', '--recursive', action='store_true', help='recursively downloads images')
    parser.add_argument('-l', '--level', type=int, default=5, help='maximum depth level of recursive download')
    parser.add_argument('-p', '--path', default='./data/', help='path where downloaded files will be saved')
    
    return parser.parse_args()

def fetch_url(url):
    """Fetch content from URL"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (compatible; Spider/1.0)'
        }
        response = requests.get(url, headers=headers, timeout=30)
        response.raise_for_status()
        return response.text
    except requests.exceptions.RequestException as e:
        print(f"Failed to fetch: {url}\nError: {e}")
        return None

def extract_images(html, base_url):
    """Extract image URLs from HTML"""
    images = []
    soup = BeautifulSoup(html, 'html.parser')
    
    for img in soup.find_all('img'):
        if img.get('src'):
            img_url = urljoin(base_url, img['src'])
            # Check if the URL has a supported extension
            if img_url.lower().endswith(SUPPORTED_EXTENSIONS):
                images.append(img_url)
    
    return images

def extract_links(html, base_url):
    """Extract links from HTML"""
    links = []
    soup = BeautifulSoup(html, 'html.parser')
    base_domain = urlparse(base_url).netloc
    
    for a in soup.find_all('a', href=True):
        link = urljoin(base_url, a['href'])
        # Skip non-HTTP(S) links
        if not link.startswith(('http://', 'https://')):
            continue
        # Make sure the link is to the same domain to avoid crawling the entire internet
        if urlparse(link).netloc == base_domain:
            links.append(link)
    
    return links

def download_image(url, save_path):
    """Download and save an image"""
    try:
        print(f"Downloading: {url}")
        response = requests.get(url, timeout=30)
        response.raise_for_status()
        
        # Get filename and sanitize it
        filename = os.path.basename(urlparse(url).path)
        filename = re.sub(r'[^a-zA-Z0-9\.\-]', '_', filename)
        
        # Save the image
        save_to = os.path.join(save_path, filename)
        with open(save_to, 'wb') as f:
            f.write(response.content)
        
        print(f"Saved to: {save_to}")
        return True
    except requests.exceptions.RequestException as e:
        print(f"Failed to download: {url}\nError: {e}")
        return False

def crawl(url, depth=0, recursive=False, max_depth=5, save_path='./data/'):
    """Main function to start crawling"""
    global visited_urls
    
    # Check if URL has already been visited
    if url in visited_urls:
        return
    
    # Mark URL as visited
    visited_urls.add(url)
    
    # Check if depth limit is reached
    if depth > max_depth:
        return
    
    print(f"Crawling: {url} (Depth: {depth})")
    
    # Get HTML content
    html = fetch_url(url)
    if not html:
        return
    
    # Extract and download images
    images = extract_images(html, url)
    for img_url in images:
        download_image(img_url, save_path)
    
    # If recursion is enabled and depth limit not reached, extract links and crawl them
    if recursive and depth < max_depth:
        links = extract_links(html, url)
        for link in links:
            crawl(link, depth + 1, recursive, max_depth, save_path)

def main():
    # Parse command line arguments
    args = parse_arguments()
    
    # Create save directory if it doesn't exist
    os.makedirs(args.path, exist_ok=True)
    
    # Start crawling
    crawl(args.url, recursive=args.recursive, max_depth=args.level, save_path=args.path)
    
    print("Done.")

if __name__ == "__main__":
    main() 