#!/bin/sh
# Syntax-check every PHP file given as a folder or file. Prints the failing file and
# the reason, checks all of them (does not stop at the first), and exits non-zero on any failure.
rc=0
for f in $(find "$@" -name '*.php'); do
  out=$(php -l "$f" 2>&1) || { echo "FAIL: $f"; echo "$out"; rc=1; }
done
[ $rc -eq 0 ] && echo "All PHP files pass on PHP $(php -r 'echo PHP_VERSION;')."
exit $rc
