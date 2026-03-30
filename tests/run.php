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
use AutomaticSemver\Signature\ConstantSignature;
use AutomaticSemver\Signature\DefaultValue;
use AutomaticSemver\Signature\ParameterIdentity;
use AutomaticSemver\Signature\ContainerIdentity;
use AutomaticSemver\Signature\IdentityKey;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\NamespaceIdentity;
use AutomaticSemver\Signature\ParameterSignature;
use AutomaticSemver\Signature\PropertyIdentity;
use AutomaticSemver\Signature\PrefixedSignature;
use AutomaticSemver\Signature\PropertySignature;
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


function testLegacySignatureModelsRenderCurrentStrings(): void {
    $callable = new CallableSignature('->', 'demo', [
        new ParameterSignature(new TypeReference('string')),
        new ParameterSignature(new TypeReference('int'), false, new DefaultValue('0')),
    ], new TypeReference('?\Vendor\Thing'), ['protected', 'final'], true);
    assertSameValue('Callable signature models should render the current legacy format.', '->{protected final demo(string, int = 0):?\Vendor\Thing}', $callable->toLegacyString());
    assertSameValue('Callable signature models should support string casting.', '->{protected final demo(string, int = 0):?\Vendor\Thing}', (string) $callable);
    assertContainsText('Callable signature identity should carry structural information.', 'callable|dispatch:->|name:demo', $callable->toIdentityKey());

    $property = new PropertySignature('counter', 'protected', true);
    assertSameValue('Property signature models should render the current legacy format.', 'protected static $counter', $property->toLegacyString());
    assertSameValue('Property signature identity should be structural.', 'property|name:counter|visibility:protected|static:1', $property->toIdentityKey());

    $constant = new ConstantSignature('STATUS', "'ok'");
    assertSameValue('Constant signature models should render the current legacy format.', "::STATUS = 'ok'", (string) $constant);
    assertSameValue('Constant signature identity should be structural.', "constant|name:STATUS|value:'ok'", $constant->toIdentityKey());
}


function testParameterSignatureModelsRenderCurrentStrings(): void {
    $variadic = new ParameterSignature(new TypeReference('string'), true);
    assertSameValue('Variadic parameter signature models should render the current legacy format.', '...string', $variadic->toLegacyString());

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

function testExplicitIdentityObjectsRenderStableKeys(): void {
    $namespace = new NamespaceIdentity('\\Demo\\');
    assertSameValue('Namespace identity objects should render the current key format.', 'namespace:\\Demo\\', $namespace->toIdentityKey());

    $container = new ContainerIdentity('class', 'Foo', true, false);
    assertSameValue('Container identity objects should render the current key format.', 'container|kind:class|name:Foo|abstract:1|final:0', $container->toIdentityKey());

    $parameter = new ParameterIdentity(new TypeReference('string'), true, new DefaultValue('0'));
    assertSameValue('Parameter identity objects should render the current key format.', 'param|variadic:1|type:string|default:0', $parameter->toIdentityKey());

    $callable = new CallableIdentity('->', 'demo', [$parameter], new TypeReference('?\\Vendor\\Thing'), ['protected'], true);
    assertContainsText('Callable identity objects should render the current key format.', 'callable|dispatch:->|name:demo|wrap:1|modifiers:protected|params:[param|variadic:1|type:string|default:0]|type:?\\Vendor\\Thing', $callable->toIdentityKey());

    $property = new PropertyIdentity('counter', 'protected', true);
    assertSameValue('Property identity objects should render the current key format.', 'property|name:counter|visibility:protected|static:1', $property->toIdentityKey());

    $constant = new ConstantIdentity('STATUS', "'ok'");
    assertSameValue('Constant identity objects should render the current key format.', "constant|name:STATUS|value:'ok'", $constant->toIdentityKey());
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
        '\Demo\build(?\Vendor\Package\Thing):?\Vendor\Package\Thing',
    ], $signatures);
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
        '\Demo\build(array = [], mixed = SOME_CONST, int = -1):void',
        '\Demo\build(array, mixed, int):void',
        '\Demo\build(array = [], mixed = SOME_CONST):void',
        '\Demo\build(array, mixed):void',
        '\Demo\build(array = []):void',
        '\Demo\build(array):void',
    ], $signatures);
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
    assertSameList('Trait use statements inside classes should remain ignored instead of inlining trait members.', [
        '\Demo\{Trait Helper}->assist()',
        '\Demo\Worker->__construct()',
        '\Demo\Worker->run()',
    ], $signatures);
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

echo "All tests passed\n";
