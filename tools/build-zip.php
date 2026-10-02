<?php
/**
 * Builds an installable plugin zip with nothing but PHP: no git, no zip command, no
 * dependencies. Handy on Windows, and on hosts where PHP is the only tool you have.
 *
 *   php tools/build-zip.php                        writes ../imperial-barons-online-<version>.zip
 *   php tools/build-zip.php /path/to/output.zip    writes that file instead
 *
 * It packs the working tree, so uncommitted edits are included. That is the difference from
 * `git archive`, which packs the last commit; both produce the same single top-level folder,
 * imperial-barons-online, which is what Plugins -> Add New -> Upload Plugin expects.
 */
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only.\n");
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP's zip extension is not enabled, so this script cannot build the archive.\n");
    exit(1);
}

$root = dirname(__DIR__);
$slug = 'imperial-barons-online';

/** Anything that is not part of the plugin: repository metadata, this script, build output. */
$skip_dirs  = ['.git', '.github', '.idea', '.vscode', 'node_modules', 'vendor', 'tools'];
$skip_files = ['.gitignore', '.gitattributes', '.DS_Store', 'Thumbs.db', 'desktop.ini'];
$skip_exts  = ['zip', 'log', 'swp', 'bak', 'orig', 'rej'];

$version = 'unknown';
$header = (string) file_get_contents($root . '/' . $slug . '.php');
if (preg_match('/^\s*\*?\s*Version:\s*(.+)$/mi', $header, $m)) $version = trim($m[1]);

$out = isset($argv[1]) && $argv[1] !== ''
    ? $argv[1]
    : dirname($root) . '/' . $slug . '-' . $version . '.zip';

if (file_exists($out) && !unlink($out)) {
    fwrite(STDERR, "Could not replace $out\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create $out\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($file) use ($skip_dirs) {
            return !($file->isDir() && in_array($file->getFilename(), $skip_dirs, true));
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $name = $file->getFilename();
    if (in_array($name, $skip_files, true)) continue;
    if (in_array(strtolower($file->getExtension()), $skip_exts, true)) continue;
    // Zip entries always use forward slashes, whatever the platform's separator is.
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    $zip->addFile($file->getPathname(), $slug . '/' . $relative);
    $count++;
}

if (!$zip->close()) {
    fwrite(STDERR, "Could not write $out\n");
    exit(1);
}

printf("%s\n%d files, %.1f KB, version %s\n", $out, $count, filesize($out) / 1024, $version);
