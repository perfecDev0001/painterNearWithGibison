<?php
/**
 * Performance Optimization System
 * Provides caching, compression, and optimization features
 */

class PerformanceOptimizer {
    private $cacheDir;
    private $cacheEnabled;
    private $compressionEnabled;
    private $minifyEnabled;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache';
        $this->cacheEnabled = getenv('CACHE_ENABLED') !== 'false';
        $this->compressionEnabled = function_exists('gzcompress');
        $this->minifyEnabled = true;
        
        $this->ensureCacheDirectory();
    }
    
    private function ensureCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Create subdirectories
        $subdirs = ['html', 'css', 'js', 'data', 'images'];
        foreach ($subdirs as $subdir) {
            $path = $this->cacheDir . '/' . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Cache HTML content
     */
    public function cacheHTML($key, $content, $ttl = 3600) {
        if (!$this->cacheEnabled) return;
        
        $cacheFile = $this->cacheDir . '/html/' . md5($key) . '.cache';
        $cacheData = [
            'content' => $this->minifyHTML($content),
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, serialize($cacheData));
    }
    
    /**
     * Get cached HTML content
     */
    public function getCachedHTML($key) {
        if (!$this->cacheEnabled) return false;
        
        $cacheFile = $this->cacheDir . '/html/' . md5($key) . '.cache';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return false;
        }
        
        return $cacheData['content'];
    }
    
    /**
     * Cache database query results
     */
    public function cacheQuery($sql, $params, $result, $ttl = 1800) {
        if (!$this->cacheEnabled) return;
        
        $key = md5($sql . serialize($params));
        $cacheFile = $this->cacheDir . '/data/' . $key . '.cache';
        
        $cacheData = [
            'result' => $result,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, serialize($cacheData));
    }
    
    /**
     * Get cached query result
     */
    public function getCachedQuery($sql, $params) {
        if (!$this->cacheEnabled) return false;
        
        $key = md5($sql . serialize($params));
        $cacheFile = $this->cacheDir . '/data/' . $key . '.cache';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return false;
        }
        
        return $cacheData['result'];
    }
    
    /**
     * Minify HTML content
     */
    private function minifyHTML($html) {
        if (!$this->minifyEnabled) return $html;
        
        // Remove comments
        $html = preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Minify CSS content
     */
    public function minifyCSS($css) {
        if (!$this->minifyEnabled) return $css;
        
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Remove extra whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary characters
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ', '], [';', '{', '{', '}', '}', ':', ','], $css);
        
        return trim($css);
    }
    
    /**
     * Minify JavaScript content
     */
    public function minifyJS($js) {
        if (!$this->minifyEnabled) return $js;
        
        // Basic JS minification (for more complex minification, consider using a proper library)
        
        // Remove single-line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove whitespace around operators
        $js = preg_replace('/\s*([{}();,=+\-*\/])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Compress content with gzip
     */
    public function compress($content) {
        if (!$this->compressionEnabled) return $content;
        
        return gzcompress($content, 9);
    }
    
    /**
     * Decompress gzipped content
     */
    public function decompress($content) {
        if (!$this->compressionEnabled) return $content;
        
        return gzuncompress($content);
    }
    
    /**
     * Optimize images (basic resize and compression)
     */
    public function optimizeImage($imagePath, $maxWidth = 1200, $quality = 80) {
        if (!file_exists($imagePath)) return false;
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) return false;
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Skip if image is already small enough
        if ($width <= $maxWidth) return true;
        
        // Calculate new dimensions
        $newWidth = $maxWidth;
        $newHeight = intval(($height * $maxWidth) / $width);
        
        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                return false;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }
        
        // Resize image
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        $optimizedPath = $this->cacheDir . '/images/' . basename($imagePath);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($destination, $optimizedPath, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($destination, $optimizedPath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($destination, $optimizedPath);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($destination);
        
        return $optimizedPath;
    }
    
    /**
     * Clear cache
     */
    public function clearCache($type = 'all') {
        $directories = ['html', 'css', 'js', 'data', 'images'];
        
        if ($type !== 'all' && in_array($type, $directories)) {
            $directories = [$type];
        }
        
        foreach ($directories as $dir) {
            $path = $this->cacheDir . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        $stats = [];
        $directories = ['html', 'css', 'js', 'data', 'images'];
        
        foreach ($directories as $dir) {
            $path = $this->cacheDir . '/' . $dir;
            $files = glob($path . '/*');
            $totalSize = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $totalSize += filesize($file);
                }
            }
            
            $stats[$dir] = [
                'files' => count($files),
                'size' => $totalSize
            ];
        }
        
        return $stats;
    }
    
    /**
     * Enable output compression
     */
    public function enableOutputCompression() {
        if ($this->compressionEnabled && !ob_get_level()) {
            ob_start('ob_gzhandler');
        }
    }
    
    /**
     * Set browser caching headers
     */
    public function setBrowserCache($duration = 3600) {
        $expires = gmdate('D, d M Y H:i:s', time() + $duration) . ' GMT';
        
        header("Cache-Control: public, max-age=$duration");
        header("Expires: $expires");
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime($_SERVER['SCRIPT_FILENAME'])) . ' GMT');
    }
    
    /**
     * Preload critical resources
     */
    public function preloadResources($resources) {
        foreach ($resources as $resource) {
            $type = $resource['type'] ?? 'script';
            $href = $resource['href'];
            $as = $resource['as'] ?? $type;
            
            header("Link: <$href>; rel=preload; as=$as", false);
        }
    }
    
    /**
     * Lazy load images in HTML
     */
    public function lazyLoadImages($html) {
        // Add loading="lazy" to images
        $html = preg_replace('/<img(?![^>]*loading=)([^>]*)(src=["\'][^"\']*["\'])([^>]*)>/i', 
                           '<img$1$2 loading="lazy"$3>', $html);
        
        return $html;
    }
    
    /**
     * Generate critical CSS
     */
    public function generateCriticalCSS($html, $css) {
        // Extract CSS selectors used in HTML (basic implementation)
        preg_match_all('/class=["\']([^"\']*)["\']/', $html, $classes);
        preg_match_all('/id=["\']([^"\']*)["\']/', $html, $ids);
        
        $usedSelectors = [];
        
        // Add classes
        foreach ($classes[1] as $classList) {
            $classNames = explode(' ', $classList);
            foreach ($classNames as $className) {
                if (!empty(trim($className))) {
                    $usedSelectors[] = '.' . trim($className);
                }
            }
        }
        
        // Add IDs
        foreach ($ids[1] as $id) {
            if (!empty(trim($id))) {
                $usedSelectors[] = '#' . trim($id);
            }
        }
        
        // Extract matching CSS rules (basic implementation)
        $criticalCSS = '';
        foreach ($usedSelectors as $selector) {
            $pattern = '/' . preg_quote($selector, '/') . '\s*\{[^}]*\}/';
            if (preg_match($pattern, $css, $matches)) {
                $criticalCSS .= $matches[0] . "\n";
            }
        }
        
        return $this->minifyCSS($criticalCSS);
    }
}
?>