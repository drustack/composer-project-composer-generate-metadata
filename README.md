Composer Preserve Paths
=======================

[![Build Status](https://travis-ci.org/drustack/composer-generate-metadata.svg?branch=master)](https://travis-ci.org/drustack/composer-generate-metadata)
[![Coverage Status](https://coveralls.io/repos/drustack/composer-generate-metadata/badge.svg?branch=master&service=github)](https://coveralls.io/github/drustack/composer-generate-metadata?branch=master)
[![Latest Stable Version](https://poser.pugx.org/drustack/composer-generate-metadata/v/stable.svg)](https://packagist.org/packages/drustack/composer-generate-metadata)
[![Total Downloads](https://poser.pugx.org/drustack/composer-generate-metadata/downloads.svg)](https://packagist.org/packages/drustack/composer-generate-metadata)
[![License](https://poser.pugx.org/drustack/composer-generate-metadata/license.svg)](https://packagist.org/packages/drustack/composer-generate-metadata)

Composer plugin for generate Drupal packages metadata into info files.

By default packages (e.g. modules, themes and profiles) downloaded from <https://drupal.org/> will be injeted with metadata info its .info or .info.yml, so update.module will able to figure out if corresponding version installed are outdated or not. By the way, if you download packages with GIT directly, e.g. install `-dev` release by using Composer, such metadata info won't exists and so update.module will report with unknown version.

This way you can:

-   Generate version information for `.info` files in ini format
-   Generate version information for `.info.yml` files in YAML format
-   Compute the rebulid version string for a project, by does some magic in Git to find the latest release tag along the branch we're packaging from, count the number of commits since then, and use that to construct this fancy alternate version string which is useful for the version-specific dependency support in Drupal 7 and higher

In case of Drupal 7.x, following metadata will be injected into `.info` file:

    ; Information add by drustack/composer-generate-metadata on 2017-02-18
    core = "7.x"
    project = "features"
    version = "7.x-2.10+3-dev"
    datestamp = "1487399547"

In case of Drupal 8.x, following metadata will be injected into `.info.yml` file:

    # Information add by drustack/composer-generate-metadata on 2017-02-18
    core: "8.x"
    project: "features"
    version: "8.x-3.2+1-dev"
    datestamp: "1487399552"

Installation
------------

Simply install the plugin with composer: `composer require drustack/composer-generate-metadata`

Configuration
-------------

Drupal projects are not listed on Packagist. Instead, Drupal.org provides its own directory of Drupal projects for Composer to use. Therefore you will need to add Drupal.org as a Composer Repository to your Drupal site's composer.json file.

Drupal.org provides two separate composer repository endpoints: one for Drupal 7 and one for Drupal 8.

-   To use Composer with Drupal 7, use the repository url <https://packages.drupal.org/7>
-   To use Composer with Drupal 8, use the repository url <https://packages.drupal.org/8>

To add the repository from the command line you should execute the following command from your repository root:

    $ composer config repositories.drupal composer <https://packages.drupal.org/8>

Composer will then automatically update your Drupal site's composer.json file with a repositories object of the format:

    {
        "repositories": [
            {
                "type": "composer",
                "url": "https://packages.drupal.org/7"
            }
        ]
    }

Example
-------

An example composer.json:

    {
        "repositories": [
            {
                "type": "composer",
                "url": "https://packages.drupal.org/7"
            }
        ],
        "require": {
            "drupal/drupal": "~7.54",
            "drupal/features": "2.x-dev",
            "drustack/composer-generate-metadata": "~1.0"
        }
    }

License
-------

-   Code released under [GPL-2.0+](https://github.com/drustack/composer-generate-metadata/blob/master/LICENSE)
-   Docs released under [CC BY 4.0](http://creativecommons.org/licenses/by/4.0/)

Author Information
------------------

-   Wong Hoi Sing Edison
    -   <https://twitter.com/hswong3i>
    -   <https://github.com/hswong3i>

