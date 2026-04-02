<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';

use AutomaticSemver\CLI;
use AutomaticSemver\DiffEntries;
use AutomaticSemver\DiffSection;
use AutomaticSemver\DiffReport;
use AutomaticSemver\DiffReportState;
use AutomaticSemver\DiffReportStateFactory;
use AutomaticSemver\DiffReportRenderer;
use AutomaticSemver\FileSearch\SystemFile;
use AutomaticSemver\IncrementDecider;
use AutomaticSemver\VersionIncrement;
use AutomaticSemver\ReportIdentity;
use AutomaticSemver\SignatureBuckets;
use AutomaticSemver\SignatureDiffSnapshot;
use AutomaticSemver\RevisionRange;
use AutomaticSemver\SignatureBucket;
use AutomaticSemver\SemVerDiff;
use AutomaticSemver\Signature\CallableIdentity;
use AutomaticSemver\Signature\CallableSignature;
use AutomaticSemver\Signature\ConstantIdentity;
use AutomaticSemver\Signature\ContractIdentity;
use AutomaticSemver\Signature\ContractSignature;
use AutomaticSemver\Signature\ConstantSignature;
use AutomaticSemver\Signature\DefaultValue;
use AutomaticSemver\Signature\ParameterIdentity;
use AutomaticSemver\Signature\ContainerIdentity;
use AutomaticSemver\Signature\IdentityKey;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\NamespaceConstantIdentity;
use AutomaticSemver\Signature\NamespaceConstantSignature;
use AutomaticSemver\Signature\NamespaceIdentity;
use AutomaticSemver\Signature\ParameterSignature;
use AutomaticSemver\Signature\PropertyIdentity;
use AutomaticSemver\Signature\PrefixedSignature;
use AutomaticSemver\Signature\PropertySignature;
use AutomaticSemver\Signature\TraitUseIdentity;
use AutomaticSemver\Signature\TraitUseSignature;
use AutomaticSemver\Signature\TypeReference;
use AutomaticSemver\SignatureSearch;

interface SemanticallyEqualIdentity {}

function assertSameValue(string $message, $expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Expected '" . var_export($expected, true) . "', got '" . var_export($actual, true) . "'.");
    }
}

function assertContainsText(string $message, string $needle, string $haystack): void {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . " Missing '" . $needle . "'.");
    }
}

function assertTrue(string $message, bool $condition): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertThrowsContains(string $message, string $expectedText, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        if (strpos($throwable->getMessage(), $expectedText) === false) {
            throw new RuntimeException($message . " Expected exception containing '" . $expectedText . "', got '" . $throwable->getMessage() . "'.");
        }
        return;
    }

    throw new RuntimeException($message . ' Expected an exception to be thrown.');
}

function assertSameList(string $message, array $expected, array $actual): void {
    sort($expected);
    sort($actual);
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . " Expected '\n" . implode("\n", $expected)
            . "\n', got '\n" . implode("\n", $actual) . "\n'."
        );
    }
}

function runCommand(string $command, ?string $cwd = null): void {
    if ($cwd !== null) {
        $command = 'cd ' . escapeshellarg($cwd) . ' && ' . $command;
    }

    $output = [];
    $resultCode = 0;
    exec($command . ' 2>&1', $output, $resultCode);
    if ($resultCode !== 0) {
        throw new RuntimeException("Command failed: $command\n" . implode("\n", $output));
    }
}

function writeFile(string $path, string $contents): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Failed to create directory '$directory'.");
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Failed to write '$path'.");
    }
}

function createRepository(string $name, array $files): string {
    $root = sys_get_temp_dir() . '/php-autosemver-' . $name . '-' . uniqid('', true);
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException("Failed to create repository '$root'.");
    }

    runCommand('git init -q', $root);
    runCommand('git config user.name "James Buncle"', $root);
    runCommand('git config user.email "jbuncle@hotmail.com"', $root);

    foreach ($files as $relativePath => $contents) {
        writeFile($root . '/' . $relativePath, $contents);
    }

    runCommand('git add .', $root);
    runCommand('git commit -q -m "Initial commit"', $root);

    return $root;
}

function commitAll(string $root, string $message): void {
    runCommand('git add .', $root);
    runCommand('git commit -q -m ' . escapeshellarg($message), $root);
}

function runWithArgv(array $newArgv, callable $callback): void {
    global $argv;
    $originalArgv = $argv;
    $argv = $newArgv;
    try {
        $callback();
    } finally {
        $argv = $originalArgv;
    }
}

function getSignaturesForFiles(string $root, array $files): array {
    $search = new SignatureSearch();
    $fileObjects = array_map(function (string $relativePath) use ($root): SystemFile {
        return new SystemFile($root, $relativePath);
    }, $files);

    return $search->getSignatures($fileObjects);
}

function supportsByReferenceParsing(): bool {
    $previousHandler = set_error_handler(function (): bool {
        return true;
    });

    try {
        $parser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::PREFER_PHP7);
        $parser->parse('<?php function &pool(&$items) {}');
        return true;
    } catch (Throwable $throwable) {
        return false;
    } finally {
        restore_error_handler();
    }
}


function testLegacySignatureModelsRenderCurrentStrings(): void {
    $callable = new CallableSignature('->', 'demo', [
        new ParameterSignature(new TypeReference('string')),
        new ParameterSignature(new TypeReference('int'), false, new DefaultValue('0')),
    ], new TypeReference('?\Vendor\Thing'), ['protected', 'final'], true);
    assertSameValue('Callable signature models should render the current legacy format.', '->{protected final demo(string, int = 0):?\Vendor\Thing}', $callable->toLegacyString());
    assertSameValue('Callable signature models should support string casting.', '->{protected final demo(string, int = 0):?\Vendor\Thing}', (string) $callable);
    assertContainsText('Callable signature identity should carry structural information.', 'callable|dispatch:->|name:demo', $callable->toIdentityKey());

    $property = new PropertySignature('counter', 'protected', true, new TypeReference('int'));
    assertSameValue('Property signature models should render the current legacy format.', 'protected static int $counter', $property->toLegacyString());
    assertSameValue('Property signature identity should be structural.', 'property|name:counter|visibility:protected|static:1|type:int', $property->toIdentityKey());

    $constant = new ConstantSignature('STATUS', "'ok'", 'protected');
    assertSameValue('Constant signature models should render the current legacy format.', "protected ::STATUS = 'ok'", (string) $constant);
    assertSameValue('Constant signature identity should be structural.', "constant|name:STATUS|visibility:protected|value:'ok'", $constant->toIdentityKey());
}


function testNamespaceConstantSignatureModelsRenderCurrentStrings(): void {
    $signature = new NamespaceConstantSignature('STATUS', "'ok'");
    assertSameValue('Namespace constant signatures should render the current legacy format.', "STATUS = 'ok'", $signature->toLegacyString());
    assertSameValue('Namespace constant signatures should support string casting.', "STATUS = 'ok'", (string) $signature);

    $identity = new NamespaceConstantIdentity('STATUS', "'ok'");
    assertTrue('Namespace constant identities should compare equal when name and value match.', $identity->equals(new NamespaceConstantIdentity('STATUS', "'ok'")));
    assertTrue('Namespace constant identities should detect value changes.', !$identity->equals(new NamespaceConstantIdentity('STATUS', "'no'")));
}

function testTraitUseSignatureModelsCoverUnqualifiedAliasAndMultiplePrecedenceTargets(): void {
    $alias = TraitUseSignature::forAlias(null, 'boot', null, 'private');
    assertSameValue('Trait alias signatures should support unqualified visibility-only aliases.', ' use boot as private', $alias->toLegacyString());

    $precedence = TraitUseSignature::forPrecedence(
        new TypeReference('\Vendor\SharedTrait'),
        'boot',
        [new TypeReference('\Vendor\FirstFallback'), new TypeReference('\Vendor\SecondFallback')]
    );
    assertSameValue('Trait precedence signatures should support multiple insteadof targets.', ' use \Vendor\SharedTrait::boot insteadof \Vendor\FirstFallback, \Vendor\SecondFallback', $precedence->toLegacyString());

    $identity = new TraitUseIdentity('alias', [], 'boot', null, 'private');
    assertTrue('Unqualified trait alias identities should compare equal when semantic fields match.', $identity->equals(new TraitUseIdentity('alias', [], 'boot', null, 'private')));
    assertTrue('Unqualified trait alias identities should detect modifier changes.', !$identity->equals(new TraitUseIdentity('alias', [], 'boot', null, 'protected')));
}

function testTraitUseSignatureModelsRenderCurrentStrings(): void {
    $use = TraitUseSignature::forUse([
        new TypeReference('\Vendor\SharedTrait'),
        new TypeReference('\Vendor\ExtraTrait'),
    ]);
    assertSameValue('Trait use signatures should render the current legacy format.', ' use \Vendor\SharedTrait, \Vendor\ExtraTrait', $use->toLegacyString());

    $precedence = TraitUseSignature::forPrecedence(
        new TypeReference('\Vendor\SharedTrait'),
        'boot',
        [new TypeReference('\Vendor\ExtraTrait')]
    );
    assertSameValue('Trait precedence signatures should render the current legacy format.', ' use \Vendor\SharedTrait::boot insteadof \Vendor\ExtraTrait', $precedence->toLegacyString());

    $alias = TraitUseSignature::forAlias(new TypeReference('\Vendor\SharedTrait'), 'boot', 'start', 'protected');
    assertSameValue('Trait alias signatures should render the current legacy format.', ' use \Vendor\SharedTrait::boot as protected start', $alias->toLegacyString());

    $identity = new TraitUseIdentity('alias', [new TypeReference('\Vendor\SharedTrait')], 'boot', 'start', 'protected');
    assertTrue('Trait use identities should compare equal when semantic fields match.', $identity->equals(new TraitUseIdentity('alias', [new TypeReference('\Vendor\SharedTrait')], 'boot', 'start', 'protected')));
    assertTrue('Trait use identities should detect alias target changes.', !$identity->equals(new TraitUseIdentity('alias', [new TypeReference('\Vendor\SharedTrait')], 'boot', 'run', 'protected')));
}

function testConstantIdentityAndSignatureEqualityUsesVisibility(): void {
    $left = new ConstantSignature('STATUS', "'ok'", 'protected');
    $same = new ConstantSignature('STATUS', "'ok'", 'protected');
    $differentVisibility = new ConstantSignature('STATUS', "'ok'", 'private');
    $differentValue = new ConstantSignature('STATUS', "'no'", 'protected');

    assertTrue('Constant signatures should compare equal when name, visibility, and value match.', $left->equals($same));
    assertTrue('Constant signatures should detect visibility changes.', !$left->equals($differentVisibility));
    assertTrue('Constant signatures should detect value changes.', !$left->equals($differentValue));

    $identity = new ConstantIdentity('STATUS', "'ok'", 'protected');
    assertTrue('Constant identities should compare equal when name, visibility, and value match.', $identity->equals(new ConstantIdentity('STATUS', "'ok'", 'protected')));
    assertTrue('Constant identities should detect visibility changes.', !$identity->equals(new ConstantIdentity('STATUS', "'ok'", 'private')));
}

