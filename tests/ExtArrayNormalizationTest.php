<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray as E;

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function raises(string $class, Closure $run): Throwable
{
    try {
        $run();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw $e;
    }
    throw new RuntimeException('Expected ' . $class);
}

function tree(array &$data): E
{
    $tree = new E($data);
    $tree->setDefineLeafFn(function (mixed $value): int {
        return !is_array($value) || array_key_exists('min', $value)
            ? E::COMPOSITE_LEAF : E::BRANCH_OR_PETIOLE;
    });
    return $tree;
}

$tests = [];
$tests['non-leaf candidates rejected before normalization'] = function (): void {
    foreach ([E::BRANCH_OR_PETIOLE, E::EXCLUDED, E::DISCARDED_LEAF] as $definition) {
        foreach ([E::MODE_REPLACE, E::MODE_MERGE_VALUE] as $mode) {
            $data = ['a' => 7];
            $tree = tree($data);
            $calls = 0;
            $tree->setDefineLeafFn(function (mixed $value) use ($definition): int {
                return is_array($value) && isset($value['invalid'])
                    ? $definition
                    : (is_array($value) ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF);
            });
            $tree->setNormalizeLeafFn(function (mixed $value) use (&$calls): array {
                ++$calls;
                return ['err_number' => 0, 'value' => $value];
            });
            raises(LogicException::class,
                fn() => $tree->setValue(['a', 'new'], ['invalid' => true], $mode));
            same(0, $calls);
            same(['a' => 7], $data);
        }
    }
};
$tests['replace normalization, nullable result and keep'] = function (): void {
    $data = [];
    $tree = tree($data);
    $tree->setNormalizeLeafFn(function (mixed $value): array {
        return ['err_number' => 0, 'value' => $value === null ? null : (int) $value];
    });
    $input = '5';
    $tree->setValue('a', $input);
    same('5', $input);
    same(['a' => 5], $data);
    $tree->setValue('b', null);
    $tree->setValue('a', '9', E::MODE_KEEP_OLD_VALUE);
    same(['a' => 5, 'b' => null], $data);
};
$tests['merge validates complete candidate once and preserves failed target'] = function (): void {
    $data = ['a' => ['min' => 1, 'max' => 5]];
    $tree = tree($data);
    $calls = [];
    $tree->setNormalizeLeafFn(function (array $path, mixed $value) use (&$calls): array {
        $calls[] = [$path, $value];
        $value['min'] = (int) $value['min'];
        return ['err_number' => $value['min'] > $value['max'] ? 71 : 0, 'value' => $value];
    });
    $tree->setValue('a', ['min' => '3'], E::MODE_MERGE_VALUE);
    same([[['a'], ['min' => '3', 'max' => 5]]], $calls);
    same(['a' => ['min' => 3, 'max' => 5]], $data);
    $before = $data;
    $error = raises(LogicException::class, fn() => $tree->setValue('a', ['min' => 9], E::MODE_MERGE_VALUE));
    same(71, $error->getCode());
    same(true, str_contains($error->getMessage(), '["a"]'));
    same(2, count($calls));
    same($before, $data);
};
$tests['merge petiole, branch, new leaf and scalar'] = function (): void {
    $data = ['a' => ['__petiole__' => ['min' => 1, 'max' => 5], 'child' => 7],
        'b' => ['child' => 8], 'scalar' => 2];
    $tree = tree($data);
    $calls = [];
    $tree->setNormalizeLeafFn(function (array $path, mixed $value) use (&$calls): array {
        $calls[] = [$path, $value];
        return ['err_number' => 0, 'value' => $value];
    });
    $tree->setValue('a', ['min' => 3], E::MODE_MERGE_VALUE);
    $tree->setValue('b', 10, E::MODE_MERGE_VALUE);
    $tree->setValue('new', 11, E::MODE_MERGE_VALUE);
    $tree->setValue('scalar', 12, E::MODE_MERGE_VALUE);
    same([[['a'], ['min' => 3, 'max' => 5]], [['b'], 10], [['new'], 11], [['scalar'], 12]], $calls);
    same(['__petiole__' => ['min' => 3, 'max' => 5], 'child' => 7], $data['a']);
    same(['child' => 8, '__petiole__' => 10], $data['b']);
    same(11, $data['new']);
    same(12, $data['scalar']);
};
$tests['failure restores inserted path and promoted leaf; callback exception unchanged'] = function (): void {
    $data = ['a' => 7];
    $tree = tree($data);
    $error = new DomainException('UDA failure');
    $tree->setNormalizeLeafFn(function (mixed $value) use ($error): array {
        throw $error;
    });
    same($error, raises(DomainException::class,
        fn() => $tree->setValue(['a', 'b', 'c'], 8, E::MODE_MERGE_VALUE)));
    same(['a' => 7], $data);
};
$tests['malformed callback results rejected before commit'] = function (): void {
    foreach ([true, [], ['value' => null], ['err_number' => 0],
        ['err_number' => -1, 'value' => 1], ['err_number' => '0', 'value' => 1]] as $result) {
        $data = ['a' => 1];
        $tree = tree($data);
        $tree->setNormalizeLeafFn(function (mixed $value) use ($result) { return $result; });
        raises(UnexpectedValueException::class, fn() => $tree->setValue('a', 2));
        same(['a' => 1], $data);
    }
};
$tests['root merge once'] = function (): void {
    $data = ['min' => 1, 'max' => 5];
    $tree = tree($data);
    $calls = 0;
    $tree->setNormalizeLeafFn(function (array $path, mixed $value) use (&$calls): array {
        same([], $path);
        ++$calls;
        return ['err_number' => 0, 'value' => $value];
    });
    $tree->setValue([], ['min' => 2], E::MODE_MERGE_VALUE);
    same(1, $calls);
    same(['min' => 2, 'max' => 5], $data);
};

