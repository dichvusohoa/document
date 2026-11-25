# AtomicNormalizer — Design Transfer

**Subsystem:** PBT Framework atomic data normalization and validation  
**Prepared:** 2026-10-18  
**Purpose:** transfer design decisions into a Codex session that can inspect the framework source.

> This document records the design as discussed, not a claim that the current repository implements it. Generated code and pasted snapshots are called out separately from confirmed decisions. Status labels: **CONFIRMED**, **PROPOSED**, **OPEN**, **SUPERSEDED**.

## A. Overview

### Purpose

`AtomicNormalizer` processes one atomic value against an atomic schema. It supports validation alone and validation with a modest, type-specific conversion into canonical form. It is the foundation intended to be reused by higher-level data processors.

### Scope

Atomic types discussed:

- `int`
- `float`
- `decimal`
- `string`
- `bool`
- `date`
- `time`
- `datetime`

Semantic string formats are expressed as a string schema constraint, for example:

```php
[
    'type' => 'string',
    'format' => 'email',
]
```

The design deliberately distinguishes canonical type (`string`) from semantic format (`email`).

### Place in PBT

`AtomicNormalizer` is the atomic layer under JSON and table processing. The planned `JsonNormalizer` uses it for atomic leaves while user-defined processing handles composite leaves. The planned `TableDAUNormalizer` reuses it for field values in delete/add/update payloads. `ExtArray` is the traversal engine for nested structures; it is not part of atomic value semantics.

## B. Architecture

### `AtomicSchema`

**CONFIRMED responsibility:** validate the asymmetric array schema contract for atomic types. The allowed fields depend on `type`. It does not normalize runtime input and does not own runtime error codes.

It provides `isValid()` and type-specific helpers such as `isValidIntSchema()`, `isValidStringSchema()`, and `isValidDecimalSchema()`. Schema errors are rejected before the value-processing path. A bad schema causes `InvalidArgumentException` from the normalizer APIs.

The schema validator also checks a declared `default_value` (older drafts spell it `defaultValue`): it must already be canonical and satisfy that type's constraints. Defaults are not normalized on declaration.

### `AtomicNormalizer`

**CONFIRMED responsibility:** validate and optionally canonicalize one atomic value. It owns runtime error constants, performs type-specific processing, then applies constraints, and returns a compact result rather than a full `Response`.

### `StringFormatValidator`

**CONFIRMED name and role:** a focused helper for semantic formats of `string`. It validates values; it does not normalize strings and is not a schema class. The agreed formats are `email`, `http_url`, and `uuid`. Both `AtomicSchema` (to validate string defaults) and `AtomicNormalizer` (to validate runtime values) use it.

The discussed public method is:

```php
public static function isValidFormat(string $value, string $strFormat): bool
```

Its precise implementation details and namespace must be checked against the current source before integration.

### Higher-level consumers

- `JsonNormalizer`: processes a nested document over multiple steps, using `AtomicNormalizer` on atomic leaves. Composite-leaf work is supplied by application callbacks. Errors are accumulated by data path.
- `TableDAUNormalizer`: planned processor for delete/add/update table changes; field atomic schemas and `AtomicNormalizer` provide value rules.
- `ExtArray`: generic tree traversal and leaf/branch/petiole support; it should not absorb atomic validation rules.
- `ValidUtility`: its future responsibility is narrower than its original broad name suggested. Atomic type validation belongs here in `AtomicNormalizer`; simple array/config structure helpers can remain in the utility (a rename such as `ValidArrayUtility` was discussed, but is not a confirmed change in this subsystem).

### Namespace discrepancy

**OPEN / verify in source:** the intended normalization namespace was discussed as `Core\Data\Normalization`. Pasted drafts in the recent exchange instead used `Core\Normalization`. Do not silently pick one from chat-generated files; inspect Composer PSR-4 mappings and the active source tree.

## C. Current Design Contracts

### Schema contract

