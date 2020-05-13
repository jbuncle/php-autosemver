# PHP Automatic Semantic Versioning Detector

Automatically determine next version using semver (Semantic Versioning) rules - because CI should be automated and semver is great for actually managing dependencies.

I created this tool because I wanted to fully automate my build process,
without having to trust myself to firstly remember to tag changes, and secondly to actually
tag them correctly.

*Still in development*

Compare two file paths or Git revisions to see whether the changes are considered
MAJOR, MINOR or PATCH based on semantic versioning rules.

Intended to be used in Continuous Integration (CI) Systems to help automate the versioning process.

Performs a basic/rough comparison, so, although it will largely work for most changes,
there are edge cases that won't be picked up (e.g. changes inherited from parent classes outside the search path).

Arguably, even with edge cases, this is better than manually maintaining semantic versions
since such processes take time and are prone to human error.

## Installation & Usage

### Installation
Install globally: 
```bash 
composer global require jbuncle/php-autosemver
```

Install for you project:
```bash 
composer require --dev jbuncle/php-autosemver
```
### Basic Usage

Usage, from the root of your git project:
```bash
vendor/bin/php-autosemver 1.0.0 HEAD
```
Replacing '1.0.0' and 'HEAD' with the 'wc' (working copy), the tag, branch or revision you want to compare.

### Compare top revision to last tag

Crazy long one-liner to get next version based on the last Git tag.

```bash
git fetch --tags; CURRENT_VERSION=$(git describe --tags `git rev-list --tags --max-count=1`); INC=$(vendor/bin/php-autosemver $CURRENT_VERSION); vendor/bin/composer-version --inc $CURRENT_VERSION $INC
```

### Compare top revision to working copy with Docker

```bash
docker run -v $(pwd):/app -it docker.jbuncle.co.uk/jbuncle/php-autosemver bash -c "cd /app; php-autosemver \$(latesttag) WC --verbose"
```

## Known Edge Cases
* Changing method signature to use variadics will show as breaking change, even if the change is backward compatible.
* Inherited changes as a result of updates to parent classes/traits that exist outside search directory won't be detected
* Adding a constructor with a signature that matches the parent will show as a breaking changes
* Adding a return type that was previously not type hinted will show as a breaking change, even if the type matches what was previously returned
(technically this is a breaking changes as method might be overridden and then not match). 
* Removing a type parameter will show as breaking change
* Doesn't recognise an addition of a method signature to an interface as a breaking change.

## Improvements
 * Make more aware of composer
   * Inspect composer dependencies (if a dependency has incremented, then this project should match the increment)
   * Inspect autoload paths (don't worry about classes that can't/shouldn't be accessed)
 * Only bother parsing files that have changed
 * Analyse parent classes for additional, inherited signatures
 * Treat addition of a signature to an interface as a breaking change
 * Ignore change of default values on parameters (these aren't breaking changes)
 * Don't treat making abstract class non-abstract as a breaking change

## How it works

The tool parses all the PHP files and generates a list of all the possible, accessible signatures (including variations)
found. Once generated for both sets of changes, it will compare the generated signature strings lists looking for 
removed signatures (MAJOR change), new signatures (MINOR change) or no signature changes (PATH).

For example, the following in PHP code:

```php
 namespace MyNamespace;
 class SomeClass {
    public function aMethod($a, $b = 0) {}
 }
```

Would be interpreted into 3 unique signature variations:

```
\MyNamespace\SomeClass->aMethod(mixed, mixed = 0)
\MyNamespace\SomeClass->aMethod(mixed, mixed)
\MyNamespace\SomeClass->aMethod(mixed)
```
