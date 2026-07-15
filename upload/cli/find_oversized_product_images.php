#!/usr/bin/env php
<?php

/**
 * Find product images that exceed the configured pixel dimensions.
 *
 * The output is a plain text file containing one project-relative image path
 * per line. An image is included when its width OR height exceeds the limit.
 *
 * Usage:
 *   php cli/find_oversized_product_images.php
 *   php cli/find_oversized_product_images.php --max-width=2000 --max-height=2000
 *   php cli/find_oversized_product_images.php --output=/tmp/images-to-resize.txt
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

$projectRoot = realpath(__DIR__ . '/..');
$options = getopt('', ['image-dir:', 'output:', 'max-width:', 'max-height:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Find product images whose width or height exceeds the configured limit.

Usage:
  php cli/find_oversized_product_images.php [options]

Options:
  --image-dir=PATH    Directory to scan (default: image/catalog)
  --output=PATH       List file (default: cli/oversized_product_images.txt)
  --max-width=PIXELS  Maximum allowed width (default: 2000)
  --max-height=PIXELS Maximum allowed height (default: 2000)
  --help              Show this help

The output contains one path per line, sorted naturally. Images exactly equal
to the limits are not included.
HELP;
    echo PHP_EOL;
    exit(0);
}

$imageDir = isset($options['image-dir'])
    ? resolvePath($options['image-dir'], getcwd())
    : $projectRoot . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'catalog';
$outputFile = isset($options['output'])
    ? resolvePath($options['output'], getcwd())
    : __DIR__ . DIRECTORY_SEPARATOR . 'oversized_product_images.txt';
$maxWidth = parsePositiveInteger($options, 'max-width', 2000);
$maxHeight = parsePositiveInteger($options, 'max-height', 2000);

if (!is_dir($imageDir) || !is_readable($imageDir)) {
    fwrite(STDERR, "Image directory is missing or unreadable: {$imageDir}\n");
    exit(1);
}

$outputDir = dirname($outputFile);
if (!is_dir($outputDir) || !is_writable($outputDir)) {
    fwrite(STDERR, "Output directory is missing or unwritable: {$outputDir}\n");
    exit(1);
}

$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$oversized = [];
$checked = 0;
$unreadable = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($imageDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $extensions, true)) {
        continue;
    }

    $dimensions = @getimagesize($file->getPathname());
    if ($dimensions === false) {
        $unreadable++;
        fwrite(STDERR, "Warning: could not read image dimensions: {$file->getPathname()}\n");
        continue;
    }

    $checked++;
    if ($dimensions[0] > $maxWidth || $dimensions[1] > $maxHeight) {
        $oversized[] = displayPath($file->getPathname(), $projectRoot);
    }
}

natcasesort($oversized);
$oversized = array_values($oversized);

// Write through a temporary file so a failed scan never leaves a partial list.
$temporaryFile = tempnam($outputDir, '.oversized-images-');
if ($temporaryFile === false) {
    fwrite(STDERR, "Could not create a temporary output file in: {$outputDir}\n");
    exit(1);
}

$contents = $oversized ? implode(PHP_EOL, $oversized) . PHP_EOL : '';
if (file_put_contents($temporaryFile, $contents, LOCK_EX) === false
    || !chmod($temporaryFile, 0644)
    || !rename($temporaryFile, $outputFile)
) {
    @unlink($temporaryFile);
    fwrite(STDERR, "Could not write image list: {$outputFile}\n");
    exit(1);
}

echo "Checked {$checked} image(s).\n";
echo "Found " . count($oversized) . " image(s) exceeding {$maxWidth}x{$maxHeight} pixels.\n";
if ($unreadable > 0) {
    echo "Skipped {$unreadable} unreadable image(s).\n";
}
echo "Saved list to: {$outputFile}\n";

/**
 * Return an absolute path, resolving relative paths from the supplied base.
 */
function resolvePath($path, $base)
{
    if ($path === '') {
        return $base;
    }

    $isAbsolute = $path[0] === DIRECTORY_SEPARATOR
        || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:[\\\\\/]/', $path));

    return $isAbsolute ? $path : $base . DIRECTORY_SEPARATOR . $path;
}

/**
 * Read and validate a positive integer CLI option.
 */
function parsePositiveInteger(array $options, $name, $default)
{
    if (!isset($options[$name])) {
        return $default;
    }

    $value = filter_var($options[$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) {
        fwrite(STDERR, "--{$name} must be a positive integer.\n");
        exit(1);
    }

    return $value;
}

/**
 * Prefer a portable project-relative path, falling back to an absolute path.
 */
function displayPath($path, $projectRoot)
{
    $rootPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($path, $rootPrefix, strlen($rootPrefix)) === 0) {
        $path = substr($path, strlen($rootPrefix));
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}