**CONFIRMED at design level:** schema is an associative PHP array with `type` as discriminator. It is asymmetric: each type admits its own set of keys. `AtomicSchema` is the contract validator, not a runtime result object.

Common concepts:

| Field | Contract discussed |
|---|---|
| `type` | Required canonical type name. |
| `nullable` | Optional boolean; default is false. Controls how a `null` input is handled. |
| `default_value` | Optional canonical default. This spelling is the latest design note; older generated/pasted code uses `defaultValue`, so source and callers must be checked. |
| `gt`, `gte`, `lt`, `lte` | Strict/inclusive range bounds. At most one lower bound (`gt` or `gte`) and at most one upper bound (`lt` or `lte`). |

Type-specific fields discussed:

| Type | Additional fields / rules |
|---|---|
| `int` | Integer range bounds. |
| `float` | Finite numeric range bounds. Exact comparison follows PHP float precision. |
| `decimal` | String representation, exact range comparison, optional `scale`. Do not route through binary float. |
| `string` | `trim`, `min_length`, `max_length`, `format`. Length is Unicode code-point length after normalization. |
| `bool` | No range constraint. |
| `date`, `time`, `datetime` | Canonical temporal strings and same-type range bounds. |

String formats confirmed in the latest design discussion:

```php
AtomicSchema::FORMAT_EMAIL     // 'email'
AtomicSchema::FORMAT_HTTP_URL  // 'http_url'
AtomicSchema::FORMAT_UUID      // 'uuid'
```

The values are formats on `type=string`, not separate atomic types. `AtomicSchema` must reject unknown format names. A string `default_value`, when present, must satisfy its declared format too.

### Public API

The agreed wrapper design is:

```php
public static function normalize(mixed $value, array $arrSchema): array;
public static function validate(
    mixed $value,
    array $arrSchema,
    bool $strict = true
): array;
public static function isValid(
    mixed $value,
    array $arrSchema,
    bool $strict = true
): bool;
```

The single internal path is:

```php
private static function process(
    mixed $value,
    array $arrSchema,
    bool $strict
): array;
```

`normalize()` delegates with `strict=false`. `validate()` delegates with the caller's `strict`. `isValid()` calls `validate()` and returns whether its error number is `ERR_NONE`.

### Strictness

**CONFIRMED:** `strict` describes acceptance of non-canonical input; it is independent of PHP's `declare(strict_types=1)`.

- `strict=true`: a non-null value must already be canonical. No coercion/formatting is applied to make it canonical.
- `strict=false`: the processor may perform the limited conversion defined for the type, then validate the canonical candidate.
- `validate(..., strict=false)` follows the same conversion behavior as `normalize()`.
- `normalize()` is the convenience API for non-strict processing.

The user explicitly used examples such as integer string `'123 '` to `123` and time `'9:15'` to a padded canonical form to explain the difference. Exact accepted lexical variants for every type remain source-level details to verify.

### Result and error contract

**CONFIRMED shape:** compact associative result with an integer error number and a value; no full `Response`, `extra`, or eagerly generated message.

Latest pasted code uses:

```php
[
    'err_number' => int,
    'value' => mixed,
]
```

On success, `value` is the canonical/normalized result. On failure, `value` is the original input for that call, not a partially transformed candidate. The normalized candidate is internal until all constraints pass.

An invalid schema throws `InvalidArgumentException`, including when called through `isValid()`; `isValid()` only simplifies valid value outcomes, not schema exceptions.

`showMessage(int $err_number, mixed $value, array $arrSchema): string` was present in generated/pasted code as an on-demand message API. Message strings are not stored per result, which matters when processing many table cells. Exact signature and message wording should be checked in current source.

### Error constants

Runtime error constants belong to `AtomicNormalizer`, not `AtomicSchema`. The following names/values appeared in a generated/pasted draft; treat the exact list and numbers as **PROPOSED / verify against active source**, while the ownership rule is **CONFIRMED**:

