<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';

use AutomaticSemver\CLI;
use AutomaticSemver\DiffReport;
use AutomaticSemver\FileSearch\SystemFile;
use AutomaticSemver\SemVerDiff;
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

testExcludePathsAreHonoured();
testGitIgnoreInlineCommentsAreIgnored();
testIncludePathsRestrictTheSurface();
testWorkingCopyNewSignatureIsMinor();
testGitRevisionRemovalIsMajor();
testOptionalParameterAdditionIsMinor();
testExplicitDefaultConstructorIsPatch();
testSignatureSearchCapturesCurrentModelShape();
testSignatureSearchIgnoresPrivateMembers();
testSignatureSearchFormatsDefaultValues();
testSignatureSearchCoversVariadicStaticMethods();
testSignatureSearchCoversTraitsAndInterfaces();
testDiffReportFormatting();
testCliParsingAndDefaults();

echo "All tests passed\n";
