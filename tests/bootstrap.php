<?php
declare(strict_types=1);
error_reporting(E_ALL);
$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0];

function check(bool $cond, string $msg): void {
    if ($cond) { $GLOBALS['__t']['pass']++; echo "  ok  $msg\n"; }
    else       { $GLOBALS['__t']['fail']++; echo "  FAIL $msg\n"; }
}

function done(): void {
    $t = $GLOBALS['__t'];
    echo "== {$t['pass']} pass, {$t['fail']} fail ==\n";
    exit($t['fail'] > 0 ? 1 : 0);
}
