<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';

use AutomaticSemver\CLI;
use AutomaticSemver\DiffReport;
use AutomaticSemver\FileSearch\SystemFile;
use AutomaticSemver\SemVerDiff;
use AutomaticSemver\Signature\CallableSignature;
use AutomaticSemver\Signature\ConstantSignature;
use AutomaticSemver\Signature\DefaultValue;
use AutomaticSemver\Signature\ParameterSignature;
use AutomaticSemver\Signature\PropertySignature;
use AutomaticSemver\Signature\TypeReference;
use AutomaticSemver\SignatureSearch;

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

    $property = new PropertySignature('counter', 'protected static ');
    assertSameValue('Property signature models should render the current legacy format.', 'protected static $counter', $property->toLegacyString());

    $constant = new ConstantSignature('STATUS', "'ok'");
    assertSameValue('Constant signature models should render the current legacy format.', "::STATUS = 'ok'", (string) $constant);
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
    $report = new DiffReport('from-tag', 'to-tag', ['sameSignature'], ['newSignature'], ['removedSignature']);

    assertSameValue('Removed signatures should produce a MAJOR increment.', 'MAJOR', $report->getIncrement());
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
