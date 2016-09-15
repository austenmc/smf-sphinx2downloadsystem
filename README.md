# Simple Machines Forum Sphinx Download Manager --> Download System Converter

This was built for Talisman at http://dragonprime.net to help him convert his
Simple Machines Forum version 1.1.21, complete with unsupported mod called "Sphinx Download Manager"
originally built by the apparently defunct Dynamic Systems & Content Solution, to the
latest version of SMF without losing his download section, converting them to the [Download System](http://custom.simplemachines.org/mods/index.php?mod=992).

## Usage
1. First, edit `transfer.php` to set the `$server`, `$dbname`, `$username` and `$password` settings. If your 
SMF install uses a different DB prefix (the text at the beginning of each table name) then edit the old and new table names.

1. Next, simply run `php transfer.php`.

Of course, you should back up your database before you do run this script :)