| Constant | Draft value |
|---|---:|
| `ERR_NONE` | 0 |
| `ERR_NULL_NOT_ALLOWED` | 1 |
| `ERR_TYPE` | 2 |
| `ERR_FORMAT` | 3 |
| `ERR_NOT_CANONICAL` | 4 |
| `ERR_NUMBER_OUT_OF_RANGE` | 5 |
| `ERR_NOT_FINITE` | 6 |
| `ERR_INVALID_UTF8` | 7 |
| `ERR_MIN_LENGTH` | 8 |
| `ERR_MAX_LENGTH` | 9 |
| `ERR_SCALE` | 10 |
| `ERR_GT` | 11 |
| `ERR_GTE` | 12 |
| `ERR_LT` | 13 |
| `ERR_LTE` | 14 |

## D. Algorithms

### Main `process()` sequence

The intended order is:

1. Call `AtomicSchema::isValid($arrSchema)`. If false, throw `InvalidArgumentException`.
2. Handle `null` before type conversion.
3. Copy non-null input to an internal candidate.
4. Dispatch by schema `type` to its type-specific processor, passing `strict`.
5. If type processing succeeds, validate post-normalization constraints (length/format/scale/range as relevant).
6. Return `ERR_NONE` with the candidate on success. On any value error, return the error with the original input.

Stop at the first error for one atomic value. Error aggregation by path belongs to higher-level processors such as `JsonNormalizer` and `TableDAUNormalizer`.

### Null/default branch

**CONFIRMED precedence:**

```text
if input is null:
    if nullable is true: return success(null)
    else if default_value exists: return success(default_value)
    else: return ERR_NULL_NOT_ALLOWED
```

This branch applies regardless of `strict`. `nullable=true` takes precedence over a declared default. `null` is not changed from an empty string. A non-null invalid input is not replaced by the default. A declared default is already canonical/constraint-valid because schema validation checks it before processing the input.

The use of `array_key_exists` (or equivalent) is necessary to distinguish an absent default from valid defaults such as `0`, `false`, `''`, or `null` where nullable permits null.

### Type processing and constraints

The shared contract is type conversion/canonical validation first, then constraints. Below are rules explicitly discussed versus implementation details that appeared only in generated drafts.

| Type | Confirmed design | Draft details to verify, not assumed final |
|---|---|---|
| `int` | Canonical output is PHP integer; lenient mode can convert numeric string such as `'123 '`; range constraints apply. | Whether `+`, leading zeros, all ASCII whitespace, and exact overflow handling are accepted. A generated draft trimmed, accepted sign/leading zeros, and checked PHP integer bounds before cast. |
| `float` | Canonical output is finite PHP float; range constraints use PHP float precision. | Generated draft accepted int/numeric strings only in non-strict mode, including exponent notation, and rejected overflow/underflow; verify exact lexical policy. |
| `decimal` | Exact decimal is represented as string; do not use float; optional `scale` limits fractional digits without implicit rounding/padding. | Generated draft normalized sign/leading zeros in non-strict mode and retained trailing fractional zeros; verify exact canonical grammar and negative-zero policy. |
| `string` | Require string; support trim option, UTF-8 validity, min/max length by Unicode code points, and semantic `format`. | Generated draft used ASCII whitespace trimming and treated strict trim as `ERR_NOT_CANONICAL`; verify exact whitespace semantics. |
| `bool` | Canonical output is PHP bool. | Exact lenient inputs (0/1 and textual values) appeared in a draft, not a separately confirmed contract. |
| `date` | Canonical `Y-m-d`; real calendar date. | Exact lenient month/day padding and accepted lexical forms need source verification. |
| `time` | Canonical `H:i:s`; user example `'9:15'` should become padded canonical time in non-strict mode. | Generated draft used `09:15:00`, allowed omitted seconds as `00`, rejected timezone/microseconds; verify these edges. |
| `datetime` | Canonical `Y-m-d H:i:s`, no additional timezone decision was established. | Generated draft accepted `T` or space leniently, padded components, and rejected timezone/microseconds; verify. |

