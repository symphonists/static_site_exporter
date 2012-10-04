# Static Site Exporter

- Version: 0.4
- Author: Symphony Team, Nick Dunn
- Build Date: 2010-12-21
- Requirements: Symphony 2.3

## Description

Export a Symphony site to flat HTML pages.

## Installation

1. Place the `static_site_exporter` folder in your Symphony `extensions` directory.
2. Go to _System > Extensions_, select "Static Site Exporter", choose "Enable" from the with-selected menu, then click Apply.

## Usage

View the extension by following _System > Static Site Exporter_. Once the extension has crawled your site and built an index of pages and assets, you can generate an archive (Zip) as a "static build".

First click the "Index Site" button which will initialise the crawler. This follows all hyperlinks within your site and indexes the contents of each page.

When complete, click the "Generate Static Build" button which compiles this index into a Zip file in your `manifest/tmp` directory.

## Preferences

There are several preferences edited via the _System > Preferences_ page:

* `Index File Name` is the name of the HTML file created for each Symphony page (defaults to `index.html`)
* `Export Location` is the full disk path of where to save exported Zip archives (defaults to `manifest/tmp`)
* `Force Include` lets you specify a list of additional files and/or folders to be included in the Zip
* `Include 404 pages in export archive` does what it says on the tin

Additionally you can set up global string replacements in the `lib/inc.string_replace_pairs.php` file. This is an array of pairs in the form:

    $pairs['foo'] = 'bar';

Where all instances of the string `foo` will be replaced with `bar` when the export Zip is created. This is useful for replacing URLs or paths which might need to be absolute or hard-coded in your pages.