# Spider - Web Scraping Tool

A program to extract images from websites recursively.

## Implementations

This project has been implemented in multiple languages for your convenience:

### PHP Version

```
./spider [-r] [-l N] [-p PATH] URL
```

### Python Version

Dependencies:
```
pip install -r requirements.txt
```

Usage:
```
./spider_py [-r] [-l N] [-p PATH] URL
```

### Ruby Version

Dependencies:
```
bundle install
```

Usage:
```
./spider_rb [-r] [-l PATH] [-p PATH] URL
```

## Options

- `-r` or `--recursive`: recursively downloads the images in a URL
- `-l N` or `--level N`: indicates the maximum depth level of the recursive download (default: 5)
- `-p PATH` or `--path PATH`: indicates the path where the downloaded files will be saved (default: ./data/)

## Supported Image Types

- .jpg/jpeg
- .png
- .gif
- .bmp