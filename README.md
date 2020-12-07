CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Maintainers


INTRODUCTION
------------

Webform extended functionality for Drupal 8/9.

REQUIREMENTS
------------

Drupal core `8.8` or up.

INSTALLATION
------------

 * Install the Webform Purge module as you would normally install a contributed
   Drupal module. Visit [Installing Drupal 8 Modules](https://www.drupal.org/node/1897420) for further
   information.

USAGE
-----

### 7.x-1.x

See [Webform Purge](https://www.drupal.org/project/webform_purge) for the Drupal 7 variant of this module.

### 2.x

The `2.x` release of this module exposes an extra drush command in addition to the purge functionality provided by the Webform module. It allows a one-off purge command that also respects the remainder of x days similar with the cron runs.

```
$ drush webform-purge:purge <webform_id> --purge-type=all --purge-days=30
```


MAINTAINERS
-----------

 * Sang Lostrie (baikho) - https://www.drupal.org/u/baikho

### Supporting organizations:

 * [Access](https://www.drupal.org/access) - Development and maintenance