function testParameterSignatureModelsRenderCurrentStrings(): void {
    $variadic = new ParameterSignature(new TypeReference('string'), true);
    assertSameValue('Variadic parameter signature models should render the current legacy format.', '...string', $variadic->toLegacyString());

    $byReference = new ParameterSignature(new TypeReference('array'), false, null, true);
    assertSameValue('By-reference parameter signature models should render the current legacy format.', '&array', $byReference->toLegacyString());

    $defaulted = new ParameterSignature(new TypeReference('int'), false, new DefaultValue('-1'));
    assertSameValue('Default-value parameter signature models should render the current legacy format.', 'int = -1', (string) $defaulted);
}


function testTypeReferenceModelsRenderCurrentStrings(): void {
    $type = new TypeReference('?\Vendor\Thing');
    assertSameValue('Type reference models should render the current legacy format.', '?\Vendor\Thing', (string) $type);
    assertSameValue('Type reference identity should be structural.', 'type:?\Vendor\Thing', $type->toIdentityKey());
}


function invokePrivateMethod($object, string $methodName, array $arguments = []) {
    $method = new ReflectionMethod($object, $methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $arguments);
}

function testPrefixedSignatureCanSeparateIdentityFromLegacyFormatting(): void {
    $signature = new PrefixedSignature(
        '{abstract Foo}',
        new CallableSignature('->', 'demo', [], null),
        new ContainerIdentity('class', 'Foo', true, false)
    );

    assertSameValue('Prefixed signatures should keep the current legacy rendering.', '{abstract Foo}->demo()', $signature->toLegacyString());
    assertContainsText('Prefixed signatures should use the supplied semantic identity prefix.', 'prefixed|container|kind:class|name:Foo|abstract:1|final:0|callable|dispatch:->|name:demo', $signature->toIdentityKey());
}

function testContractSignatureModelsRenderCurrentStrings(): void {
    $contract = new ContractSignature('implements', [
        new TypeReference('\Vendor\FirstContract'),
        new TypeReference('\Vendor\SecondContract'),
    ]);

    assertSameValue('Contract signature models should render the current legacy format.', ' implements \Vendor\FirstContract, \Vendor\SecondContract', $contract->toLegacyString());
    assertContainsText('Contract signature models should render structural identities.', 'contract|kind:implements|types:[type:\Vendor\FirstContract,type:\Vendor\SecondContract]', $contract->toIdentityKey());
}

function testExplicitIdentityObjectsRenderStableKeys(): void {
    $namespace = new NamespaceIdentity('\\Demo\\');
    assertSameValue('Namespace identity objects should render the current key format.', 'namespace:\\Demo\\', $namespace->toIdentityKey());

    $container = new ContainerIdentity('class', 'Foo', true, false);
    assertSameValue('Container identity objects should render the current key format.', 'container|kind:class|name:Foo|abstract:1|final:0', $container->toIdentityKey());

    $parameter = new ParameterIdentity(new TypeReference('string'), true, new DefaultValue('0'), true);
    assertSameValue('Parameter identity objects should render the current key format.', 'param|variadic:1|byref:1|type:string|default:0', $parameter->toIdentityKey());

    $callable = new CallableIdentity('->', 'demo', [$parameter], new TypeReference('?\\Vendor\\Thing'), ['protected'], true, true);
    assertContainsText('Callable identity objects should render the current key format.', 'callable|dispatch:->|name:demo|wrap:1|returnsref:1|modifiers:protected|params:[param|variadic:1|byref:1|type:string|default:0]|type:?\\Vendor\\Thing', $callable->toIdentityKey());

    $property = new PropertyIdentity('counter', 'protected', true, new TypeReference('int'));
    assertSameValue('Property identity objects should render the current key format.', 'property|name:counter|visibility:protected|static:1|type:int', $property->toIdentityKey());

    $constant = new ConstantIdentity('STATUS', "'ok'", 'protected');
    assertSameValue('Constant identity objects should render the current key format.', "constant|name:STATUS|visibility:protected|value:'ok'", $constant->toIdentityKey());

    $contract = new ContractIdentity('implements', [new TypeReference('\Vendor\Contract')]);
    assertSameValue('Contract identity objects should render the current key format.', 'contract|kind:implements|types:[type:\Vendor\Contract]', $contract->toIdentityKey());
}

function testIdentityEqualityUsesSemanticObjectComparison(): void {
    $left = new CallableIdentity(
        '->',
        'demo',
        [new ParameterIdentity(new TypeReference('string'), true, new DefaultValue('0'))],
        new TypeReference('?\\Vendor\\Thing'),
        ['protected'],
        true
    );
    $right = new CallableIdentity(
        '->',
        'demo',
        [new ParameterIdentity(new TypeReference('string'), true, new DefaultValue('0'))],
        new TypeReference('?\\Vendor\\Thing'),
        ['protected'],
        true
    );
    $different = new CallableIdentity(
        '->',
        'demo',
        [new ParameterIdentity(new TypeReference('string'), false, new DefaultValue('0'))],
        new TypeReference('?\\Vendor\\Thing'),
        ['protected'],
        true
    );

    assertTrue('Identity objects should compare equal by semantic structure.', $left->equals($right));
    assertTrue('Identity equality should detect semantic differences.', !$left->equals($different));
}

function testSemanticDiffUsesIdentityEqualityNotOnlySerializedKeys(): void {
    $diff = new SemVerDiff(sys_get_temp_dir(), [], []);
    $left = new class implements LegacySignature {
        public function toLegacyString(): string { return '\\Demo\\One->demo()'; }
        public function toIdentityKey(): string { return 'callable|name:demo'; }
        public function equals(IdentityKey $other): bool { return $other instanceof self || $other instanceof SemanticallyEqualIdentity; }
        public function __toString(): string { return $this->toLegacyString(); }
    };
    $right = new class implements LegacySignature, SemanticallyEqualIdentity {
        public function toLegacyString(): string { return '\\Demo\\One->demo()'; }
        public function toIdentityKey(): string { return 'totally-different-key'; }
        public function equals(IdentityKey $other): bool { return $other instanceof self || $other instanceof SemanticallyEqualIdentity; }
        public function __toString(): string { return $this->toLegacyString(); }
    };

    $previous = SignatureBuckets::fromSignatures([$left]);
    $current = SignatureBuckets::fromSignatures([$right]);
    $matched = $current->findMatching($left);

    assertTrue('Semantic diff lookups should use equality rather than serialized keys alone.', $matched !== null);
    assertTrue('Semantic diff setup should still build a previous bucket set.', count($previous->all()) === 1);
}

function testSignatureIndexPreservesAllDisplaysForOneIdentity(): void {
    $diff = new SemVerDiff(sys_get_temp_dir(), [], []);
    $signatures = [
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\One->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\Two->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
    ];

    $index = SignatureBuckets::fromSignatures($signatures);
    $rendered = $index->findMatching($signatures[0]);

    assertSameList('Semantic identity buckets should preserve every legacy display string for reporting.', ['\Demo\One->demo()', '\Demo\Two->demo()'], $rendered->getDisplays());
}

function testSignatureDiffSnapshotProducesEntries(): void {
    $previous = new SignatureBuckets([
        new SignatureBucket(new ReportIdentity('same'), ['sameSignature']),
        new SignatureBucket(new ReportIdentity('removed'), ['removedSignature']),
    ]);
    $current = new SignatureBuckets([
        new SignatureBucket(new ReportIdentity('same'), ['sameSignature']),
        new SignatureBucket(new ReportIdentity('new'), ['newSignature']),
    ]);

    $entries = (new SignatureDiffSnapshot($previous, $current))->toEntries();

    assertSameList('Signature snapshots should preserve unchanged buckets.', ['sameSignature'], $entries->getUnchangedDisplays());
    assertSameList('Signature snapshots should preserve new buckets.', ['newSignature'], $entries->getNewDisplays());
    assertSameList('Signature snapshots should preserve removed buckets.', ['removedSignature'], $entries->getRemovedDisplays());
}

function testSignatureDiffSnapshotCanBeBuiltFromSignatures(): void {
    $previous = [
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\One->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
    ];
    $current = [
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\One->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\Two->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:extra'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
    ];

    $entries = SignatureDiffSnapshot::fromSignatures($previous, $current)->toEntries();

    assertSameList('Signature snapshot factories should preserve unchanged semantic matches.', ['\Demo\One->demo()'], $entries->getUnchangedDisplays());
    assertSameList('Signature snapshot factories should preserve new signatures.', ['\Demo\Two->demo()'], $entries->getNewDisplays());
    assertSameList('Signature snapshot factories should not invent removed signatures.', [], $entries->getRemovedDisplays());
}

function testSignatureBucketsCanBeBuiltFromSignatures(): void {
    $signatures = [
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\One->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
        new class implements LegacySignature {
            public function toLegacyString(): string { return '\Demo\Two->demo()'; }
            public function toIdentityKey(): string { return 'callable|name:demo'; }
            public function equals(IdentityKey $other): bool { return $other->toIdentityKey() === $this->toIdentityKey(); }
            public function __toString(): string { return $this->toLegacyString(); }
        },
    ];

    $buckets = SignatureBuckets::fromSignatures($signatures);

    assertTrue('Signature bucket construction should collapse semantically matching signatures into one bucket.', count($buckets->all()) === 1);
    assertSameList('Signature bucket construction should retain every legacy display string.', ['\Demo\One->demo()', '\Demo\Two->demo()'], $buckets->findMatching($signatures[0])->getDisplays());
}

function testDiffEntriesFlattenDisplays(): void {
    $entries = new DiffEntries(
        [new SignatureBucket(new ReportIdentity('same'), ['same-one', 'same-two'])],
        [new SignatureBucket(new ReportIdentity('new'), ['new-one'])],
        [new SignatureBucket(new ReportIdentity('removed'), ['removed-one'])]
    );

    assertSameList('DiffEntries should flatten unchanged bucket displays.', ['same-one', 'same-two'], $entries->getUnchanged()->getDisplays());
    assertSameList('DiffEntries should flatten new bucket displays.', ['new-one'], $entries->getNewDisplays());
    assertSameList('DiffEntries should flatten removed bucket displays.', ['removed-one'], $entries->getRemovedDisplays());
    assertTrue('DiffEntries should detect new signatures.', $entries->hasNew());
    assertTrue('DiffEntries should detect removed signatures.', $entries->hasRemoved());
}