`StringFormatValidator::isValidFormat()` runs as a value constraint after string canonicalization. It should return false for an unsupported format; schema validation should prevent unsupported names from reaching normal runtime processing.

## E. Confirmed Design Decisions

| Decision | Reason | Effect |
|---|---|---|
| Separate schema contract (`AtomicSchema`) from runtime value processing (`AtomicNormalizer`). | Schema validation and runtime errors are different responsibilities; avoids cycles and keeps runtime codes out of schema. | The normalizer validates schema first and throws for invalid schema. |
| Use one internal `process(value, schema, strict)` path. | Prevent normalization and validation logic from drifting or being duplicated. | Public methods are thin wrappers; `isValid()` wraps `validate()`. |
| Strictness is a parameter describing canonical-input acceptance. | Atomic transformations are intentionally light and vary by type. | `strict=false` can normalize; `strict=true` rejects non-canonical non-null input. |
| Atomic result is compact and contains error number plus value. | Avoids full response objects, repeated messages, and per-cell message overhead. | Higher-level layers attach paths/aggregate errors; messages are generated on demand. |
| On failure return the original input, not a partially mutated candidate. | Makes error reporting predictable and prevents partial conversion from escaping. | Use an internal candidate and commit only on full success. |
| Null policy: nullable wins; otherwise use default; otherwise error. | Allows DB/config defaults while preserving explicit nullable semantics. | Null branch runs before type processing and is independent of strictness. |
| `default_value` is canonical and validated in the schema layer. | Defaults should not be silently repaired, and runtime should not normalize a schema default. | Input `null` can return the already valid default directly. |
| Semantic string formats remain `type=string` plus `format`. | Email/URL/UUID describe string semantics rather than new canonical PHP types. | `AtomicSchema` validates allowed format names; `StringFormatValidator` validates values. |
| The helper is named `StringFormatValidator`. | It only validates formats; `StringAtomicNormalizer` falsely implied normalization. | `AtomicSchema` and `AtomicNormalizer` call the validator. |
| Runtime error constants are owned by `AtomicNormalizer`. | It creates runtime results/errors; schema class does not. | Error code domain is not part of schema contract. |
| `ValidUtility` is not responsible for atomic, JSON, or table data validation. | These now have dedicated normalizers/schema contracts; utilities can focus on basic array/config helpers. | Avoid duplicating atomic checks in `ValidUtility`. |

## F. Superseded / Rejected Designs

| Earlier idea | Status and replacement |
|---|---|
| `process(value, schema, strict, changeValue)` | **SUPERSEDED.** `changeValue` was removed; the agreed core needs only `strict`. Strictness selects canonical-only validation versus lenient conversion. |
| Separate unrelated implementations for `normalize()` and `validate()` | **SUPERSEDED.** Both delegate to `process()`. `normalize()` uses non-strict; `validate()` takes strict. |
| Atomic `email` as a separate `type` | **SUPERSEDED.** Use `type=string, format=email`; same pattern applies to HTTP URL and UUID. |
| Many tiny classes/files `EmailFormatValidator`, `HttpUrlFormatValidator`, `UuidFormatValidator` plus a dispatcher named `StringAtomicNormalizer` | **SUPERSEDED.** Consolidate the format checks in one `StringFormatValidator` file/class. |
| `StringAtomicNormalizer` name | **SUPERSEDED.** Renamed because the helper checks formats and performs no normalization. |
| Full `Response` or stored message per atomic result | **REJECTED.** Return compact error/value data and create message on demand. |
| Numeric tuple result `[0 => ..., 1 => ...]` | Earlier question only; latest pasted draft uses associative keys. Treat associative `err_number`/`value` as the latest design snapshot and verify actual public contract in source. |
| `AtomicSchema` holding runtime error codes | **REJECTED.** Codes belong to `AtomicNormalizer`. |
| `ValidUtility` as the general home for all primitive and structured validation | **SUPERSEDED for atomic values.** Atomic/json/table processing is outside its future responsibility. A rename such as `ValidArrayUtility` remains unconfirmed. |

