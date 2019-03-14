= PHP Automatic Semantic Versioning Detector =

Compare two file paths or Git revisions to see whether the changes are considered
MAJOR, MINOR or PATCH based on semantic versioning rules.

Intended to be used in CI.

This does a basic/rough comparison, so, although it will largely work for most changes,
there are edge cases that won't be picked up (e.g. changes inherited from parent classes outside the search path).

Arguably, even with edge cases, this is better than manually maintaining semantic versions
since such processes take time and are prone to human error.

== Improvements ==

* Inspect composer dependencies (if a dependency has incremented, then this project should match the increment)
* 