function testDiffEntriesCanBeBuiltFromLegacyDisplays(): void {
    $entries = DiffEntries::fromLegacyDisplays(['same-one'], ['new-one'], ['removed-one']);

    assertSameList('Legacy display construction should preserve unchanged displays.', ['same-one'], $entries->getUnchangedDisplays());
    assertSameList('Legacy display construction should preserve new displays.', ['new-one'], $entries->getNewDisplays());
    assertSameList('Legacy display construction should preserve removed displays.', ['removed-one'], $entries->getRemovedDisplays());
}

function testDiffSectionsFlattenBucketDisplays(): void {
    $section = new DiffSection([
        new SignatureBucket(new ReportIdentity('same'), ['same-one', 'same-two']),
        new SignatureBucket(new ReportIdentity('same-extra'), ['same-three']),
    ]);

    assertSameList('Diff sections should flatten bucket display strings.', ['same-one', 'same-two', 'same-three'], $section->getDisplays());
    assertTrue('Diff sections with displays should not be empty.', !$section->isEmpty());
    assertTrue('Empty diff sections should report empty state.', (new DiffSection([]))->isEmpty());
}

function testDiffReportCanBeBuiltFromBuckets(): void {
    $entries = new DiffEntries(
        [new SignatureBucket(new ReportIdentity('same'), ['sameSignature'])],
        [new SignatureBucket(new ReportIdentity('new'), ['newSignature'])],
        [new SignatureBucket(new ReportIdentity('removed'), ['removedSignature'])]
    );
    $report = DiffReport::fromEntries('from-tag', 'to-tag', $entries);

    assertTrue('Bucket-backed reports should resolve MAJOR increment values.', $report->getIncrementValue()->equals(VersionIncrement::major()));
    assertTrue('Bucket-backed reports should expose increment value objects.', $report->getIncrementValue()->equals(VersionIncrement::major()));
    assertContainsText('Bucket-backed reports should still include unchanged signatures.', "	sameSignature", $report->toString(2));
    assertContainsText('Bucket-backed reports should still include new signatures.', "	newSignature", $report->toString(1));
    assertContainsText('Bucket-backed reports should still include removed signatures.', "	removedSignature", $report->toString(1));
}

function testDiffReportStateCarriesResolvedReportState(): void {
    $range = new RevisionRange('from-tag', 'to-tag');
    $entries = DiffEntries::fromLegacyDisplays(['sameSignature'], ['newSignature'], ['removedSignature']);
    $state = new DiffReportState($range, $entries, VersionIncrement::major());

    assertSameValue('Report state should expose the from label.', 'from-tag', $state->getRange()->getFrom());
    assertSameList('Report state should expose unchanged sections.', ['sameSignature'], $state->getUnchangedSection()->getDisplays());
    assertSameList('Report state should expose new sections.', ['newSignature'], $state->getNewSection()->getDisplays());
    assertSameList('Report state should expose removed sections.', ['removedSignature'], $state->getRemovedSection()->getDisplays());
    assertTrue('Report state should carry increment values.', $state->getIncrement()->equals(VersionIncrement::major()));
}

function testDiffReportStateFactoryResolvesIncrementValues(): void {
    $factory = new DiffReportStateFactory();
    $state = $factory->create(
        new RevisionRange('from-tag', 'to-tag'),
        DiffEntries::fromLegacyDisplays(['sameSignature'], ['newSignature'], [])
    );

    assertTrue('Report state factories should resolve increment values from entry state.', $state->getIncrement()->equals(VersionIncrement::minor()));
}

function testIncrementDeciderUsesEntryState(): void {
    $decider = new IncrementDecider();

    assertSameValue('Removed entries should be MAJOR.', 'MAJOR', $decider->decide(DiffEntries::fromLegacyDisplays([], [], ['removed']))->toString());
    assertSameValue('New entries without removals should be MINOR.', 'MINOR', $decider->decide(DiffEntries::fromLegacyDisplays([], ['new'], []))->toString());
    assertSameValue('No changes should be PATCH.', 'PATCH', $decider->decide(DiffEntries::fromLegacyDisplays(['same'], [], []))->toString());
}

function testVersionIncrementBehavesLikeAValueObject(): void {
    assertTrue('Major increments should compare equal by value.', VersionIncrement::major()->equals(VersionIncrement::major()));
    assertTrue('Different increment values should not compare equal.', !VersionIncrement::major()->equals(VersionIncrement::minor()));
    assertSameValue('Version increments should preserve the current string values.', 'PATCH', VersionIncrement::patch()->toString());
    assertSameValue('Version increments should support string casting.', 'MINOR', (string) VersionIncrement::minor());
}

