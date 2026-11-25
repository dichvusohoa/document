<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray;

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function sameBinding(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

// Each callback accesses private owner state: both object and class scope must survive.
$owner = new class {
    private const INCREMENT = 2;
    private array $events = [];

    public function install(ExtArray $tree): void
    {
        $tree->setDefineLeafFn(function (mixed $value): int {
            $this->events[] = 'define';
            return is_array($value) ? ExtArray::BRANCH_OR_PETIOLE : ExtArray::COMPOSITE_LEAF;
        });
        $tree->setNormalizeLeafFn(function (mixed $value): array {
            $this->events[] = 'normalize';
            return ['err_number' => 0, 'value' => (int) $value];
        });
        $tree->setProcessLeafFn(function (array $path, mixed &$value, array|int $definition): void {
            $this->events[] = 'process';
            $value += self::INCREMENT;
        });
        $tree->setStartBranchFn(function (array $path): void {
            $this->events[] = 'start';
        });
        $tree->setEndBranchFn(function (array $path): void {
            $this->events[] = 'end';
        });
    }

    public function events(): array { return $this->events; }
};
$data = [];
$tree = new ExtArray($data);
$owner->install($tree);
$tree->setValue('a', '5');
$tree->traverseTree();
sameBinding(['a' => 7], $data);
$counts = array_count_values($owner->events());
sameBinding(true, $counts['define'] > 0);
foreach (['normalize', 'process', 'start', 'end'] as $event) {
    sameBinding(1, $counts[$event] ?? 0);
}
echo "PASS: five setters preserve owner and private scope\n";

$data = [];
$tree = new ExtArray($data);
$events = [];
$tree->setDefineLeafFn(static function (mixed $value): int {
    return is_array($value) ? ExtArray::BRANCH_OR_PETIOLE : ExtArray::COMPOSITE_LEAF;
});
$tree->setNormalizeLeafFn(static fn(mixed $value): array => ['err_number' => 0, 'value' => (int) $value]);
$tree->setProcessLeafFn(static function (array $path, mixed &$value, array|int $definition) use (&$events): void {
    $events[] = 'process';
    ++$value;
});
$tree->setStartBranchFn(static function (array $path) use (&$events): void { $events[] = 'start'; });
$tree->setEndBranchFn(static function (array $path) use (&$events): void { $events[] = 'end'; });
$tree->setValue('a', '5');
$tree->traverseTree();
sameBinding(['a' => 6], $data);
sameBinding(['start', 'process', 'end'], $events);
echo "PASS: five setters accept static closures\n";

// The caller can explicitly grant access to protected ExtArray helpers.
$fnProcessLeaf = function (array $path, mixed &$value, array|int $definition): void {
    $result = $this->callNormalizeLeafFn($path, $value);
    $value = $result['value'];
};
$fnProcessLeaf = $fnProcessLeaf->bindTo($tree, ExtArray::class);
sameBinding(true, $fnProcessLeaf instanceof Closure);
$tree->setProcessLeafFn($fnProcessLeaf);
$data['a'] = '9';
$tree->traverseTree();
sameBinding(['a' => 9], $data);
echo "PASS: explicit binding retains protected helper access\n";

try {
    $tree->setProcessLeafFn(static function (array $path, mixed $value, array|int $definition): void {});
    throw new RuntimeException('Expected signature rejection for missing reference.');
} catch (InvalidArgumentException $exception) {
    echo "PASS: signature validation still rejects invalid static processor\n";
}
restore_error_handler();
