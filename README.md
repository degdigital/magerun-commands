Magerun Custom Commands
================

Some additional commands for the excellent N98-MageRun Magento command-line tool.

The purpose of this project is just to have an easy way to deploy new, custom
commands that I need to use for Magento development and automated testing.

Installation
------------
There are a few options.

Here's the easiest:

1. Create ~/.n98-magerun/modules/ if it doesn't already exist.

        mkdir -p ~/.n98-magerun/modules/

2. Clone the magerun-commands repository in there

        cd ~/.n98-magerun/modules/
        git clone git@github.com:degdigital/magerun-commands.git

Commands
--------

### Export Dataset ###

This command will export a specific list of table(s).

    $ n98-magerun db:export --tables="catalog_product_entity"

It's intended to be used to export a partial dataset(catalog, customers, orders) for automated testing.