function testDiffReportStillExposesLegacyIncrementStrings(): void {
    $report = DiffReport::fromLegacyDisplays('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], []);

    assertSameValue('Diff reports should keep exposing the legacy increment string API for compatibility.', 'MINOR', $report->getIncrement());
    assertTrue('Diff reports should expose their resolved state object.', $report->getState()->getIncrement()->equals(VersionIncrement::minor()));
}

function testDiffReportRendererCanRenderReportState(): void {
    $renderer = new DiffReportRenderer();
    $state = (new DiffReportStateFactory())->create(
        new RevisionRange('from-tag', 'to-tag'),
        new DiffEntries(
            [new SignatureBucket(new ReportIdentity('same'), ['sameSignature'])],
            [new SignatureBucket(new ReportIdentity('new'), ['newSignature'])],
            [new SignatureBucket(new ReportIdentity('removed'), ['removedSignature'])]
        )
    );
    $rendered = $renderer->renderState($state, 2);

    assertContainsText('State-backed rendering should include the comparison header.', 'Comparing from-tag => to-tag', $rendered);
    assertContainsText('State-backed rendering should include unchanged entries.', "	sameSignature", $rendered);
    assertContainsText('State-backed rendering should include new entries.', "	newSignature", $rendered);
    assertContainsText('State-backed rendering should include removed entries.', "	removedSignature", $rendered);
    assertContainsText('State-backed rendering should append the resolved increment.', 'MAJOR', $rendered);
}

function testDiffReportRendererFormatsBucketEntries(): void {
    $renderer = new DiffReportRenderer();
    $report = DiffReport::fromEntries('from-tag', 'to-tag', new DiffEntries(
        [new SignatureBucket(new ReportIdentity('same'), ['sameSignature'])],
        [new SignatureBucket(new ReportIdentity('new'), ['newSignature'])],
        [new SignatureBucket(new ReportIdentity('removed'), ['removedSignature'])]
    ));
    $rendered = $renderer->render($report, 2);

    assertContainsText('Report rendering should include the comparison header.', 'Comparing from-tag => to-tag', $rendered);
    assertContainsText('Report rendering should include unchanged entries.', "	sameSignature", $rendered);
    assertContainsText('Report rendering should include new entries.', "	newSignature", $rendered);
    assertContainsText('Report rendering should include removed entries.', "	removedSignature", $rendered);
    assertContainsText('Report rendering should append the resolved increment.', 'MAJOR', $rendered);
}

function testRevisionRangeCarriesReportLabels(): void {
    $range = new RevisionRange('from-tag', 'to-tag');

    assertSameValue('Revision ranges should expose the from label.', 'from-tag', $range->getFrom());
    assertSameValue('Revision ranges should expose the to label.', 'to-tag', $range->getTo());
    assertSameValue('Revision ranges should preserve the current display format.', 'from-tag => to-tag', $range->toDisplayString());
}

function testDiffReportNamedConstructorsPreserveBehaviour(): void {
    $entries = DiffEntries::fromLegacyDisplays(['sameSignature'], ['newSignature'], ['removedSignature']);

    $entryReport = DiffReport::fromEntries('from-tag', 'to-tag', $entries);
    $legacyReport = DiffReport::fromLegacyDisplays('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], ['removedSignature']);

    assertTrue('Entry-backed report construction should preserve increment semantics.', $entryReport->getIncrementValue()->equals(VersionIncrement::major()));
    assertSameValue('Entry-backed reports should preserve the from label.', 'from-tag', $entryReport->getFrom());
    assertSameValue('Entry-backed reports should preserve the to label.', 'to-tag', $entryReport->getTo());
    assertTrue('Legacy-display report construction should preserve increment semantics.', $legacyReport->getIncrementValue()->equals(VersionIncrement::major()));
    assertSameValue('Legacy-display reports should preserve the from label.', 'from-tag', $legacyReport->getFrom());
    assertSameValue('Legacy-display reports should preserve the to label.', 'to-tag', $legacyReport->getTo());
    assertContainsText('Entry-backed report construction should preserve rendering.', "	sameSignature", $entryReport->toString(2));
    assertContainsText('Legacy-display report construction should preserve rendering.', "	removedSignature", $legacyReport->toString(1));
}

function testDiffReportExposesSections(): void {
    $report = DiffReport::fromLegacyDisplays('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], ['removedSignature']);

    assertSameList('Reports should expose unchanged sections.', ['sameSignature'], $report->getUnchangedSection()->getDisplays());
    assertSameList('Reports should expose new sections.', ['newSignature'], $report->getNewSection()->getDisplays());
    assertSameList('Reports should expose removed sections.', ['removedSignature'], $report->getRemovedSection()->getDisplays());
}

function testDiffReportRendererUsesReportAccessors(): void {
    $report = DiffReport::fromLegacyDisplays('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], ['removedSignature']);
    $renderer = new DiffReportRenderer();

    $rendered = $renderer->render($report, 2);

    assertContainsText('Renderer output should include unchanged signatures via the report API.', "	sameSignature", $rendered);
    assertContainsText('Renderer output should include new signatures via the report API.', "	newSignature", $rendered);
    assertContainsText('Renderer output should include removed signatures via the report API.', "	removedSignature", $rendered);
    assertContainsText('Renderer output should append the report increment.', 'MAJOR', $rendered);
}

function testSignatureIdentityKeepsCurrentDiffBehaviour(): void {
    $root = createRepository('identity-diff', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
class Foo { public function demo(string $name) {} }
PHP,
    ]);

    writeFile($root . '/src/Foo.php', <<<'PHP'
<?php
namespace Demo;
class Foo { public function demo(string $name, int $count = 0) {} }
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Identity-key diffing should preserve the current increment semantics.', 'MINOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testExcludePathsAreHonoured(): void {
    $root = createRepository('exclude-paths', [
        '.gitignore' => "",
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function stableMethod() {} }\n",
    ]);

    writeFile($root . '/tests/Support/NewSignature.php', "<?php\nnamespace Demo\\Tests;\nclass NewSignature { public function newMethod() {} }\n");

    $diff = new SemVerDiff($root, [], ['vendor', 'tests']);
    assertSameValue('Excluded paths should not affect the increment.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGitIgnoreInlineCommentsAreIgnored(): void {
    $root = createRepository('gitignore-comments', [
        '.gitignore' => "ignored.php # local scratch file\n",
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function stableMethod() {} }\n",
    ]);

    writeFile($root . '/ignored.php', "<?php\nnamespace Demo;\nclass IgnoredSignature { public function newMethod() {} }\n");

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Ignored files should not affect the increment.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGroupedAndUngroupedTypeImportsRemainEquivalent(): void {
    $root = createRepository('grouped-import-equivalence', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Thing as Alias;
use Vendor\Package\Other;
function build(?Alias $item, Other $other = null): ?Alias {}
PHP,
    ]);

    writeFile($root . '/src/Types.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\{Thing as Alias, Other};
function build(?Alias $item, Other $other = null): ?Alias {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Grouped and ungrouped type imports with the same resolved targets should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGroupedAndUngroupedConstImportsRemainEquivalent(): void {
    $root = createRepository('grouped-const-import-equivalence', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\DEFAULT_MODE;
function build($mode = DEFAULT_MODE): void {}
PHP,
    ]);

    writeFile($root . '/src/Config.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Config\{const DEFAULT_MODE};
function build($mode = DEFAULT_MODE): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Grouped and ungrouped const imports with the same resolved targets should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testTraitAliasFormattingEquivalenceIsPatch(): void {
    $root = createRepository('trait-alias-equivalence', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {
    public function boot() {}
}
class Worker {
    use SharedTrait {
        SharedTrait::boot as private;
    }
}
PHP,
    ]);

    writeFile($root . '/src/Traits.php', <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {
    public function boot() {}
}
class Worker {
    use SharedTrait {
        boot as private;
    }
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Qualified and unqualified trait aliases with the same semantic meaning should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testNamespaceConstantOrderingDoesNotBumpVersion(): void {
    $root = createRepository('namespace-constant-ordering', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
const STATUS = 'ok', MODE = 'safe';
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
const MODE = 'safe', STATUS = 'ok';
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering namespace constants without changing values should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testImplementedContractOrderingDoesNotBumpVersion(): void {
    $root = createRepository('implements-ordering', [
        'src/Worker.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\FirstContract;
use Vendor\Contract\SecondContract;
class Worker implements FirstContract, SecondContract {}
PHP,
    ]);

    writeFile($root . '/src/Worker.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\FirstContract;
use Vendor\Contract\SecondContract;
class Worker implements SecondContract, FirstContract {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering implemented contracts without changing the set should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testExtendedInterfaceOrderingDoesNotBumpVersion(): void {
    $root = createRepository('interface-extends-ordering', [
        'src/Contract.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\FirstBase;
use Vendor\Contract\SecondBase;
interface Contract extends FirstBase, SecondBase {}
PHP,
    ]);

    writeFile($root . '/src/Contract.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\FirstBase;
use Vendor\Contract\SecondBase;
interface Contract extends SecondBase, FirstBase {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering extended interfaces without changing the set should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testTraitPrecedenceOrderingDoesNotBumpVersion(): void {
    $root = createRepository('trait-precedence-ordering', [
        'src/Worker.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait { public function boot() {} }
trait FirstFallback { public function boot() {} }
trait SecondFallback { public function boot() {} }
class Worker {
    use SharedTrait, FirstFallback, SecondFallback {
        SharedTrait::boot insteadof FirstFallback, SecondFallback;
    }
}
PHP,
    ]);

    writeFile($root . '/src/Worker.php', <<<'PHP'
<?php
namespace Demo;
trait SharedTrait { public function boot() {} }
trait FirstFallback { public function boot() {} }
trait SecondFallback { public function boot() {} }
class Worker {
    use SharedTrait, FirstFallback, SecondFallback {
        SharedTrait::boot insteadof SecondFallback, FirstFallback;
    }
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering insteadof fallback traits without changing the set should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testTypeAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('type-alias-rename-equivalence', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Thing as Alias;
function build(Alias $item): Alias {}
PHP,
    ]);

    writeFile($root . '/src/Types.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Thing as RenamedAlias;
function build(RenamedAlias $item): RenamedAlias {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming a type alias without changing its resolved target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testConstantAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('constant-alias-rename-equivalence', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\DEFAULT_MODE as MODE;
function build($mode = MODE): void {}
PHP,
    ]);

    writeFile($root . '/src/Config.php', <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\DEFAULT_MODE as DEFAULT_SETTING;
function build($mode = DEFAULT_SETTING): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming a constant alias without changing its resolved target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testNamespaceConstantAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('namespace-constant-alias-rename-equivalence', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
const STATUS = Alias::DEFAULT_MODE;
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as ConfigAlias;
const STATUS = ConfigAlias::DEFAULT_MODE;
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming a class alias used in a namespace constant without changing its target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testImplementedContractAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('implements-alias-rename-equivalence', [
        'src/Worker.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\PrimaryContract as ContractAlias;
class Worker implements ContractAlias {}
PHP,
    ]);

    writeFile($root . '/src/Worker.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\PrimaryContract as RenamedContract;
class Worker implements RenamedContract {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming an implemented contract alias without changing its target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testExtendedParentAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('extends-alias-rename-equivalence', [
        'src/Child.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\BaseClass as ParentAlias;
class Child extends ParentAlias {}
PHP,
    ]);

    writeFile($root . '/src/Child.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\BaseClass as RenamedParent;
class Child extends RenamedParent {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming a parent-class alias without changing its resolved target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGroupedAndUngroupedContractImportsRemainEquivalent(): void {
    $root = createRepository('grouped-contract-import-equivalence', [
        'src/Worker.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\PrimaryContract;
use Vendor\Contract\SecondaryContract;
class Worker implements PrimaryContract, SecondaryContract {}
PHP,
    ]);

    writeFile($root . '/src/Worker.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\{PrimaryContract, SecondaryContract};
class Worker implements PrimaryContract, SecondaryContract {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Grouped and ungrouped contract imports with the same resolved targets should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testTraitUseOrderingDoesNotBumpVersion(): void {
    $root = createRepository('trait-use-ordering', [
        'src/Worker.php' => <<<'PHP'
<?php
namespace Demo;
trait FirstTrait {}
trait SecondTrait {}
class Worker {
    use FirstTrait, SecondTrait;
}
PHP,
    ]);

    writeFile($root . '/src/Worker.php', <<<'PHP'
<?php
namespace Demo;
trait FirstTrait {}
trait SecondTrait {}
class Worker {
    use SecondTrait, FirstTrait;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering trait use lists without changing the set should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSplitAndGroupedNamespaceConstantDeclarationsRemainEquivalent(): void {
    $root = createRepository('namespace-constant-declaration-shape', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
const STATUS = 'ok';
const MODE = 'safe';
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
const STATUS = 'ok', MODE = 'safe';
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Split and grouped namespace constant declarations with the same values should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testInterfaceParentAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('interface-parent-alias-rename-equivalence', [
        'src/Contract.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\BaseContract as ParentAlias;
interface Contract extends ParentAlias {}
PHP,
    ]);

    writeFile($root . '/src/Contract.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Contract\BaseContract as RenamedParent;
interface Contract extends RenamedParent {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming an extended interface alias without changing its resolved target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testClassConstantFetchAliasRenamingDoesNotBumpVersion(): void {
    $root = createRepository('class-const-fetch-alias-rename-equivalence', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
class Values {
    public const MODE = Alias::DEFAULT_MODE;
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as ConfigAlias;
class Values {
    public const MODE = ConfigAlias::DEFAULT_MODE;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Renaming a class alias used in a class constant fetch without changing its target should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGroupedAndUngroupedConstImportsRemainEquivalentInNamespaceConstants(): void {
    $root = createRepository('grouped-namespace-const-import-equivalence', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Flags\ENABLED;
const STATUS = ENABLED;
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Flags\{const ENABLED};
const STATUS = ENABLED;
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Grouped and ungrouped constant imports used in namespace constants should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGroupedAndUngroupedConstImportsRemainEquivalentInClassConstants(): void {
    $root = createRepository('grouped-class-const-import-equivalence', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Flags\ENABLED;
class Values {
    public const STATUS = ENABLED;
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Flags\{const ENABLED};
class Values {
    public const STATUS = ENABLED;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Grouped and ungrouped constant imports used in class constants should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testSplitAndGroupedPropertyDeclarationsRemainEquivalent(): void {
    $root = createRepository('property-declaration-shape', [
        'src/Fields.php' => <<<'PHP'
<?php
namespace Demo;
class Fields {
    public $one;
    public $two;
}
PHP,
    ]);

    writeFile($root . '/src/Fields.php', <<<'PHP'
<?php
namespace Demo;
class Fields {
    public $one, $two;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Split and grouped property declarations with the same members should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSplitAndGroupedClassConstantDeclarationsRemainEquivalent(): void {
    $root = createRepository('class-constant-declaration-shape', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const FIRST = 'a';
    public const SECOND = 'b';
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    public const FIRST = 'a', SECOND = 'b';
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Split and grouped class constant declarations with the same values should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testClassConstantOrderingDoesNotBumpVersion(): void {
    $root = createRepository('class-constant-ordering', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const FIRST = 'a';
    public const SECOND = 'b';
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    public const SECOND = 'b';
    public const FIRST = 'a';
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Reordering class constants without changing names or values should remain PATCH.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testIncludePathsRestrictTheSurface(): void {
    $root = createRepository('include-paths', [
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function stableMethod() {} }\n",
        'lib/Helper.php' => "<?php\nnamespace Demo;\nclass Helper { public function stableMethod() {} }\n",
    ]);

    writeFile($root . '/lib/NewApi.php', "<?php\nnamespace Demo;\nclass NewApi { public function newMethod() {} }\n");

    $diff = new SemVerDiff($root, ['src'], []);
    assertSameValue('Include paths should restrict which files are analysed.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testWorkingCopyNewSignatureIsMinor(): void {
    $root = createRepository('wc-minor', [
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function stableMethod() {} }\n",
    ]);

    writeFile($root . '/src/Bar.php', "<?php\nnamespace Demo;\nclass Bar { public function newMethod() {} }\n");

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('A new public signature in the working copy should be MINOR.', 'MINOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testGitRevisionRemovalIsMajor(): void {
    $root = createRepository('git-major', [
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function removedMethod() {} }\n",
    ]);

    writeFile($root . '/src/Foo.php', "<?php\nnamespace Demo;\nclass Foo {}\n");
    commitAll($root, 'Remove method');

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Removing a signature between revisions should be MAJOR.', 'MAJOR', $diff->diff('HEAD~1', 'HEAD')->getIncrement());
}

function testOptionalParameterAdditionIsMinor(): void {
    $root = createRepository('optional-param', [
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo { public function demo(string \$name) {} }\n",
    ]);

    writeFile($root . '/src/Foo.php', "<?php\nnamespace Demo;\nclass Foo { public function demo(string \$name, int \$count = 0) {} }\n");

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding an optional parameter should remain backward compatible and be MINOR.', 'MINOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testExplicitDefaultConstructorIsPatch(): void {
    $root = createRepository('constructor', [
        'src/Foo.php' => "<?php\nnamespace Demo;\nclass Foo {}\n",
    ]);

    writeFile($root . '/src/Foo.php', "<?php\nnamespace Demo;\nclass Foo { public function __construct() {} }\n");

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding an explicit no-arg constructor should match the implicit constructor signature.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testOptionalParameterDefaultChangeIsMajor(): void {
    $root = createRepository('default-change', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
class Foo { public function demo(string $name, int $count = 0) {} }
PHP,
    ]);

    writeFile($root . '/src/Foo.php', <<<'PHP'
<?php
namespace Demo;
class Foo { public function demo(string $name, int $count = 1) {} }
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing an optional default value currently counts as MAJOR because the formatted signature changes.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testPropertyVisibilityTighteningIsMajor(): void {
    $root = createRepository('property-visibility', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
class Foo { public $value; }
PHP,
    ]);

    writeFile($root . '/src/Foo.php', <<<'PHP'
<?php
namespace Demo;
class Foo { protected $value; }
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Tightening property visibility should remain a MAJOR change.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testImportedAliasTargetChangeIsMajor(): void {
    $root = createRepository('alias-target-change', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Original as Item;
function build(Item $item): Item {}
PHP,
    ]);

    writeFile($root . '/src/Foo.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Replacement as Item;
function build(Item $item): Item {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing the fully resolved target behind an alias should remain MAJOR.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchCapturesCurrentModelShape(): void {
    $root = createRepository('signature-shape', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Bar as Baz;
final class Foo {
    public const STATUS = 'ok';
    public $visible;
    private $hidden;

    public function demo(string $name, int $count = 0): ?Baz {}
    protected static function build(): self {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Foo.php']);
    assertSameList('The signature model should include current public/protected API shapes.', [
        '\\Demo\\{final Foo}::STATUS = \'ok\'',
        '\\Demo\\{final Foo}$visible',
        '\\Demo\\{final Foo}->__construct()',
        '\\Demo\\{final Foo}->demo(string, int = 0):?\\Vendor\\Package\\Bar',
        '\\Demo\\{final Foo}->demo(string, int):?\\Vendor\\Package\\Bar',
        '\\Demo\\{final Foo}->demo(string):?\\Vendor\\Package\\Bar',
        '\\Demo\\{final Foo}::{protected build():self}',
    ], $signatures);
}

function testSignatureSearchIgnoresPrivateMembers(): void {
    $root = createRepository('private-members', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
class Foo {
    private $hidden;
    public $visible;

    private function hiddenMethod() {}
    public function visibleMethod() {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Foo.php']);
    assertSameList('Private members should not appear in the signature set.', [
        '\\Demo\\Foo$visible',
        '\\Demo\\Foo->__construct()',
        '\\Demo\\Foo->visibleMethod()',
    ], $signatures);
}


function testSignatureSearchResolvesNullableAndImportedTypes(): void {
    $root = createRepository('type-resolution', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\Thing as Alias;
use Vendor\Package\Other;

function build(?Alias $item, Other $other = null): ?Alias {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Types.php']);
    assertSameList('Imported and nullable types should resolve to the current signature strings.', [
        '\Demo\build(?\Vendor\Package\Thing, \Vendor\Package\Other = null):?\Vendor\Package\Thing',
        '\Demo\build(?\Vendor\Package\Thing, \Vendor\Package\Other):?\Vendor\Package\Thing',
        '\Demo\build(?\Vendor\Package\Thing):?\Vendor\Package\Thing',
    ], $signatures);
}

function testSignatureSearchResolvesImportedClassConstantFetchesInValues(): void {
    $root = createRepository('class-const-fetch-resolution', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
class Settings {
    public const MODE = Alias::DEFAULT_MODE;
}
const CURRENT_MODE = Alias::DEFAULT_MODE;
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Config.php']);
    assertSameList('Imported class aliases should resolve in class constant fetch values.', [
        '\Demo\CURRENT_MODE = \Vendor\Config::DEFAULT_MODE',
        '\Demo\Settings->__construct()',
        '\Demo\Settings::MODE = \Vendor\Config::DEFAULT_MODE',
    ], $signatures);
}

function testClassConstantFetchAliasChangesAffectDiffs(): void {
    $root = createRepository('class-const-fetch-alias-diff', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
class Settings {
    public const MODE = Alias::DEFAULT_MODE;
}
PHP,
    ]);

    writeFile($root . '/src/Config.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Fallback as Alias;
class Settings {
    public const MODE = Alias::DEFAULT_MODE;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing an imported class alias used in a class constant fetch should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchResolvesImportedConstantsInDefaultsAndValues(): void {
    $root = createRepository('const-import-resolution', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\DEFAULT_MODE;
function build($mode = DEFAULT_MODE): void {}
class Settings {
    public const MODE = DEFAULT_MODE;
}
const CURRENT_MODE = DEFAULT_MODE;
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Config.php']);
    assertSameList('Imported constants should resolve in default expressions and constant values.', [
        '\Demo\CURRENT_MODE = \Vendor\Config\DEFAULT_MODE',
        '\Demo\Settings::MODE = \Vendor\Config\DEFAULT_MODE',
        '\Demo\Settings->__construct()',
        '\Demo\build():void',
        '\Demo\build(mixed = \Vendor\Config\DEFAULT_MODE):void',
        '\Demo\build(mixed):void',
    ], $signatures);
}

function testSignatureSearchResolvesGroupedConstImportsInDefaults(): void {
    $root = createRepository('grouped-const-import-resolution', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config\{const DEFAULT_MODE};
function build($mode = DEFAULT_MODE): void {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Config.php']);
    assertSameList('Grouped constant imports should resolve in default expressions.', [
        '\Demo\build():void',
        '\Demo\build(mixed = \Vendor\Config\DEFAULT_MODE):void',
        '\Demo\build(mixed):void',
    ], $signatures);
}

function testImportedConstantChangesAffectDiffs(): void {
    $root = createRepository('const-import-diff', [
        'src/Config.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\DEFAULT_MODE;
function build($mode = DEFAULT_MODE): void {}
PHP,
    ]);

    writeFile($root . '/src/Config.php', <<<'PHP'
<?php
namespace Demo;
use const Vendor\Config\FALLBACK_MODE;
function build($mode = FALLBACK_MODE): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing an imported constant alias target should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchCapturesNamespaceConstants(): void {
    $root = createRepository('namespace-constants', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
const STATUS = 'ok', DEFAULTS = ['enabled' => true];
function run(): void {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Constants.php']);
    assertSameList('Namespace constants should be part of the signature surface.', [
        "\Demo\DEFAULTS = ['enabled' => true]",
        "\Demo\STATUS = 'ok'",
        '\Demo\run():void',
    ], $signatures);
}

function testSignatureSearchFormatsRicherNamespaceConstantValues(): void {
    $root = createRepository('namespace-constant-values', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
use const Vendor\Flags\ENABLED;
const STATUS = Alias::DEFAULT_MODE,
    SETTINGS = ['enabled' => ENABLED, 'here' => __DIR__],
    CURRENT_CLASS = __CLASS__;
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Constants.php']);
    assertSameList('Namespace constants should preserve imported class constant fetches, imported constants, arrays, and magic constants.', [
        '\Demo\CURRENT_CLASS = __CLASS__',
        "\Demo\SETTINGS = ['enabled' => \Vendor\Flags\ENABLED, 'here' => __DIR__]",
        '\Demo\STATUS = \Vendor\Config::DEFAULT_MODE',
    ], $signatures);
}

function testNamespaceConstantAliasTargetChangesAffectDiffs(): void {
    $root = createRepository('namespace-constant-alias-diff', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config as Alias;
const STATUS = Alias::DEFAULT_MODE;
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\Fallback as Alias;
const STATUS = Alias::DEFAULT_MODE;
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing an imported class alias used in a namespace constant should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testNamespaceConstantImportedConstantTargetChangesAffectDiffs(): void {
    $root = createRepository('namespace-constant-import-diff', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
use const Vendor\Flags\ENABLED;
const STATUS = ENABLED;
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
use const Vendor\Flags\DISABLED;
const STATUS = DISABLED;
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing an imported constant used in a namespace constant should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testNamespaceConstantChangesAffectDiffs(): void {
    $root = createRepository('namespace-constant-diff', [
        'src/Constants.php' => <<<'PHP'
<?php
namespace Demo;
const STATUS = 'ok';
PHP,
    ]);

    writeFile($root . '/src/Constants.php', <<<'PHP'
<?php
namespace Demo;
const STATUS = 'no';
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing a namespace constant should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchResolvesGroupedTypeImports(): void {
    $root = createRepository('grouped-type-resolution', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\{Thing as Alias, Other};
function build(?Alias $item, Other $other = null): ?Alias {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Types.php']);
    assertSameList('Grouped type imports should resolve to the current signature strings.', [
        '\Demo\build(?\Vendor\Package\Thing, \Vendor\Package\Other = null):?\Vendor\Package\Thing',
        '\Demo\build(?\Vendor\Package\Thing, \Vendor\Package\Other):?\Vendor\Package\Thing',
        '\Demo\build(?\Vendor\Package\Thing):?\Vendor\Package\Thing',
    ], $signatures);
}

function testSignatureSearchIgnoresNonTypeGroupedImports(): void {
    $root = createRepository('grouped-mixed-imports', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\{Thing as Alias, function helper, const FLAG};
function build(Alias $item): Alias {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Types.php']);
    assertSameList('Grouped function and const imports should remain ignored for type resolution.', [
        '\Demo\build(\Vendor\Package\Thing):\Vendor\Package\Thing',
    ], $signatures);
}

function testSignatureSearchCapturesTypedProperties(): void {
    $root = createRepository('typed-properties', [
        'src/Model.php' => <<<'PHP'
<?php
namespace Demo;
class Model {
    public int $count;
    protected ?\DateTimeImmutable $seenAt;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Model.php']);
    assertSameList('Typed properties should be part of the signature surface.', [
        '\Demo\Model->__construct()',
        '\Demo\Modelint $count',
        '\Demo\Modelprotected ?\Demo\DateTimeImmutable $seenAt',
    ], $signatures);
}

function testTypedPropertyChangesAffectDiffs(): void {
    $root = createRepository('typed-property-diff', [
        'src/Model.php' => <<<'PHP'
<?php
namespace Demo;
class Model {
    public $count;
}
PHP,
    ]);

    writeFile($root . '/src/Model.php', <<<'PHP'
<?php
namespace Demo;
class Model {
    public int $count;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding a property type should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchPreservesProtectedAndStaticPropertyMarkers(): void {
    $root = createRepository('property-markers', [
        'src/Foo.php' => <<<'PHP'
<?php
namespace Demo;
class Foo {
    protected static $counter;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Foo.php']);
    assertSameList('Protected static properties should keep their current marker shape.', [
        '\Demo\Foo->__construct()',
        '\Demo\Fooprotected static $counter',
    ], $signatures);
}


function testSignatureSearchPreservesPhp72BuiltinAndContextualTypes(): void {
    $root = createRepository('php72-builtins', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
class BaseThing {}
class ChildThing extends BaseThing {
    public function connect(object $target, iterable $items): object {}
    protected function cloneParent(parent $source): parent {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Types.php']);
    assertSameList('PHP 7.2 built-in and contextual types should stay semantic rather than being namespaced.', [
        '\Demo\BaseThing->__construct()',
        '\Demo\ChildThing->__construct()',
        '\Demo\ChildThing extends \Demo\BaseThing',
        '\Demo\ChildThing->connect(object, iterable):object',
        '\Demo\ChildThing->{protected cloneParent(parent):parent}',
    ], $signatures);
}

function testBuiltinAndParentTypeChangesAffectDiffs(): void {
    $root = createRepository('php72-type-diff', [
        'src/Types.php' => <<<'PHP'
<?php
namespace Demo;
class BaseThing {}
class ChildThing extends BaseThing {
    public function connect($target, $items) {}
    protected function cloneParent($source) {}
}
PHP,
    ]);

    writeFile($root . '/src/Types.php', <<<'PHP'
<?php
namespace Demo;
class BaseThing {}
class ChildThing extends BaseThing {
    public function connect(object $target, iterable $items): object {}
    protected function cloneParent(parent $source): parent {}
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding PHP 7.2 built-in or contextual types should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}


function testSignatureSearchPreservesStaticClassConstantReferences(): void {
    $root = createRepository('static-class-constant-references', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const PUBLIC_VALUE = 1;
    public const CURRENT = static::PUBLIC_VALUE;
}
function build($value = static::PUBLIC_VALUE): void {}
const ACTIVE = static::PUBLIC_VALUE;
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Static class constant fetches should be preserved as contextual references in defaults and constant values.', [
        '\Demo\ACTIVE = static::PUBLIC_VALUE',
        '\Demo\Values->__construct()',
        '\Demo\Values::CURRENT = static::PUBLIC_VALUE',
        '\Demo\Values::PUBLIC_VALUE = 1',
        '\Demo\build():void',
        '\Demo\build(mixed = static::PUBLIC_VALUE):void',
        '\Demo\build(mixed):void',
    ], $signatures);
}

function testStaticClassConstantReferenceChangesAffectDiffs(): void {
    $root = createRepository('static-class-constant-diff', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const PUBLIC_VALUE = 1;
}
function build($value = self::PUBLIC_VALUE): void {}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    public const PUBLIC_VALUE = 1;
}
function build($value = static::PUBLIC_VALUE): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing self:: to static:: in a rendered signature should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchFormatsMagicConstants(): void {
    $root = createRepository('magic-constant-rendering', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
function build($dir = __DIR__, $file = __FILE__): void {}
class Info {
    public const SELF_CLASS = __CLASS__;
}
const CURRENT_NAMESPACE = __NAMESPACE__;
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Magic constants should be preserved explicitly in signatures.', [
        '\Demo\CURRENT_NAMESPACE = __NAMESPACE__',
        '\Demo\Info->__construct()',
        '\Demo\Info::SELF_CLASS = __CLASS__',
        '\Demo\build():void',
        '\Demo\build(mixed = __DIR__):void',
        '\Demo\build(mixed = __DIR__, mixed = __FILE__):void',
        '\Demo\build(mixed, mixed):void',
        '\Demo\build(mixed):void',
    ], $signatures);
}

function testMagicConstantChangesAffectDiffs(): void {
    $root = createRepository('magic-constant-diff', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
function build($value = __DIR__): void {}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
function build($value = __FILE__): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing a magic constant in a signature should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchFormatsRicherDefaultExpressions(): void {
    $root = createRepository('default-expression-shapes', [
        'src/Defaults.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Config;
function build(
    array $items = ['name' => 'demo', 'enabled' => true],
    $mode = Config::DEFAULT_MODE,
    array $mapping = [1 => Config::DEFAULT_MODE, 2 => null]
): void {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Defaults.php']);
    assertSameList('Default expressions should preserve keyed arrays, quoted strings, booleans, nulls, and class constants.', [
        "\\Demo\\build():void",
        "\\Demo\\build(array = ['name' => 'demo', 'enabled' => true], mixed = \\Vendor\\Config::DEFAULT_MODE, array = [1 => \\Vendor\\Config::DEFAULT_MODE, 2 => null]):void",
        "\\Demo\\build(array = ['name' => 'demo', 'enabled' => true], mixed = \\Vendor\\Config::DEFAULT_MODE):void",
        "\\Demo\\build(array = ['name' => 'demo', 'enabled' => true]):void",
        "\\Demo\\build(array, mixed, array):void",
        "\\Demo\\build(array, mixed):void",
        "\\Demo\\build(array):void",
    ], $signatures);
}

function testDefaultExpressionRenderingAffectsDiffs(): void {
    $root = createRepository('default-expression-diff', [
        'src/Defaults.php' => <<<'PHP'
<?php
namespace Demo;
function build(array $items = []): void {}
PHP,
    ]);

    writeFile($root . '/src/Defaults.php', <<<'PHP'
<?php
namespace Demo;
function build(array $items = ['name' => 'demo']): void {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing a richer default expression should still affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchFormatsDefaultValues(): void {
    $root = createRepository('default-values', [
        'src/Defaults.php' => <<<'PHP'
<?php
namespace Demo;
function build(array $items = [], $mode = SOME_CONST, int $offset = -1): void {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Defaults.php']);
    assertSameList('Default values should retain the current string formatting rules.', [
        '\Demo\build():void',
        '\Demo\build(array = [], mixed = \Demo\SOME_CONST, int = -1):void',
        '\Demo\build(array, mixed, int):void',
        '\Demo\build(array = [], mixed = \Demo\SOME_CONST):void',
        '\Demo\build(array, mixed):void',
        '\Demo\build(array = []):void',
        '\Demo\build(array):void',
    ], $signatures);
}

function testSignatureSearchCapturesByReferenceCallables(): void {
    if (!supportsByReferenceParsing()) {
        return;
    }

    $root = createRepository('by-reference-callables', [
        'src/Refs.php' => <<<'PHP'
<?php
namespace Demo;
function &pool(&$items) {}
class Store {
    public function &take(&$items) {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Refs.php']);
    assertSameList('By-reference parameters and returns should be part of the callable signature surface.', [
        '\Demo\&pool(&mixed):mixed',
        '\Demo\Store->__construct()',
        '\Demo\Store->&take(&mixed):mixed',
    ], $signatures);
}

function testByReferenceChangesAffectDiffs(): void {
    if (!supportsByReferenceParsing()) {
        return;
    }

    $root = createRepository('by-reference-diff', [
        'src/Refs.php' => <<<'PHP'
<?php
namespace Demo;
function pool($items) {}
PHP,
    ]);

    writeFile($root . '/src/Refs.php', <<<'PHP'
<?php
namespace Demo;
function &pool(&$items) {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding by-reference callable semantics should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchCoversVariadicStaticMethods(): void {
    $root = createRepository('variadic-static', [
        'src/Bag.php' => <<<'PHP'
<?php
namespace Demo;
class Bag {
    public static function collect(string ...$items): array {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Bag.php']);
    assertSameList('Variadic static methods should preserve their current signature string shape.', [
        '\Demo\Bag->__construct()',
        '\Demo\Bag::collect(...string):array',
    ], $signatures);
}

function testSignatureSearchCapturesInterfaceInheritanceContracts(): void {
    $root = createRepository('interface-extends', [
        'src/Contracts.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\BaseContract;
interface Contract extends BaseContract {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Contracts.php']);
    assertSameList('Interface inheritance should be part of the signature surface.', [
        '\Demo\Contract extends \Vendor\BaseContract',
    ], $signatures);
}

function testSignatureSearchCapturesTraitUseAdaptations(): void {
    $root = createRepository('trait-use-adaptations', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {}
trait ExtraTrait {}
class Worker {
    use SharedTrait, ExtraTrait {
        SharedTrait::boot insteadof ExtraTrait;
        SharedTrait::boot as protected start;
    }
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Traits.php']);
    assertSameList('Trait use declarations and adaptations should be part of the signature surface.', [
        '\Demo\Worker->__construct()',
        '\Demo\Worker use \Demo\SharedTrait, \Demo\ExtraTrait',
        '\Demo\Worker use \Demo\SharedTrait::boot as protected start',
        '\Demo\Worker use \Demo\SharedTrait::boot insteadof \Demo\ExtraTrait',
    ], $signatures);
}

function testSignatureSearchCapturesTraitAliasEdgeCases(): void {
    $root = createRepository('trait-alias-edge-cases', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {
    public function boot() {}
}
trait FirstFallback {}
trait SecondFallback {}
class Worker {
    use SharedTrait, FirstFallback, SecondFallback {
        SharedTrait::boot insteadof FirstFallback, SecondFallback;
        boot as private;
    }
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Traits.php']);
    assertSameList('Trait alias edge cases should be part of the signature surface.', [
        '\Demo\Worker use \Demo\SharedTrait, \Demo\FirstFallback, \Demo\SecondFallback',
        '\Demo\Worker use \Demo\SharedTrait::boot insteadof \Demo\FirstFallback, \Demo\SecondFallback',
        '\Demo\Worker use boot as private',
        '\Demo\Worker->__construct()',
        '\Demo\{Trait SharedTrait}->boot()',
    ], $signatures);
}

function testTraitAliasVisibilityChangesAffectDiffs(): void {
    $root = createRepository('trait-alias-visibility-diff', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {
    public function boot() {}
}
class Worker {
    use SharedTrait {
        boot as protected;
    }
}
PHP,
    ]);

    writeFile($root . '/src/Traits.php', <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {
    public function boot() {}
}
class Worker {
    use SharedTrait {
        boot as private;
    }
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing trait alias visibility should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testTraitUseAdaptationChangesAffectDiffs(): void {
    $root = createRepository('trait-use-adaptation-diff', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {}
trait ExtraTrait {}
class Worker {
    use SharedTrait, ExtraTrait {
        SharedTrait::boot insteadof ExtraTrait;
    }
}
PHP,
    ]);

    writeFile($root . '/src/Traits.php', <<<'PHP'
<?php
namespace Demo;
trait SharedTrait {}
trait ExtraTrait {}
class Worker {
    use SharedTrait, ExtraTrait {
        ExtraTrait::boot insteadof SharedTrait;
    }
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing trait use adaptations should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchCapturesClassInheritanceContracts(): void {
    $root = createRepository('class-contracts', [
        'src/Contracts.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\BaseClass;
use Vendor\PrimaryContract;
use Vendor\SecondaryContract;
class Worker extends BaseClass implements PrimaryContract, SecondaryContract {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Contracts.php']);
    assertSameList('Class inheritance contracts should be part of the signature surface.', [
        '\Demo\Worker->__construct()',
        '\Demo\Worker extends \Vendor\BaseClass',
        '\Demo\Worker implements \Vendor\PrimaryContract, \Vendor\SecondaryContract',
    ], $signatures);
}

function testInterfaceInheritanceChangesAffectDiffs(): void {
    $root = createRepository('interface-diff', [
        'src/Contract.php' => <<<'PHP'
<?php
namespace Demo;
interface Contract {}
PHP,
    ]);

    writeFile($root . '/src/Contract.php', <<<'PHP'
<?php
namespace Demo;
use Vendor\BaseContract;
interface Contract extends BaseContract {}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Adding an interface inheritance contract should affect the diff result.', 'MINOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchCoversTraitsAndInterfaces(): void {
    $root = createRepository('trait-interface', [
        'src/Contracts.php' => <<<'PHP'
<?php
namespace Demo;
trait Helper {
    protected function assist() {}
}
interface Contract {
    public function run();
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Contracts.php']);
    assertSameList('Traits and interfaces should keep their current signature representation.', [
        '\Demo\{Trait Helper}->{protected assist()}',
        '\Demo\Contract->run()',
    ], $signatures);
}


function testSignatureSearchCoversNestedNamespaceFallbackTypes(): void {
    $root = createRepository('nested-namespace', [
        'src/Nested.php' => <<<'PHP'
<?php
namespace Demo\Inner;
class LocalType {}
function build(LocalType $item): LocalType {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Nested.php']);
    assertSameList('Unimported types should fall back to the current namespace path.', [
        '\Demo\Inner\LocalType->__construct()',
        '\Demo\Inner\build(\Demo\Inner\LocalType):\Demo\Inner\LocalType',
    ], $signatures);
}

function testSignatureSearchCoversFullyQualifiedAndAbstractShapes(): void {
    $root = createRepository('fqcn-abstract', [
        'src/Shapes.php' => <<<'PHP'
<?php
namespace Demo;
abstract class Base {
    final protected function make(\DateTimeImmutable $when): \DateTimeImmutable {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Shapes.php']);
    assertSameList('Abstract/final wrappers and fully qualified names should retain their current shape.', [
        '\Demo\{abstract Base}->__construct()',
        '\Demo\{abstract Base}->{protected final make(\DateTimeImmutable):\DateTimeImmutable}',
    ], $signatures);
}

function testSignatureSearchCapturesConstantVisibility(): void {
    $root = createRepository('constant-visibility', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const PUBLIC_VALUE = 1;
    protected const PROTECTED_VALUE = 2;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Class constant visibility should be part of the signature surface.', [
        '\Demo\Values->__construct()',
        '\Demo\Values::PUBLIC_VALUE = 1',
        '\Demo\Valuesprotected ::PROTECTED_VALUE = 2',
    ], $signatures);
}

function testConstantVisibilityChangesAffectDiffs(): void {
    $root = createRepository('constant-visibility-diff', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const STATUS = 1;
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    protected const STATUS = 1;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing class constant visibility should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchFormatsClassConstantUnaryValues(): void {
    $root = createRepository('const-values', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const NEGATIVE = -1;
    public const POSITIVE = +2;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Class constant unary values should retain their current formatting.', [
        '\Demo\Values->__construct()',
        '\Demo\Values::NEGATIVE = -1',
        '\Demo\Values::POSITIVE = +2',
    ], $signatures);
}


function testSignatureSearchPrefersUseAliasesOverNamespaceFallback(): void {
    $root = createRepository('alias-precedence', [
        'src/Alias.php' => <<<'PHP'
<?php
namespace Demo;
use Vendor\Package\LocalType;
class LocalType {}
function build(LocalType $item): LocalType {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Alias.php']);
    assertSameList('Imported aliases should keep taking precedence over same-named local types.', [
        '\Demo\LocalType->__construct()',
        '\Demo\build(\Vendor\Package\LocalType):\Vendor\Package\LocalType',
    ], $signatures);
}

function testSignatureSearchCoversGroupedPropertyAndConstantDeclarations(): void {
    $root = createRepository('grouped-declarations', [
        'src/Fields.php' => <<<'PHP'
<?php
namespace Demo;
class Fields {
    public $one, $two;
    public const FIRST = 'a', SECOND = 'b';
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Fields.php']);
    assertSameList('Grouped property and constant declarations should emit separate signatures in their current shape.', [
        '\Demo\Fields$one',
        '\Demo\Fields$two',
        '\Demo\Fields->__construct()',
        '\Demo\Fields::FIRST = \'a\'',
        '\Demo\Fields::SECOND = \'b\'',
    ], $signatures);
}

function testSignatureSearchCoversGlobalNamespaceShapes(): void {
    $root = createRepository('global-namespace', [
        'src/GlobalCode.php' => <<<'PHP'
<?php
class GlobalThing {}
function make(int $value): int {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/GlobalCode.php']);
    assertSameList('Global namespace symbols should retain their current root-level signature shape.', [
        'GlobalThing->__construct()',
        'make(int):int',
    ], $signatures);
}


function testRootAnchoredGitIgnorePatternsAreHonoured(): void {
    $root = createRepository('gitignore-root-anchored', [
        '.gitignore' => "/ignored.php
",
        'src/Foo.php' => "<?php
namespace Demo;
class Foo { public function stableMethod() {} }
",
    ]);

    writeFile($root . '/ignored.php', "<?php
namespace Demo;
class IgnoredSignature { public function newMethod() {} }
");

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Root-anchored gitignore entries should ignore matching root files.', 'PATCH', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchWrapsParseFailures(): void {
    $root = createRepository('parse-failure', [
        'src/Broken.php' => "<?php
namespace Demo;
function broken( {}
",
    ]);

    assertThrowsContains('Parse failures should be wrapped with the source file path.', "Failed to process 'src/Broken.php'", function () use ($root): void {
        getSignaturesForFiles($root, ['src/Broken.php']);
    });
}

function testSignatureSearchIgnoresFunctionImports(): void {
    $root = createRepository('function-import', [
        'src/Functions.php' => <<<'PHP'
<?php
namespace Demo;
use function Vendor\helper;
function run(): void {}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Functions.php']);
    assertSameList('Non-class use imports should continue to be ignored by the signature scan.', [
        '\Demo\run():void',
    ], $signatures);
}

function testSignatureSearchFailsOnUnsupportedTopLevelStatements(): void {
    $root = createRepository('unsupported-top-level', [
        'src/Unsupported.php' => <<<'PHP'
<?php
try {
    $value = 1;
} catch (\Exception $exception) {
}
PHP,
    ]);

    $parser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::PREFER_PHP7);
    $ast = $parser->parse(file_get_contents($root . '/src/Unsupported.php'));
    $rootNamespace = new AutomaticSemver\Objects\RootNamespaceObject($ast);

    assertThrowsContains('Unsupported top-level statements should fail fast with the original node type.', 'Unsupported type PhpParser\Node\Stmt\TryCatch', function () use ($rootNamespace): void {
        $rootNamespace->getSignatures();
    });
}

function testSignatureSearchIgnoresTraitUseStatementsInsideClasses(): void {
    $root = createRepository('trait-use', [
        'src/Traits.php' => <<<'PHP'
<?php
namespace Demo;
trait Helper {
    public function assist() {}
}
class Worker {
    use Helper;
    public function run() {}
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Traits.php']);
    assertSameList('Trait use statements inside classes should be surfaced without inlining trait members into the class.', [
        '\Demo\{Trait Helper}->assist()',
        '\Demo\Worker use \Demo\Helper',
        '\Demo\Worker->__construct()',
        '\Demo\Worker->run()',
    ], $signatures);
}

function testSignatureSearchFormatsAdditionalClassConstantValueShapes(): void {
    $root = createRepository('extra-constant-values', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    private const RATE = 1.5;
    protected const ITEMS = [1.5, false, null];
    public const ENV = PHP_EOL;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Class constant values should preserve floats, plain const fetches, and unkeyed arrays.', [
        "\\Demo\\Values->__construct()",
        "\\Demo\\Valuesprivate ::RATE = 1.5",
        "\\Demo\\Valuesprotected ::ITEMS = [1.5, false, null]",
        "\\Demo\\Values::ENV = PHP_EOL",
    ], $signatures);
}

function testSignatureSearchFormatsRicherClassConstantValues(): void {
    $root = createRepository('rich-constant-values', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const DEFAULTS = ['name' => 'demo', 'enabled' => true];
    public const MODE = \Vendor\Config::DEFAULT_MODE;
    public const MAPPING = [1 => \Vendor\Config::DEFAULT_MODE, 2 => null];
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Class constant values should preserve keyed arrays, quoted strings, booleans, nulls, and class constants.', [
        "\\Demo\\Values->__construct()",
        "\\Demo\\Values::DEFAULTS = ['name' => 'demo', 'enabled' => true]",
        "\\Demo\\Values::MODE = \\Vendor\\Config::DEFAULT_MODE",
        "\\Demo\\Values::MAPPING = [1 => \\Vendor\\Config::DEFAULT_MODE, 2 => null]",
    ], $signatures);
}

function testSignatureSearchCapturesPrivateAndSelfConstantReferences(): void {
    $root = createRepository('private-self-constants', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const PUBLIC_VALUE = 1;
    private const PRIVATE_VALUE = self::PUBLIC_VALUE;
    protected const MAPPING = ['code' => self::PUBLIC_VALUE, 'enabled' => true];
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Values.php']);
    assertSameList('Class constants should preserve private/protected visibility and self:: references in current rendering.', [
        "\\Demo\\Values->__construct()",
        "\\Demo\\Values::PUBLIC_VALUE = 1",
        "\\Demo\\Valuesprivate ::PRIVATE_VALUE = self::PUBLIC_VALUE",
        "\\Demo\\Valuesprotected ::MAPPING = ['code' => self::PUBLIC_VALUE, 'enabled' => true]",
    ], $signatures);
}

function testPrivateConstantVisibilityChangesAffectDiffs(): void {
    $root = createRepository('private-constant-visibility-diff', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    protected const STATUS = 1;
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    private const STATUS = 1;
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing class constant visibility to private should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testClassConstantValueRenderingAffectsDiffs(): void {
    $root = createRepository('class-constant-value-diff', [
        'src/Values.php' => <<<'PHP'
<?php
namespace Demo;
class Values {
    public const DEFAULTS = [];
}
PHP,
    ]);

    writeFile($root . '/src/Values.php', <<<'PHP'
<?php
namespace Demo;
class Values {
    public const DEFAULTS = ['name' => 'demo'];
}
PHP
    );

    $diff = new SemVerDiff($root, [], []);
    assertSameValue('Changing a richer class constant value should affect the diff result.', 'MAJOR', $diff->diff('HEAD', 'WC')->getIncrement());
}

function testSignatureSearchFormatsPlainScalarClassConstants(): void {
    $root = createRepository('plain-const-values', [
        'src/Numbers.php' => <<<'PHP'
<?php
namespace Demo;
class Numbers {
    public const COUNT = 3;
}
PHP,
    ]);

    $signatures = getSignaturesForFiles($root, ['src/Numbers.php']);
    assertSameList('Plain scalar class constants should keep their fallback formatting.', [
        '\Demo\Numbers->__construct()',
        '\Demo\Numbers::COUNT = 3',
    ], $signatures);
}

function testCliPreloadFailuresAndUnknownOptions(): void {
    $cli = new CLI();
    assertThrowsContains('CLI should reject access to args before load.', 'Args not loaded', function () use ($cli): void {
        $cli->getFrom();
    });

    assertThrowsContains('CLI should reject access to flags before load.', 'Options not loaded', function () use ($cli): void {
        $cli->getProjectPath();
    });

    runWithArgv([
        'php-autosemver',
        '--mystery',
        'v1.2.3',
    ], function (): void {
        $cli = new CLI();
        $cli->load();

        assertSameValue('Unknown long options should remain positional arguments.', '--mystery', $cli->getFrom());
        assertSameValue('The following positional argument should still become the to revision.', 'v1.2.3', $cli->getTo());
    });
}

function testDiffReportFormatting(): void {
    $report = DiffReport::fromLegacyDisplays('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], ['removedSignature']);

    assertTrue('Removed signatures should produce a MAJOR increment.', $report->getIncrementValue()->equals(VersionIncrement::major()));
    assertContainsText('Verbosity 1 output should include the comparison header.', 'Comparing from-tag => to-tag', $report->toString(1));
    assertContainsText('Verbosity 2 output should include unchanged signatures.', "\tsameSignature", $report->toString(2));
}

function testCliParsingAndDefaults(): void {
    runWithArgv([
        'php-autosemver',
        '--verbosity=2',
        '--project=/tmp/example-project',
        'v1.0.0',
    ], function (): void {
        $cli = new CLI();
        $cli->load();

        assertSameValue('CLI should parse the from revision.', 'v1.0.0', $cli->getFrom());
        assertSameValue('CLI should default the to revision to HEAD.', 'HEAD', $cli->getTo());
        assertSameValue('CLI should parse verbosity.', 2, $cli->getVerbosity());
        assertSameValue('CLI should parse project path.', '/tmp/example-project', $cli->getProjectPath());
    });

    runWithArgv([
        'php-autosemver',
        '--verbosity',
        '1',
        '--project',
        '/tmp/second-project',
        'v2.0.0',
        'HEAD~1',
    ], function (): void {
        $cli = new CLI();
        $cli->load();

        assertSameValue('CLI should parse the from revision when options use separate values.', 'v2.0.0', $cli->getFrom());
        assertSameValue('CLI should parse the to revision when provided.', 'HEAD~1', $cli->getTo());
        assertSameValue('CLI should parse verbosity when the value is separate.', 1, $cli->getVerbosity());
        assertSameValue('CLI should parse project path when the value is separate.', '/tmp/second-project', $cli->getProjectPath());
    });

    runWithArgv([
        'php-autosemver',
        '--from=v3.0.0',
        '--to=HEAD~2',
    ], function (): void {
        $cli = new CLI();
        $cli->load();

        assertSameValue('CLI should accept --from as a fallback when no positional revision is supplied.', 'v3.0.0', $cli->getFrom());
        assertSameValue('CLI should accept --to as a fallback when no positional revision is supplied.', 'HEAD~2', $cli->getTo());
    });
}

testLegacySignatureModelsRenderCurrentStrings();
testParameterSignatureModelsRenderCurrentStrings();
testTypeReferenceModelsRenderCurrentStrings();
testPrefixedSignatureCanSeparateIdentityFromLegacyFormatting();
testExplicitIdentityObjectsRenderStableKeys();
testIdentityEqualityUsesSemanticObjectComparison();
testSemanticDiffUsesIdentityEqualityNotOnlySerializedKeys();
testSignatureIndexPreservesAllDisplaysForOneIdentity();
testSignatureDiffSnapshotProducesEntries();
testSignatureDiffSnapshotCanBeBuiltFromSignatures();
testSignatureBucketsCanBeBuiltFromSignatures();
testDiffEntriesFlattenDisplays();
testDiffEntriesCanBeBuiltFromLegacyDisplays();
testDiffSectionsFlattenBucketDisplays();
testDiffReportCanBeBuiltFromBuckets();
testIncrementDeciderUsesEntryState();
testVersionIncrementBehavesLikeAValueObject();
testDiffReportStillExposesLegacyIncrementStrings();
testDiffReportRendererFormatsBucketEntries();
testRevisionRangeCarriesReportLabels();
testDiffReportNamedConstructorsPreserveBehaviour();
testDiffReportExposesSections();
testDiffReportRendererUsesReportAccessors();
testSignatureIdentityKeepsCurrentDiffBehaviour();
testExcludePathsAreHonoured();
testGroupedAndUngroupedTypeImportsRemainEquivalent();
testGroupedAndUngroupedConstImportsRemainEquivalent();
testTraitAliasFormattingEquivalenceIsPatch();
testNamespaceConstantOrderingDoesNotBumpVersion();
testImplementedContractOrderingDoesNotBumpVersion();
testExtendedInterfaceOrderingDoesNotBumpVersion();
testTraitPrecedenceOrderingDoesNotBumpVersion();
testTypeAliasRenamingDoesNotBumpVersion();
testConstantAliasRenamingDoesNotBumpVersion();
testNamespaceConstantAliasRenamingDoesNotBumpVersion();
testImplementedContractAliasRenamingDoesNotBumpVersion();
testExtendedParentAliasRenamingDoesNotBumpVersion();
testGroupedAndUngroupedContractImportsRemainEquivalent();
testTraitUseOrderingDoesNotBumpVersion();
testSplitAndGroupedNamespaceConstantDeclarationsRemainEquivalent();
testInterfaceParentAliasRenamingDoesNotBumpVersion();
testClassConstantFetchAliasRenamingDoesNotBumpVersion();
testGroupedAndUngroupedConstImportsRemainEquivalentInNamespaceConstants();
testGroupedAndUngroupedConstImportsRemainEquivalentInClassConstants();
testSplitAndGroupedPropertyDeclarationsRemainEquivalent();
testSplitAndGroupedClassConstantDeclarationsRemainEquivalent();
testClassConstantOrderingDoesNotBumpVersion();
testGitIgnoreInlineCommentsAreIgnored();
testRootAnchoredGitIgnorePatternsAreHonoured();
testIncludePathsRestrictTheSurface();
testWorkingCopyNewSignatureIsMinor();
testGitRevisionRemovalIsMajor();
testOptionalParameterAdditionIsMinor();
testExplicitDefaultConstructorIsPatch();
testOptionalParameterDefaultChangeIsMajor();
testPropertyVisibilityTighteningIsMajor();
testImportedAliasTargetChangeIsMajor();
testSignatureSearchCapturesCurrentModelShape();
testSignatureSearchIgnoresPrivateMembers();
testSignatureSearchFormatsDefaultValues();
testSignatureSearchCoversVariadicStaticMethods();
testSignatureSearchCoversTraitsAndInterfaces();
testSignatureSearchCoversNestedNamespaceFallbackTypes();
testSignatureSearchCoversFullyQualifiedAndAbstractShapes();
testSignatureSearchFormatsClassConstantUnaryValues();
testSignatureSearchFormatsPlainScalarClassConstants();
testSignatureSearchPrefersUseAliasesOverNamespaceFallback();
testSignatureSearchCoversGroupedPropertyAndConstantDeclarations();
testSignatureSearchCoversGlobalNamespaceShapes();
testSignatureSearchWrapsParseFailures();
testSignatureSearchIgnoresFunctionImports();
testSignatureSearchFailsOnUnsupportedTopLevelStatements();
testSignatureSearchIgnoresTraitUseStatementsInsideClasses();
testDiffReportFormatting();
testCliParsingAndDefaults();
testCliPreloadFailuresAndUnknownOptions();

testTraitUseSignatureModelsRenderCurrentStrings();
testTraitUseSignatureModelsCoverUnqualifiedAliasAndMultiplePrecedenceTargets();
testNamespaceConstantSignatureModelsRenderCurrentStrings();
testSignatureSearchFormatsRicherNamespaceConstantValues();
testNamespaceConstantAliasTargetChangesAffectDiffs();
testNamespaceConstantImportedConstantTargetChangesAffectDiffs();
testSignatureSearchResolvesImportedClassConstantFetchesInValues();
testClassConstantFetchAliasChangesAffectDiffs();
testSignatureSearchResolvesImportedConstantsInDefaultsAndValues();
testSignatureSearchFormatsMagicConstants();
testMagicConstantChangesAffectDiffs();
testSignatureSearchResolvesGroupedConstImportsInDefaults();
testImportedConstantChangesAffectDiffs();
testConstantIdentityAndSignatureEqualityUsesVisibility();
testContractSignatureModelsRenderCurrentStrings();
testDiffReportStateCarriesResolvedReportState();
testDiffReportStateFactoryResolvesIncrementValues();
testDiffReportRendererCanRenderReportState();
testSignatureSearchCapturesNamespaceConstants();
testNamespaceConstantChangesAffectDiffs();
testSignatureSearchResolvesNullableAndImportedTypes();
testSignatureSearchResolvesGroupedTypeImports();
testSignatureSearchIgnoresNonTypeGroupedImports();
testSignatureSearchCapturesTypedProperties();
testTypedPropertyChangesAffectDiffs();
testSignatureSearchPreservesProtectedAndStaticPropertyMarkers();
testSignatureSearchPreservesPhp72BuiltinAndContextualTypes();
testBuiltinAndParentTypeChangesAffectDiffs();
testSignatureSearchPreservesStaticClassConstantReferences();
testStaticClassConstantReferenceChangesAffectDiffs();
testSignatureSearchFormatsRicherDefaultExpressions();
testDefaultExpressionRenderingAffectsDiffs();
testSignatureSearchCapturesByReferenceCallables();
testByReferenceChangesAffectDiffs();
testSignatureSearchCapturesTraitUseAdaptations();
testSignatureSearchCapturesTraitAliasEdgeCases();
testTraitAliasVisibilityChangesAffectDiffs();
testTraitUseAdaptationChangesAffectDiffs();
testSignatureSearchCapturesInterfaceInheritanceContracts();
testSignatureSearchCapturesClassInheritanceContracts();
testInterfaceInheritanceChangesAffectDiffs();
testSignatureSearchCapturesConstantVisibility();
testConstantVisibilityChangesAffectDiffs();
testSignatureSearchFormatsAdditionalClassConstantValueShapes();
testSignatureSearchFormatsRicherClassConstantValues();
testSignatureSearchCapturesPrivateAndSelfConstantReferences();
testPrivateConstantVisibilityChangesAffectDiffs();
testClassConstantValueRenderingAffectsDiffs();

echo "All tests passed\n";