$tests['solitary petiole getters support scalar, null, array and root leaves'] = function (): void {
    $originalName = E::getPetioleName();
    try {
        foreach ([E::PETIOLE_DEFAULT_NAME, '__policy__'] as $petiole) {
            E::setPetioleName($petiole);
            foreach (['old', null, ['min' => 1, 'max' => 5]] as $leaf) {
                $data = ['item' => [$petiole => $leaf]];
                $tree = tree($data);
                same($leaf, $tree->getValue('item'));
                same($leaf, $tree->getValue(['item', $petiole]));
                same(['exists' => true, 'value' => $leaf], $tree->getExistInfoAndValue('item'));
                same(true, $tree->existElement('item'));
                $ref = &$tree->getReferenceAt('item');
                $ref = 'new';
                unset($ref);
                same(['item' => [$petiole => 'new']], $data);

                $root = [$petiole => $leaf];
                $rootTree = tree($root);
                same($leaf, $rootTree->getValue([]));
                same($leaf, $rootTree->getValue([$petiole]));
                same([$petiole => $leaf], $root);
            }
        }
    } finally {
        E::setPetioleName($originalName);
    }
};
$tests['solitary petiole supports writes, merge, keep and growing branches'] = function (): void {
    $originalName = E::getPetioleName();
    try {
        foreach ([E::PETIOLE_DEFAULT_NAME, '__policy__'] as $petiole) {
            E::setPetioleName($petiole);
            $data = ['item' => [$petiole => ['min' => 1, 'max' => 5]]];
            $tree = tree($data);
            $tree->setNormalizeLeafFn(static fn(mixed $v): array => ['err_number' => 0, 'value' => $v]);
            $tree->setValue('item', ['min' => 2], E::MODE_MERGE_VALUE);
            same(['item' => [$petiole => ['min' => 2, 'max' => 5]]], $data);
            $tree->setValue('item', 99, E::MODE_KEEP_OLD_VALUE);
            same(['min' => 2, 'max' => 5], $tree->getValue('item'));
            $tree->setValue(['item', $petiole], 3);
            $tree->setValue(['item', 'child'], 7);
            same(['item' => [$petiole => 3, 'child' => 7]], $data);
            $tree->setValue('item', 4);
            same(['item' => 4], $data);

            $root = [$petiole => ['min' => 1, 'max' => 5]];
            $rootTree = tree($root);
            $rootTree->setNormalizeLeafFn(static fn(mixed $v): array => ['err_number' => 0, 'value' => $v]);
            $rootTree->setValue([], ['min' => 2], E::MODE_MERGE_VALUE);
            same([$petiole => ['min' => 2, 'max' => 5]], $root);
            $rootTree->setValue('child', 8);
            same([$petiole => ['min' => 2, 'max' => 5], 'child' => 8], $root);
        }
    } finally {
        E::setPetioleName($originalName);
    }
};
$tests['solitary support still rejects duplicate and nonterminal petiole paths'] = function (): void {
    $originalName = E::getPetioleName();
    try {
        foreach ([E::PETIOLE_DEFAULT_NAME, '__policy__'] as $petiole) {
            E::setPetioleName($petiole);
            $data = ['a' => ['b' => [$petiole => 'old']], $petiole => 'root'];
            $tree = tree($data);
            $tree->setNormalizeLeafFn(static fn(mixed $v): array => ['err_number' => 0, 'value' => $v]);
            $before = $data;
            foreach ([['a', 'b', $petiole, $petiole], [$petiole, 'a', 'b', $petiole],
                ['a', 'b', $petiole, 'child']] as $path) {
                raises(LogicException::class, static fn() => $tree->getValue($path));
                raises(LogicException::class, static fn() => $tree->getReferenceAt($path));
                raises(LogicException::class, static fn() => $tree->existElement($path));
                foreach ([E::MODE_REPLACE, E::MODE_KEEP_OLD_VALUE, E::MODE_MERGE_VALUE] as $mode) {
                    raises(LogicException::class, static fn() => $tree->setValue($path, 7, $mode));
                    same($before, $data);
                }
            }
            $invalid = ['item' => [$petiole => []]];
            $invalidTree = tree($invalid);
            raises(LogicException::class, static fn() => $invalidTree->traverseTree());
        }
    } finally {
        E::setPetioleName($originalName);
    }
};

