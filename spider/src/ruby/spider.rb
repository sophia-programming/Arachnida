#!/usr/bin/env ruby
require 'optparse'
require 'open-uri'
require 'nokogiri'
require 'uri'
require 'fileutils'

# Supported image extensions
SUPPORTED_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.gif', '.bmp']

# Track visited URLs to avoid infinite loops
@visited_urls = {}

# Parse command line arguments
options = {
  recursive: false,
  max_depth: 5,
  save_path: './data/'
}

OptionParser.new do |opts|
  opts.banner = "Usage: ./spider [options] URL"

  opts.on('-r', '--recursive', 'Recursively downloads images') do
    options[:recursive] = true
  end

  opts.on('-l', '--level N', Integer, 'Maximum depth level of recursive download') do |n|
    options[:max_depth] = n
  end

  opts.on('-p', '--path PATH', 'Path where downloaded files will be saved') do |path|
    options[:save_path] = path.end_with?('/') ? path : "#{path}/"
  end
end.parse!

# Get URL from remaining arguments
url = ARGV[0]

if url.nil?
  puts "Error: URL is required."
  puts "Usage: ./spider [options] URL"
  exit(1)
end

# Create save directory if it doesn't exist
FileUtils.mkdir_p(options[:save_path])

# Fetch URL content
def fetch_url(url)
  begin
    URI.open(url, 
      'User-Agent' => 'Mozilla/5.0 (compatible; Spider/1.0)',
      read_timeout: 30
    ).read
  rescue => e
    puts "Failed to fetch: #{url}\nError: #{e}"
    nil
  end
end

# Extract image URLs from HTML
def extract_images(html, base_url)
  images = []
  
  doc = Nokogiri::HTML(html)
  doc.css('img').each do |img|
    if img['src']
      img_url = URI.join(base_url, img['src']).to_s
      extension = File.extname(img_url).downcase
      
      if SUPPORTED_EXTENSIONS.include?(extension)
        images << img_url
      end
    end
  end
  
  images
end

# Extract links from HTML
def extract_links(html, base_url)
  links = []
  base_domain = URI.parse(base_url).host
  
  doc = Nokogiri::HTML(html)
  doc.css('a').each do |a|
    if a['href']
      begin
        link = URI.join(base_url, a['href']).to_s
        link_domain = URI.parse(link).host
        
        if link_domain == base_domain
          links << link
        end
      rescue URI::InvalidURIError
        # Skip invalid URLs
        next
      end
    end
  end
  
  links
end

# Download and save an image
def download_image(url, save_path)
  begin
    puts "Downloading: #{url}"
    
    # Get image data
    img_data = fetch_url(url)
    return if img_data.nil?
    
    # Get filename and sanitize it
    filename = File.basename(URI.parse(url).path)
    filename = filename.gsub(/[^a-zA-Z0-9\.\-]/, '_')
    
    # Save image
    save_to = File.join(save_path, filename)
    File.open(save_to, 'wb') do |file|
      file.write(img_data)
    end
    
    puts "Saved to: #{save_to}"
    true
  rescue => e
    puts "Failed to download: #{url}\nError: #{e}"
    false
  end
end

# Main function to start crawling
def crawl(url, depth = 0, options = {})
  # Check if URL has already been visited
  return if @visited_urls[url]
  
  # Mark URL as visited
  @visited_urls[url] = true
  
  # Check if depth limit is reached
  return if depth > options[:max_depth]
  
  puts "Crawling: #{url} (Depth: #{depth})"
  
  # Get HTML content
  html = fetch_url(url)
  return unless html
  
  # Extract and download images
  images = extract_images(html, url)
  images.each do |img_url|
    download_image(img_url, options[:save_path])
  end
  
  # If recursion is enabled and depth limit not reached, extract links and crawl them
  if options[:recursive] && depth < options[:max_depth]
    links = extract_links(html, url)
    links.each do |link|
      crawl(link, depth + 1, options)
    end
  end
end

# Start crawling
crawl(url, 0, options)

puts "Done." 