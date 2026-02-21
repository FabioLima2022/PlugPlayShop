<?php
// Helpers de SEO para PlugPlay Shop

function seo_site_url(): string {
    $env = load_env();
    if (($env['FORCE_HTTPS'] ?? '') === 'true') {
        return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function seo_canonical(?string $path = null): string {
    $base = seo_site_url();
    $uri = $path ?? ($_SERVER['REQUEST_URI'] ?? '/');
    $url = rtrim($base, '/') . $uri;
    return htmlspecialchars($url);
}

function seo_meta(array $opts = []): void {
    $title = $opts['title'] ?? 'PlugPlay Shop';
    $desc = $opts['description'] ?? 'Descubra produtos selecionados com avaliações, imagens e links de compra.';
    $canonical = $opts['canonical'] ?? seo_canonical();
    $image = $opts['image'] ?? null;
    $type = $opts['type'] ?? 'website';
    $siteName = $opts['site_name'] ?? 'PlugPlay Shop';
    $locale = $opts['locale'] ?? 'pt_BR';

    echo '<link rel="canonical" href="' . $canonical . '" />' . "\n";
    echo '<meta name="description" content="' . (function_exists('h') ? h($desc) : htmlspecialchars($desc)) . '" />' . "\n";
    echo '<meta property="og:title" content="' . (function_exists('h') ? h($title) : htmlspecialchars($title)) . '" />' . "\n";
    echo '<meta property="og:description" content="' . (function_exists('h') ? h($desc) : htmlspecialchars($desc)) . '" />' . "\n";
    echo '<meta property="og:type" content="' . (function_exists('h') ? h($type) : htmlspecialchars($type)) . '" />' . "\n";
    echo '<meta property="og:url" content="' . $canonical . '" />' . "\n";
    echo '<meta property="og:site_name" content="' . (function_exists('h') ? h($siteName) : htmlspecialchars($siteName)) . '" />' . "\n";
    echo '<meta property="og:locale" content="' . (function_exists('h') ? h($locale) : htmlspecialchars($locale)) . '" />' . "\n";
    if ($image) {
        echo '<meta property="og:image" content="' . (function_exists('h') ? h($image) : htmlspecialchars($image)) . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . (function_exists('h') ? h($image) : htmlspecialchars($image)) . '" />' . "\n";
    }
    echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '" />' . "\n";
    echo '<meta name="twitter:title" content="' . (function_exists('h') ? h($title) : htmlspecialchars($title)) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . (function_exists('h') ? h($desc) : htmlspecialchars($desc)) . '" />' . "\n";
}

function seo_jsonld(array $schema): void {
    // Ensure all strings are UTF-8 safe before encoding
    array_walk_recursive($schema, function(&$item) {
        if (is_string($item)) {
            if (function_exists('mb_check_encoding')) {
                if (!mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1');
                }
            } elseif (function_exists('iconv')) {
                // Fallback using iconv to ignore invalid characters
                $item = iconv('UTF-8', 'UTF-8//IGNORE', $item);
            }
        }
    });
    $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        // If encoding fails, return empty object to avoid breaking page
        echo '<script type="application/ld+json">{}</script>' . "\n";
    } else {
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}