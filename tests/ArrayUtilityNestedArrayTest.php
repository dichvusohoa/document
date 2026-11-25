<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Utility\ArrayUtility;

function sameNested(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function rejectsNested(Closure $run): void
{
    try {
        $run();
    } catch (InvalidArgumentException $error) {
        return;
    }
    throw new RuntimeException('Expected InvalidArgumentException.');
}

$tests = [];
$tests['default types accept scalar roots and nested arrays without changing input'] = static function (): void {
    foreach ([null, true, false, 0, -2, 1.5, '', 'text', []] as $value) {
        sameNested(null, ArrayUtility::validateNestedArray($value));
    }
    $input = [7 => ['int' => 1, 'float' => 2.5, 'string' => '3', 'bool' => false,
        'null' => null, 'empty' => []], 'other' => 'keep'];
    $before = $input;
    ArrayUtility::validateNestedArray($input);
    sameNested($before, $input);
    sameNested(null, ReflectionReference::fromArrayElement($input, 7));
    sameNested(null, ReflectionReference::fromArrayElement($input[7], 'int'));
};
$tests['allowed types restrict every value including the root and containers'] = static function (): void {
    ArrayUtility::validateNestedArray(3, ['allowed_types' => ['int']]);
    ArrayUtility::validateNestedArray(['n' => [1, 2]], ['allowed_types' => ['array', 'int']]);
    ArrayUtility::validateNestedArray([], ['allowed_types' => ['array']]);
    ArrayUtility::validateNestedArray(3, ['allowed_types' => ['int', 'int']]);
    rejectsNested(static fn() => ArrayUtility::validateNestedArray(3, ['allowed_types' => []]));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray([], ['allowed_types' => []]));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray('3', ['allowed_types' => ['int']]));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray([1], ['allowed_types' => ['int']]));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray([null], ['allowed_types' => ['array']]));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray([[true]], ['allowed_types' => ['array', 'int']]));
};
$tests['objects and resources require explicit allowed types'] = static function (): void {
    $object = new stdClass();
    $resource = fopen('php://memory', 'r+');
    try {
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($object));
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($resource));
        rejectsNested(static fn() => ArrayUtility::validateNestedArray(['object' => $object]));
        ArrayUtility::validateNestedArray($object, ['allowed_types' => ['object']]);
        ArrayUtility::validateNestedArray($resource, ['allowed_types' => ['resource']]);
        ArrayUtility::validateNestedArray(['object' => $object, 'resource' => $resource],
            ['allowed_types' => ['array', 'object', 'resource']]);
    } finally {
        fclose($resource);
    }
};
$tests['allowed objects are leaves and their properties are not traversed'] = static function (): void {
    $external = 1;
    $object = new stdClass();
    $object->self = $object;
    $object->notAllowedScalar = INF;
    $object->reference = &$external;
    $cycle = [];
    $cycle['self'] = &$cycle;
    $object->arrayCycle = $cycle;
    ArrayUtility::validateNestedArray($object, ['allowed_types' => ['object']], 0);
    ArrayUtility::validateNestedArray([$object], ['allowed_types' => ['array', 'object']], 1);
    sameNested(1, $external);
    sameNested($object, $object->self);
};
$tests['non-finite floats are rejected unless explicitly allowed'] = static function (): void {
    foreach ([INF, -INF, NAN] as $value) {
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($value));
        rejectsNested(static fn() => ArrayUtility::validateNestedArray([['n' => $value]]));
        ArrayUtility::validateNestedArray($value, ['allow_non_finite_floats' => true]);
        ArrayUtility::validateNestedArray([['n' => $value]], ['allow_non_finite_floats' => true]);
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($value,
            ['allow_non_finite_floats' => true, 'allowed_types' => ['int']]));
    }
};
$tests['options reject unknown names invalid types and malformed allowed type lists'] = static function (): void {
    $bad = [
        ['unknown' => true],
        [0 => true],
        ['allow_references' => 1], ['allow_references' => null], ['allow_references' => 'true'],
        ['allow_non_finite_floats' => 1], ['allow_non_finite_floats' => null],
        ['allowed_types' => null], ['allowed_types' => 'int'],
        ['allowed_types' => ['type' => 'int']], ['allowed_types' => [1 => 'int']],
        ['allowed_types' => ['int', null]], ['allowed_types' => [42]],
        ['allowed_types' => ['integer']], ['allowed_types' => ['double']],
        ['allowed_types' => ['boolean']], ['allowed_types' => ['NULL']],
        ['allowed_types' => ['mixed']], ['allowed_types' => ['callable']],
    ];
    foreach ($bad as $options) {
        rejectsNested(static fn() => ArrayUtility::validateNestedArray(1, $options));
    }
};
$tests['maximum depth counts every child value including scalar null and empty array'] = static function (): void {
    foreach ([null, 1, 'text', false, 1.5, []] as $root) {
        ArrayUtility::validateNestedArray($root, [], 0);
        rejectsNested(static fn() => ArrayUtility::validateNestedArray([$root], [], 0));
        ArrayUtility::validateNestedArray([$root], [], 1);
        rejectsNested(static fn() => ArrayUtility::validateNestedArray([[$root]], [], 1));
        ArrayUtility::validateNestedArray([[$root]], [], 2);
    }
    rejectsNested(static fn() => ArrayUtility::validateNestedArray([], [], -1));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray(null, [], -1));
    $deep = 1;
    for ($i = 0; $i < 160; ++$i) {
        $deep = [$deep];
    }
    ArrayUtility::validateNestedArray($deep);
    ArrayUtility::validateNestedArray($deep, [], null);
    ArrayUtility::validateNestedArray($deep, [], 160);
    rejectsNested(static fn() => ArrayUtility::validateNestedArray($deep, [], 159));
};
$tests['references are rejected by default and allowed without detaching or mutating aliases'] = static function (): void {
    $external = ['n' => 1];
    $input = ['first' => &$external, 'second' => &$external];
    rejectsNested(static fn() => ArrayUtility::validateNestedArray($input));
    rejectsNested(static fn() => ArrayUtility::validateNestedArray($input, ['allow_references' => false]));
    $idBefore = ReflectionReference::fromArrayElement($input, 'first')->getId();
    ArrayUtility::validateNestedArray($input, ['allow_references' => true]);
    sameNested($idBefore, ReflectionReference::fromArrayElement($input, 'first')->getId());
    sameNested($idBefore, ReflectionReference::fromArrayElement($input, 'second')->getId());
    sameNested(['n' => 1], $external);
    $external['n'] = 2;
    sameNested(2, $input['first']['n']);
    sameNested(2, $input['second']['n']);
    $scalar = 7;
    ArrayUtility::validateNestedArray(['a' => &$scalar, 'b' => &$scalar], ['allow_references' => true]);
    sameNested(7, $scalar);
};
$tests['self and mutual array cycles are always rejected even with unlimited depth'] = static function (): void {
    $self = [];
    $self['self'] = &$self;
    $left = [];
    $right = [];
    $left['right'] = &$right;
    $right['left'] = &$left;
    foreach ([false, true] as $allow) {
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($self, ['allow_references' => $allow]));
        rejectsNested(static fn() => ArrayUtility::validateNestedArray($left, ['allow_references' => $allow]));
    }
    sameNested(ReflectionReference::fromArrayElement($left, 'right')->getId(),
        ReflectionReference::fromArrayElement($left['right']['left'], 'right')->getId());
};
$tests['shared array aliases in separate branches are not cycles'] = static function (): void {
    $leaf = ['n' => 1];
    $branch = ['leaf' => &$leaf];
    $input = ['left' => &$branch, 'right' => &$branch, 'leaf' => &$leaf];
    ArrayUtility::validateNestedArray($input, ['allow_references' => true], 3);
    sameNested(['n' => 1], $leaf);
    $leaf['n'] = 2;
    sameNested(2, $input['left']['leaf']['n']);
    sameNested(2, $input['right']['leaf']['n']);
    sameNested(2, $input['leaf']['n']);
    // A completed validation leaves no active reference IDs for the next call.
    ArrayUtility::validateNestedArray($input, ['allow_references' => true]);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
foreach ($tests as $name => $run) {
    $run();
    echo 'PASS: ', $name, PHP_EOL;
}
restore_error_handler();
echo count($tests), ' scenarios passed.', PHP_EOL;
