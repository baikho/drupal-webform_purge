CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Maintainers


INTRODUCTION
------------

Webform Purge extended functionality for Drupal 8/9.

REQUIREMENTS
------------

Drupal core `8.8` or up.

INSTALLATION
------------

 * Install the Webform Purge module as you would normally install a contributed
   Drupal module. Visit [Installing Drupal 8 Modules](https://www.drupal.org/node/1897420) for further
   information.
 * Alternatively you can include the module as a repository in your `composer.json` file:
 
```
"repositories": [
   {
       "type":"package",
       "package": {
           "name": "drupal/webform_purge",
           "version": "2.0.0-beta1",
           "type": "drupal-module",
           "source": {
               "type": "git",
               "url": "https://github.com/baikho/drupal-webform_purge.git",
               "reference": "8af591aa1f099546b5995812280846e67eb80de0"
           }
       }
   }
]
```

USAGE
-----

### 7.x-1.x

See [Webform Purge](https://www.drupal.org/project/webform_purge) for the Drupal 7 variant of this module.

### 2.x

The `2.x` release of this module exposes an extra drush command in addition to the purge functionality provided by the [Webform](https://www.drupal.org/project/webform) module. It exposes a purge command that respects the retain of x days similar as with the cron runs.

```
$ drush webform-purge:purge <webform_id> --purge-type=<all|draft|completed> --purge-days=<30>
```


MAINTAINERS
-----------

 * Sang Lostrie (baikho) - https://www.drupal.org/u/baikho

### Supporting organizations:

 * [Access](https://www.drupal.org/access) - Development and maintenance