$tests['traversal retains solitary petioles at root and child, including excluded nodes'] = function (): void {
    foreach ([true, false] as $root) {
        foreach ([true, false] as $excluded) {
            foreach ([false, true] as $withChild) {
                $node = ['__petiole__' => 10];
                if ($withChild) { $node[7] = null; }
                $data = $root ? $node : ['item' => $node];
                $tree = new E($data);
                $target = $root ? [] : ['item'];
                $processed = [];
                $ended = [];
                $tree->setDefineLeafFn(static function (array $path, mixed $value) use ($excluded, $target): int {
                    if ($excluded && $path === $target) { return E::EXCLUDED; }
                    return is_array($value) ? E::BRANCH_OR_PETIOLE
                        : ($value === null ? E::DISCARDED_LEAF : E::COMPOSITE_LEAF);
                });
                $tree->setProcessLeafFn(static function (array $path, mixed &$value, array|int $definition) use (&$processed): void {
                    $processed[] = $path;
                    ++$value;
                });
                $tree->setEndBranchFn(static function (array $path) use (&$ended): void { $ended[] = $path; });
                $tree->traverseTree();
                $expectedNode = $excluded ? $node : ['__petiole__' => 11];
                same($root ? $expectedNode : ['item' => $expectedNode], $tree->getData());
                same($excluded ? 10 : 11, $tree->getValue($target));
                same($excluded ? [] : [[...$target, '__petiole__']], $processed);
                same(!$excluded, in_array($target, $ended, true));
            }
        }
    }
};

foreach ($tests as $name => $test) {
    $test();
    echo "PASS: $name\n";
}
echo 'PASS: ' . count($tests) . " ExtArray normalization scenarios\n";
