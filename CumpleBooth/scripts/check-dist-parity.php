<?php
/** Gate de release: Vite debe copiar public/ byte a byte dentro de dist/. */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__);
$public = $root . '/public';
$dist = $root . '/dist';
if (!is_dir($dist)) { fwrite(STDERR, "FAIL dist/ no existe; ejecuta npm run build.\n"); exit(1); }
$issues = [];
$checked = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) { continue; }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($public) + 1));
    $target = $dist . '/' . $relative;
    $checked++;
    if (!is_file($target)) { $issues[] = "$relative falta en dist"; continue; }
    if (!hash_equals(hash_file('sha256', $file->getPathname()), hash_file('sha256', $target))) { $issues[] = "$relative difiere"; }
}
foreach (glob($dist . '/*.php') ?: [] as $file) {
    if (!is_file($public . '/' . basename($file))) { $issues[] = basename($file) . ' es PHP obsoleto extra en dist'; }
}
if ($issues) { foreach ($issues as $issue) { fwrite(STDERR, "FAIL $issue\n"); } exit(1); }
fwrite(STDOUT, "OK paridad public->dist ($checked archivos).\n");
