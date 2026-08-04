<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI.\n"); exit(2); }
require dirname(__DIR__) . '/public/lib.php';

function cc_cli_has(string $flag): bool { return in_array($flag, $_SERVER['argv'] ?? [], true); }
function cc_cli_option(string $name, ?string $default = null): ?string
{
    foreach ($_SERVER['argv'] ?? [] as $arg) {
        if (strpos($arg, '--' . $name . '=') === 0) { return substr($arg, strlen($name) + 3); }
    }
    return $default;
}
function cc_cli_require_apply(): bool
{
    $apply = cc_cli_has('--apply');
    fwrite(STDOUT, $apply ? "Modo APPLY\n" : "Modo DRY-RUN (usa --apply para escribir)\n");
    return $apply;
}
