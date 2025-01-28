#!/bin/bash
# Used in .github/workflows/dailyCI.yml

set -x

cd ../mediawiki

echo 'require_once "$IP/includes/DevelopmentSettings.php";' >> LocalSettings.php
echo 'error_reporting(E_ALL| E_STRICT);' >> LocalSettings.php
echo 'ini_set("display_errors", 1);' >> LocalSettings.php
echo '$wgUsePathInfo = false;' >> LocalSettings.php
# For re-using the Wikimedia CI settings, pretend we're running in quibble
echo 'if ( !defined( "MW_QUIBBLE_CI" ) ) define( "MW_QUIBBLE_CI", true );' >> LocalSettings.php
echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php
echo '$wgLanguageCode = "'$LANG'";' >> LocalSettings.php
echo '$wgDebugLogFile = "'$LOG_DIR'/mw-debug.log";' >> LocalSettings.php

echo '$wgEnableWikibaseRepo = true;' >> LocalSettings.php
echo '$wgEnableWikibaseClient = true;' >> LocalSettings.php
echo '$wgWBClientSettings["siteGlobalID"] = "enwiki";' >> LocalSettings.php

# enable Extensions and Skins
echo 'wfLoadSkin( "Vector" );' >> LocalSettings.php
echo 'wfLoadExtension( "EntitySchema" );' >> LocalSettings.php
echo 'wfLoadExtension( "cldr" );' >> LocalSettings.php
echo 'wfLoadExtension( "SyntaxHighlight_GeSHi" );' >> LocalSettings.php
echo 'wfLoadExtension( "Scribunto" );' >> LocalSettings.php
echo 'wfLoadExtension( "Elastica" );' >> LocalSettings.php
echo 'wfLoadExtension( "EventBus" );' >> LocalSettings.php
echo 'wfLoadExtension( "CirrusSearch" );' >> LocalSettings.php
echo 'wfLoadExtension( "WikibaseCirrusSearch" );' >> LocalSettings.php
echo 'require_once __DIR__ . "/extensions/Wikibase/Wikibase.php";' >> LocalSettings.php
