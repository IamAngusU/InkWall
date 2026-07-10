<?php
declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php import_inkwall_template.php source.html destination-directory\n");
    exit(2);
}

$source = (string)file_get_contents($argv[1]);
$destination = rtrim($argv[2], '/');
$assets = $destination . '/assets';
if (!is_dir($assets) && !mkdir($assets, 0755, true) && !is_dir($assets)) {
    throw new RuntimeException('Could not create InkWall assets directory.');
}

$source = preg_replace_callback(
    '~data:image/png;base64,([A-Za-z0-9+/=]+)~',
    static function (array $match) use ($assets): string {
        $binary = base64_decode($match[1], true);
        if ($binary === false) throw new RuntimeException('Embedded image is invalid.');
        file_put_contents($assets . '/github-destination.png', $binary, LOCK_EX);
        return 'assets/github-destination.png?v=<?= inkwall_asset_version("assets/github-destination.png") ?>';
    },
    $source
);

$source = preg_replace(
    '~apiBase: typeof window\.EINK_MESSAGE_API.*?: null,~s',
    'apiBase: `${location.pathname.replace(/\\/(?:index\\.php)?$/, "")}/api`.replace(/^\\/\\//, "/"),',
    $source,
    1
);
$source = str_replace(
    'if (!/^data:image\\/webp;base64,[A-Za-z0-9+/=]+$/.test(src)) return null;',
    'if (!/^data:image\\/webp;base64,[A-Za-z0-9+/=]+$/.test(src) && !/^\\/inkwall\\/media\\.php\\?id=[a-f0-9-]{20,40}$/i.test(src)) return null;',
    $source
);
$source = str_replace(
    'Moderation rules, reporting procedures, and security controls are continuously maintained.',
    'Moderation rules, reporting procedures, and security controls are continuously maintained. Usage is correlated with a random browser pseudonym; only country hints and referrer domains are retained. Raw IP addresses, identities, user agents, and complete referrer URLs are not stored by InkWall.',
    $source
);
$source = str_replace(
    '<span>Angus Uelsmann</span>',
    '<span>Angus Uelsmann</span><span aria-hidden="true">·</span><a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>',
    $source
);
$source = str_replace(
    'repositoryUrl: "https://github.com/IamAngusU/IamAngusU",',
    'repositoryUrl: "https://github.com/IamAngusU/InkWall",',
    $source
);
$source = preg_replace(
    '~image\.src = AppConfig\.apiBase\s*\? `\$\{AppConfig\.apiBase\}/favicon\?url=\$\{encodeURIComponent\(origin\)\}`\s*: `\$\{origin\}/favicon\.ico`;~',
    'image.src = `${origin}/favicon.ico`;',
    $source,
    1
);

require_once __DIR__ . '/inkwall_template_overrides.php';
$source = inkwall_apply_template_overrides($source);

$bootstrap = <<<'PHP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
function inkwall_asset_version(string $relative): string {
    $modified = @filemtime(__DIR__ . '/' . ltrim($relative, '/'));
    return (string)($modified ?: 1);
}
inkwall_begin_public_request('view');
?>
PHP;

file_put_contents($destination . '/index.php', $bootstrap . $source, LOCK_EX);
echo "Imported InkWall template to {$destination}/index.php\n";