## G. Implementation Status

### Known from discussion

- PHP drafts for `AtomicSchema`, `AtomicNormalizer`, and the consolidated format validator were generated and edited in the chat workspace.
- A recent generated/pasted `AtomicNormalizer` draft had a `validateConstraints()` branch that handled only email using `filter_var()`. The intended update was to dispatch all string formats through `StringFormatValidator::isValidFormat()`.
- A recent generated/pasted `AtomicSchema` draft added `FORMAT_EMAIL`, `FORMAT_HTTP_URL`, and `FORMAT_UUID`, whitelisted them in `isValidStringSchema()`, and used the format validator for a string default.
- The user explicitly settled the helper class name as `StringFormatValidator` and that both schema and normalizer use it.

### Not established

- None of the above proves that the current Git working tree contains those edits. This task did not inspect the active PBT source repository.
- Chat-generated files and pasted snapshots had namespace/spelling differences. Do not call them the repository's current implementation without checking the actual files.
- Runtime tests and PHP lint were not confirmed in this transfer context.

## H. Open Issues

1. **Namespace and PSR-4 location:** `Core\Data\Normalization` is the architectural namespace found in earlier design context; recent pasted drafts used `Core\Normalization`. Confirm Composer mapping and active folder.
2. **Schema key spelling:** latest design notes say `default_value`; earlier snapshots use `defaultValue`. Confirm the current intended public schema vocabulary and update all consumers consistently.
3. **Result key spelling:** `errNumber` appeared in earlier discussion/code; later pasted code used `err_number`. Confirm the final public result key and preserve it across JSON/table adapters.
4. **Exact per-type lexical grammar:** broad strict/non-strict behavior is confirmed, but many edge cases in generated code (whitespace set, sign, leading zeros, exponent, underflow, negative zero, omitted temporal seconds) were not individually confirmed.
5. **Format semantics:** the three format names and single validator class are confirmed. Exact accepted syntax is not fully specified by design. In particular, determine the intended handling of IDN email/hosts, URL userinfo/ports/IPv6/percent-encoding, and UUID version/variant restrictions before treating a particular regex/filter behavior as contract.
6. **Error namespace across layers:** JSON composite callbacks supply their own `err_number`; atomic codes may numerically collide. The path aggregation contract exists, but a shared/global versus layer-specific error-code namespace was raised and not resolved here.
7. **`AtomicSchema` object versus array argument:** earlier API discussion entertained array or `AtomicSchema`; the later class design treats `AtomicSchema` as an array-contract validator. Confirm source API remains array-only.
8. **`showMessage()` detail:** on-demand generation is established, but exact signature/message localization policy should be checked against current source.

## I. Compatibility and Dependencies

- `AtomicSchema` must whitelist the supported format strings and validate a declared string default against the same format validator used at runtime.
- `AtomicNormalizer` must dispatch `format` after string canonicalization and report failures as `ERR_FORMAT`; it should not duplicate email/URL/UUID logic.
- `StringFormatValidator` must use the same format constant values as `AtomicSchema`; avoid duplicate literals where the source design permits constants to be shared.
- `JsonNormalizer` expects atomic results and maps failures to a data path. Its composite UDA error values are a separate integration concern.
- `TableDAUNormalizer` is expected to reuse atomic processing for each field and avoid constructing messages for every cell unless requested.
- The key names `default_value` and `err_number` affect schema declarations, payload adapters, errors, and tests; migration must be coordinated if these are the final spellings.
- Keep compatibility with PHP 8.0.13. Drafts used PHP 8 features such as `mixed`, `match`, named union-like docblocks, and array unpacking; verify every syntax feature against that runtime.

