#! /bin/bash

set -x

originalDirectory=$(pwd)

cd ..

git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/core.git mediawiki --depth 1

cd mediawiki

cd skins
git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/skins/Vector --depth 1

cd ../extensions

git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/extensions/Scribunto.git --depth 1
git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/extensions/cldr --depth 1
git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/extensions/SyntaxHighlight_GeSHi --depth 1
git clone -b $MW_BRANCH https://gerrit.wikimedia.org/r/mediawiki/extensions/Wikibase --depth 1
cd Wikibase
# work around GitHub Actions being blocked from Phabricator (T372626)
git apply << 'EOF'
diff --git a/.gitmodules b/.gitmodules
index df41c768af..e9926d6ddd 100644
--- a/.gitmodules
+++ b/.gitmodules
@@ -3,13 +3,13 @@
 	url = https://gerrit.wikimedia.org/r/data-values/value-view
 [submodule "view/lib/wikibase-serialization"]
 	path = view/lib/wikibase-serialization
-	url = https://phabricator.wikimedia.org/source/wikibase-serialization.git
+	url = https://github.com/wmde/WikibaseSerializationJavaScript.git
 [submodule "view/lib/wikibase-data-values"]
 	path = view/lib/wikibase-data-values
-	url = https://phabricator.wikimedia.org/source/datavalues-javascript.git
+	url = https://github.com/wmde/DataValuesJavaScript.git
 [submodule "view/lib/wikibase-data-model"]
 	path = view/lib/wikibase-data-model
-	url = https://phabricator.wikimedia.org/source/wikibase-data-model.git
+	url = https://github.com/wmde/WikibaseDataModelJavaScript.git
 [submodule "view/lib/wikibase-termbox"]
 	path = view/lib/wikibase-termbox
 	url = https://gerrit.wikimedia.org/r/wikibase/termbox
EOF
git submodule update --init
cd .. # back to extensions/

cp -rT $originalDirectory EntitySchema

cd .. # back to mediawiki/

cp $originalDirectory/build/ci-scripts/composer.local.json composer.local.json

composer install

# Try composer install again... this tends to fail from time to time
if [ $? -gt 0 ]; then
	composer install
fi

mysql -e 'create database test_db_wiki;' -uroot -proot -h"127.0.0.1"
php maintenance/install.php \
    --dbtype $DBTYPE \
    --dbserver 127.0.0.1 \
    --dbuser root \
    --dbpass root \
    --dbpath $(pwd) \
    --server http://127.0.0.1:8080/ \
    --pass ${MEDIAWIKI_PASSWORD} \
    TestWiki ${MEDIAWIKI_USER}
