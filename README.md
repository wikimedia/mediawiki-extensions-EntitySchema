# Entity Schema Extension

## Development

### Installing the extension
Clone the code into your `extensions/` directory and add the following lines to your `LocalSettings.php`:
```php
$wgEntitySchemaShExSimpleUrl = 'https://shex-simple.toolforge.org/wikidata/packages/shex-webapp/doc/shex-simple.html?data=Endpoint:%20https://query.wikidata.org/sparql&hideData&manifest=[]&textMapIsSparqlQuery';
wfLoadExtension( 'EntitySchema' );
```
`$wgEntitySchemaShExSimpleUrl` is intended to contain a link to a tool that allows checking the current schema against Items.
See the [description of the option in extension.json](https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/EntitySchema/+/ea8b17df26e6ab3499f953faf8e0fa3b5197de81/extension.json#81) for more details.
That value is the same as what is in the Wikidata production configuration at time of writing and is also suitable for development.

Then, in the root mediawiki directory, update Medawiki composer and run the update maintenance script:
```bash
mw docker mediawiki composer update
mw docker mediawiki exec php maintenance/run.php update
```

### Setting up the extension's development environment

Install the npm dependencies, recommended way is with [fresh](https://github.com/wikimedia/fresh) being run in the `EntitySchema/` directory:
```bash
$ fresh-node
I have no name!@c91178c3ea7d:/EntitySchema$ npm install
```

Install the composer dependencies, recommended way is with [mwcli](https://www.mediawiki.org/wiki/Cli) being run in the `EntitySchema/` directory:

```bash
$ mw docker mediawiki composer install
```

### Running PHP linting and static analysis

PHPCS and other PHP linting
```bash
$ mw docker mediawiki composer test
```

If there are fixable errors, then they can be fixed with:

```bash
mw docker mediawiki composer fix
```

The static analyzer `phan` can run from the root mediawiki directory with:

```bash
mw docker mediawiki composer phan
```
It might take a few minutes to finish as it runs a global analysis.
If there is an issue in non-EntitySchema code, then that might be resolved by
first: making sure all your core/extensions/skin repositories are up-to-date (run `git pull`), and
second: updating composer in mediawiki core (see above).


### Running PHPUnit tests

From the root mediawiki directory run the following:

```bash
$ mw docker mediawiki composer phpunit extensions/EntitySchema/tests/phpunit/
```

All tests should pass on the `master` branch.

However, note that Wikimedia CI runs a subset of the PHPUnit tests, the tests in the _unit_ subdirectory,
with a slightly different command that asserts some more restrictions:

```bash
$ mw docker mediawiki exec php vendor/bin/phpunit extensions/EntitySchema/tests/phpunit/unit/
```

Though this will not work for integration test.
Use that command to reproduce CI failures when working with _unit_ tests specifically.

### Running JS linting

In the `EntitySchema/` directory:
```bash
$ fresh-node
I have no name!@c91178c3ea7d:/EntitySchema$ npm test
```

### Running Browser tests

TODO

### Chore: Updating dependencies

Many of the dependencies of EntitySchema are kept up-to-date by [LibraryUpgrader](https://www.mediawiki.org/wiki/Libraryupgrader).
However, sometimes LibraryUpgrader runs into trouble and therefore we want to make sure to check the state of our dependencies regularly.

In general, we want to keep both the Node.js npm dependencies, as well as the PHP composer dependencies up-to-date.
For the npm dependencies, first make sure your local dependencies are up-to-date by executing `npm ci`.
Then run `npm outdated` to see if any need an update.

Similarly for composer: make sure your local dependencies match what is in composer.json by running `composer update`.
Then check for outdated dependencies by running `composer outdated --direct`.

All dependencies should generally be updated to the latest version.
If you discover that a dependency should not be updated for some reason, please amend this section with the dependency and the reason.
If a dependency can only be updated with substantial manual work, you can create a new task for it and skip it in the context of the current chore.

## Troubleshooting

### I get `tempnam(): file created in the system's temporary directory` when running the phpunit tests?

Add `$wgTmpDirectory = '/tmp';` to your LocalSettings.php.

### I get `Table 'default.unittest_entityschema_id_counter' doesn't exist` when running the phpunit tests?

Update your MediaWiki's composer dependencies as described above.
