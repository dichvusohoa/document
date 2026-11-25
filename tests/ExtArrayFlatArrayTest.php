<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray;
use Core\Normalization\UploadFilesSchema;

function sameFlat(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$arrSource = ['docs' => [3 => 'a', 8 => 'b'], 'empty' => [], 'skip' => ['hidden' => 1], 'discard' => 2];
$tree = new ExtArray($arrSource);
$tree->setDefineLeafFn(function (array $arrPath, mixed $mixValue): array|int {
    if ($arrPath === ['skip']) { return ExtArray::EXCLUDED; }
    if ($arrPath === ['discard']) { return ExtArray::DISCARDED_LEAF; }
    return is_array($mixValue) ? ExtArray::BRANCH_OR_PETIOLE : [];
});
$iProcess = 0;
$tree->setProcessLeafFn(function (array $arrPath, mixed &$mixValue, array|int $mixDefinition) use (&$iProcess): void {
    ++$iProcess;
});
$fnUnexpected = function (): void { throw new RuntimeException('Unexpected branch callback.'); };
$tree->setStartBranchFn($fnUnexpected);
$tree->setEndBranchFn($fnUnexpected);
$arrBefore = $arrSource;
sameFlat([serialize(['docs', 3]) => 'a', serialize(['docs', 8]) => 'b'], $tree->toFlatArray());
sameFlat(0, $iProcess);
sameFlat($arrBefore, $arrSource);
sameFlat([serialize(['docs', 3]) => [['docs', 3], 'a', []],
    serialize(['docs', 8]) => [['docs', 8], 'b', []]],
    $tree->toFlatArray(function (array $arrPath, mixed $mixValue, array|int $mixDefinition): array {
        return [$arrPath, $mixValue, $mixDefinition];
    }));
try {
    $tree->toFlatArray(function (): never { throw new DomainException('map failed'); });
    throw new RuntimeException('Expected mapping exception.');
} catch (DomainException $e) {
    sameFlat('map failed', $e->getMessage());
}
sameFlat($arrBefore, $arrSource);
// Original processor remains installed, even after a mapping exception.
$tree->setStartBranchFn(function (): void {});
$tree->setEndBranchFn(function (): void {});
$tree->traverseTree();
sameFlat(2, $iProcess);

foreach ([ExtArray::PETIOLE_DEFAULT_NAME, '__policy__'] as $strPetiole) {
    ExtArray::setPetioleName($strPetiole);
    try {
        // Inherited API works with a schema that locks its leaf callbacks.
        $schema = new UploadFilesSchema();
        $schema->setValue([], ['max_size' => 100]);
        $schema->setValue(['docs'], ['min_count' => 1]);
        $arrBefore = $schema->getData();
        sameFlat([serialize([]) => ['max_size' => 100], serialize(['docs']) => ['min_count' => 1]], $schema->toFlatArray());
        sameFlat($arrBefore, $schema->getData());
        $arrSolitary = [$strPetiole => 'value'];
        $tree = new ExtArray($arrSolitary);
        $tree->setDefineLeafFn(function (mixed $mixValue): array|int {
            return is_array($mixValue) ? ExtArray::BRANCH_OR_PETIOLE : [];
        });
        sameFlat([serialize([]) => 'value'], $tree->toFlatArray());
        sameFlat([$strPetiole => 'value'], $arrSolitary);
    } finally {
        ExtArray::setPetioleName(ExtArray::PETIOLE_DEFAULT_NAME);
    }
}

$iValue = 4;
$arrLeaf = ['nested' => ['ref' => &$iValue]];
$tree = new ExtArray($arrLeaf);
$tree->setDefineLeafFn(function (mixed $mixValue): int { return ExtArray::COMPOSITE_LEAF; });
$arrFlat = $tree->toFlatArray();
$arrFlat[serialize([])]['nested']['ref'] = 99;
sameFlat(4, $iValue);
$tree->toFlatArray(function (array $arrPath, mixed $mixValue): array {
    $mixValue['nested']['ref'] = 10;
    return $mixValue;
});
sameFlat(4, $iValue);

$tree = new ExtArray();
$tree->setDefineLeafFn(function (mixed $mixValue): int { return ExtArray::BRANCH_OR_PETIOLE; });
sameFlat([], $tree->toFlatArray());
echo "ExtArray toFlatArray tests passed.\n";
