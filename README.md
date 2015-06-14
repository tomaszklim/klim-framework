# klim-framework
This isn't next PHP web framework. It should rather be called "data and integration framework", as it is written as a base for writing data processing and systems integration tasks.

Most of this code was written in 2007-2011, however it is still actively maintained to make it work without problems on current Linux/Apache/PHP versions.

It was first written and tested on Debian Etch, then respectively on Lenny, Squeeze and Wheezy. Most code comments are in polish language. Is was meant to release as open source at least since 2008, but it happened in 2015.

This code is used by several commercial products, that either are or will be offered by Fajne.IT.


## Application structure:

```
/app - root directory (can be changed)
/app/cache - cache directories
/app/cache/smtp_failures
/app/cache/api_cookies
/app/cache/api_soap
/app/libs - here to clone this repository
/app/klimbs/branch1 - example application
/app/klimbs/branch1/include - local PHP include directory
/app/klimbs/branch1/include/config - application configuration hierarchy
/app/klimbs/branch1/java - local Java root directory
/app/klimbs/branch1/java/lib - JAR files
/app/klimbs/branch1/java/src - Java source code and compiled classes
```

Allowed application root directories are included in `Bootstrap::$paths` static array, in bootstrap.php file. This should be the only thing to change, if you want to fork this repository and use it for your purposes.

Code in bootstrap.php file has been designed to allow setting up multiple applications on single server. All of these applications share `/app/cache` and `/app/libs` directories, but have independent `include` and `java` subdirectories, depending on application root paths recognized by bootstrap.php.

`/etc/environment-type` file is required to be present on all servers using this code, and to be read-only for developers. Acceptable values as either "dev", "test" or "prod", meaning the server type.

Message logging is done to `/var/log/php/*.log` multiple log files, one file per logging facility, eg. `core.log`, `db.log` (`/var/log/php` directory must be writable for developers).


## Authors

Some of this code has been originally taken at least from:

- NNTP client code written by Terence Yim
- MediaWiki code written by Tim Starling and others
- character encoding proxy code written by Ivo Jansch
- Java-related code written by Google employees

Also, database-related code was strongly inspired by MediaWiki database driver code and its usage across MediaWiki application.

Code outside klim-* directories and bootstrap.php file was written by several other authors, especially by Manuel Lemos.


## Relation with Allegro Group

Many technical concepts used in this code were consciously or unconsciously inspired by knowledge of "Qeppo" platform used at Allegro Group, for which I worked almost 7 years.

ALL SUCH CODE HAS BEEN WRITTEN FROM SCRATCH, WITHOUT USING "Qeppo" CODE.

However, some of my code for Allegro Group were intentionally written to test some of my ideas in huge-traffic application on huge audience (over 20 millions of users, multiple Gbps traffic) and then reimplement them as open source in my spare time, avoiding problems spotted in the tests of first version. Still, all such code has been written again from scratch, without using "Qeppo" code.


## Commercial support

You can buy commercial support at http://fajne.it
