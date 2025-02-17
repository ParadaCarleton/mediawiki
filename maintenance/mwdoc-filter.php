<?php
/**
 * Filter for PHP source code that allows Doxygen to understand it better.
 *
 * This CLI script is intended to be configured as the INPUT_FILTER
 * script in a Doxyfile, e.g. like "php mwdoc-filter.php".
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

use MediaWiki\Maintenance\MWDoxygenFilter;

// Warning: Converting this to a Maintenance script may reduce performance.
if ( PHP_SAPI != 'cli' && PHP_SAPI != 'phpdbg' ) {
	die( "This filter can only be run from the command line.\n" );
}

require_once __DIR__ . '/includes/MWDoxygenFilter.php';

$source = file_get_contents( $argv[1] );
echo MWDoxygenFilter::filter( $source );