## J. Test Scenarios

No tests below are claimed as already executed. These are **PROPOSED tests** to verify the contract after checking current source.

### API and result behavior

- `normalize()` uses lenient processing; `validate($value, $schema, false)` returns the same normalized value.
- `validate(..., true)` rejects non-canonical values but accepts canonical values.
- `isValid()` returns exactly whether the delegated result has `ERR_NONE`.
- On success result value is canonical; on failure result value is byte-for-byte/the same PHP value as original input, not the partially modified candidate.
- Invalid schema throws `InvalidArgumentException` through all public APIs, including `isValid()`.
- Result contains only the agreed error number and value fields; no message/Response fields.

### Null/default behavior

- `null` with `nullable=true` returns success/null even if a default exists.
- `null` with `nullable=false` and valid `default_value` returns the default in strict and non-strict modes.
- `null` with `nullable=false` and no default returns `ERR_NULL_NOT_ALLOWED`.
- A non-null invalid input does not fall back to a default.
- Defaults `0`, `false`, `''`, and permitted `null` are distinguished from a missing default.
- Invalid/non-canonical/out-of-constraint defaults make the schema invalid.

### Type behavior

- Integer: canonical PHP int; lenient conversion of `'123 '`; lower/upper boundary and PHP integer overflow cases.
- Float: finite canonical float; values around overflow/underflow; bool and non-finite rejection; verify exact accepted strings.
- Decimal: exact string comparisons, no float conversion, scale boundary, trailing zeros, negative zero, malformed exponent/leading-zero forms.
- String: invalid UTF-8, trim strictness, Unicode length counts, min/max boundaries, range of format failures.
- Bool: canonical bool and each explicitly supported non-strict representation; reject ambiguous truthy strings.
- Date/time/datetime: canonical success, malformed calendar/time, padding behavior, timezone/microsecond boundaries, strict-versus-lenient cases.
- Range: only one of `gt/gte` and one of `lt/lte`; each strict/inclusive boundary; empty/impossible ranges; exact decimal and temporal comparisons.

### String formats

- Valid and invalid email inputs according to the chosen PHP/IDN policy.
- Absolute `http` and `https` URLs; reject other schemes, missing host, whitespace/control bytes, malformed ports, and port > 65535 if that remains the selected policy.
- UUID canonical grouping, upper/lower hex, malformed groups, braces/URN prefix, and version/variant bits according to the decision eventually made.
- A valid-looking value with an unknown format name is rejected by schema validation, not misreported as a runtime unknown format.
- String `default_value` and ordinary runtime string values use the same validator.

### Integration

- JSON atomic leaves propagate atomic `err_number` and preserve path/value semantics.
- Table add/update fields reuse the same normalization behavior; message generation stays on demand.
- Check/codify how atomic error numbers coexist with composite UDA errors.

## K. Next Steps

1. Inspect the active repository versions of `AtomicSchema`, `AtomicNormalizer`, `StringFormatValidator`, `JsonNormalizer`, and Composer PSR-4 configuration.
2. Resolve namespace and public key spelling (`default_value` versus `defaultValue`; `err_number` versus `errNumber`) before editing callers.
3. Confirm exact per-type strict/non-strict lexical rules and the intended email/URL/UUID acceptance policy.
4. Verify that schema validation and runtime validation call the same `StringFormatValidator` for string defaults and normal values.
5. Decide how JSON composite errors and atomic error constants coexist.
6. Implement only the confirmed contract, then lint and run the proposed boundary/error tests under PHP 8.0.13.

### Ready to implement once source names are aligned

- Keep one core `process($value, $schema, $strict)` with thin public wrappers.
- Handle `null`/nullable/default before type conversion.
- Use a private candidate and return original input on failure.
- Apply constraints after type processing.
- Route email/HTTP URL/UUID checks through the single `StringFormatValidator`.
- Keep runtime codes in `AtomicNormalizer`; generate messages only on demand.
