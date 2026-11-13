# Working rules for this repo (Escape Room Booking, the free "Lite" plugin)

This repository is PUBLIC and the plugin is published on WordPress.org
(https://wordpress.org/plugins/ettrick-escape-room-booking/). The paid plugin is a separate private repo
(escape-room-booking-pro). GitHub is the single source of truth. The user (John Craig, very experienced developer,
not a Bash/SSH person) must not copy or edit code by hand, on his PC or on a server.

1. Make every change on a branch and open a pull request. Never push to main. John presses Merge.
2. NO secrets, ever: no keys, tokens, passwords, licence secrets or Stripe keys (those are customer settings stored
   in the database, never in code). Everything committed here is world-readable. The "Check and build" workflow fails
   if it finds anything that looks like a key. Lite has no licence check and must not gain one.
3. GitHub Actions run on every push: PHPUnit on PHP 7.4 and 8.0-8.3 (tests.yml), then a syntax check on current PHP
   and PHP 7.4 and a test-zip build (build.yml). The plugin supports PHP 7.4, so do not use PHP 8-only syntax or
   functions (match, ?->, str_contains, str_starts_with, str_ends_with, named arguments, union types...). Fix
   failures; never weaken a check.
4. The test zip is built by tools/build.sh. It contains only: admin, includes, languages, public,
   escape-room-booking.php, readme.txt, uninstall.php, under a folder called ettrick-escape-room-booking/. If a new
   top-level file or folder becomes part of the plugin, add it to the copy line in tools/build.sh.
5. The three version numbers (plugin header, EERB_VERSION, readme.txt "Stable tag") must match; the build fails if
   not. Bump them on every release.
6. Publishing to WordPress.org is done by John with SVN from his laptop (svn-ettrick/ is ignored by git). Do not
   change that process without agreeing it with him first; a pull request never publishes anything.
7. How John tests: he downloads the zip from the Actions run (GitHub wraps it in an outer zip, so he unzips once to
   get the plugin zip), installs it on a TEST site with Plugins > Add New > Upload Plugin > "Replace current with
   uploaded", checks it, then merges the pull request.
8. Playwright end-to-end tests (tests/e2e) need a real WordPress site and are run by hand, not in GitHub.
9. Give John ONE small step at a time, naming exact files and folders. Never claim something is tested unless you
   ran it.
10. A stop-hook may claim "unpushed commits" in a shallow clone; verify with `git ls-remote origin <branch>`.
