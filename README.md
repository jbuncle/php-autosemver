# PHP Automatic Semantic Versioning Detector

[![Latest Version](https://img.shields.io/github/v/tag/jbuncle/php-autosemver?sort=semver&label=github)](https://github.com/jbuncle/php-autosemver/)
[![Packagist Version](https://img.shields.io/packagist/v/jbuncle/php-autosemver)](https://packagist.org/packages/jbuncle/php-autosemver)
[![Docker Image Version (latest semver)](https://img.shields.io/docker/v/jbuncle/php-autosemver?label=docker&logo=docker%20version&sort=semver)](https://hub.docker.com/r/jbuncle/php-autosemver)

[![Build Status](https://img.shields.io/docker/cloud/build/jbuncle/php-autosemver)](https://hub.docker.com/r/jbuncle/php-autosemver)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/jbuncle/php-autosemver)
[![LICENCE](https://img.shields.io/github/license/jbuncle/php-autosemver)](https://github.com/jbuncle/php-autosemver/blob/master/LICENSE)

*Still in development, though largely stable*

**Manage your PHP library versions automatically**

Automatically calculates the Semantic Version (SemVer) increment automatically based on source code changes between different Git references (revisions, tags, etc). This increment can then be used to determine the next version number (e.g. using the built in `composer-version` command).

Given two Git revisions (or a Working Copy), it will return **MAJOR**, **MINOR** or **PATCH** based on the changes between those revisions, based on Semantic Versioning rules (see <https://semver.org/>).

This allows the versioning process to be fully automated, where you would otherwise require a manual step to set the version would be required.

There are some edge cases, however automation is probably still better than manually maintaining semantic versions since such processes take time and are prone to human error.

## Installation with Composer

Install globally:

```bash
composer global require jbuncle/php-autosemver
```

## Basic Usage

*With composer*

```bash
cd <your project>
php-autosemver <revision-from> <revision-to>
```

*With docker*

```bash
cd <your project>
docker run -v $(pwd):/app -it --rm jbuncle/php-autosemver <revision-from> <revision-to>
```

A "revision" can be a commit, tag, branch, `HEAD`, or `WC` (use working copy).

## Examples

### Compare your working copy to Git

Run from your the root of your project.

*With composer global*

```bash
php-autosemver --verbosity=1 HEAD WC
```

*With Docker*

```bash
docker run -v $(pwd):/app -it --rm jbuncle/php-autosemver bash -c "php-autosemver --verbosity=1 HEAD WC"
```

Useful for checking whether your commit introduces an API breaking change and print out the relevant differences.

### Automatically tag current Git revision

Create a Git tag by comparing current revision to last tag.

*With composer*

```bash
# Ensure we have fetched existing tags
git fetch --tags

# Calculate the version number using php-autosemver
LATEST_TAG=$(latesttag);
INCREMENT=$(php-autosemver ${LAST_VERSION});
NEW_VERSION=$(composer-version --inc ${LATEST_TAG} ${INCREMENT});

# Create the tag and push it
git tag ${NEW_VERSION}
git push origin ${NEW_VERSION}
```

*With Docker*

```bash
# Ensure we have fetched existing tags
git fetch --tags

# Calculate the version number using php-autosemver docker image
NEW_VERSION=$(docker run -it --rm -v $(pwd):/app jbuncle/php-autosemver bash -c '\
LATEST_TAG=$(latesttag);\
INCREMENT=$(php-autosemver ${LATEST_TAG});\
composer-version --inc ${LATEST_TAG} ${INCREMENT}')

# Create the tag and push it
git tag ${NEW_VERSION}
git push origin ${NEW_VERSION}
```

## How it works

The tool parses all the PHP files and generates a list of all the possible, accessible signatures (including variations) found. For Git revisions it will traverse the Git commit directly using the Git CLI (therefore the `git` command is required).

Once generated for both sets of changes, it will compare the generated signature strings lists looking for removed signatures (**MAJOR** change), new signatures (**MINOR** change) or no signature changes (**PATCH**).

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

## Known Edge Cases

* Changing method signature to be a [variadic](https://www.php.net/manual/en/functions.arguments.php#functions.variable-arg-list) will show as breaking change, even if the change is backward compatible.
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
* Subversion (SVN) support
