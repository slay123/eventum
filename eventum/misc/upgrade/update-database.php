#!/usr/bin/php
<?php
// avoid setup redirecting us
define('INSTALL_PATH', realpath(dirname(__FILE__) . '/../..'));
define('CONFIG_PATH', INSTALL_PATH.'/config');

if (!file_exists(CONFIG_PATH. '/config.php')) {
	die("Can't find config.php from ". CONFIG_PATH . ". Did you forgot to copy config from old install?");
}

require_once INSTALL_PATH . '/init.php';
require_once APP_INC_PATH . 'db_access.php';

function db_getAll($query) {
	$query = str_replace('%TABLE_PREFIX%', APP_TABLE_PREFIX, $query);
	$query = str_replace('%DBNAME%', APP_SQL_DBNAME, $query);
	$res = $GLOBALS['db_api']->dbh->getAll($query, DB_FETCHMODE_ASSOC);
	if (PEAR::isError($res)) {
		echo $res->getMessage(), ': ', $res->getDebugInfo(), "\n";
		exit(1);
	}
	return $res;
}

function db_getOne($query) {
	$query = str_replace('%TABLE_PREFIX%', APP_TABLE_PREFIX, $query);
	$query = str_replace('%DBNAME%', APP_SQL_DBNAME, $query);
    $res = $GLOBALS['db_api']->dbh->getOne($query);
	if (PEAR::isError($res)) {
		echo $res->getMessage(), ': ', $res->getDebugInfo(), "\n";
		exit(1);
	}
	return $res;
}

function db_query($query) {
	$query = str_replace('%TABLE_PREFIX%', APP_TABLE_PREFIX, $query);
	$query = str_replace('%DBNAME%', APP_SQL_DBNAME, $query);
	$res = $GLOBALS['db_api']->dbh->query($query);
	if (PEAR::isError($res)) {
		echo $res->getMessage(), ': ', $res->getDebugInfo(), "\n";
		exit(1);
	}
	return $res;
}

function apply_db_changes($stmts) {
	foreach ($stmts as $stmt) {
		db_query($stmt);
	}
}

/*
 * database versions. each version script can create it's dynamic queries
 */
$versions = array(
	1 => '01_notes.php',
	2 => '02_usr_alias.php',
);

if (php_sapi_name() != 'cli') {
	echo "<pre>\n";
}

// sanity check. check that the version table exists.
$version = db_getOne("SELECT ver_version FROM %TABLE_PREFIX%version");
if (!isset($version)) {
	# insert initial value
	db_query("INSERT INTO %TABLE_PREFIX%version SET ver_version=0");
	$version = 0;
}
$target = max(array_keys($versions));
echo "Current database version: $version; Versions available: $target\n";
if ($target < $version) {
	echo "ERROR: Your database version is greater ($version) than this upgrade supports ($target)!\n";
	exit(1);
}
if ($target == $version) {
	echo "Database already at version $version. Nothing to upgrade.\n";
	exit(0);
}

echo "Upgrading database to version $target\n";
$changes = array();
for ($i = $version + 1; $i <= $target; $i++) {
	if (empty($versions[$i])) {
		echo "ERROR: patch $i is not recorded in upgrade script.\n";
		exit(1);
	}
	$patch = APP_SQL_PATCHES_PATH . '/' . $versions[$i];
	echo "Checking patch $patch\n";
	if (!file_exists($patch)) {
		echo "ERROR: Patch file doesn't exist\n";
		exit(1);
	}
	require $patch;
	$func = "db_patch_$i";
	if (!function_exists($func)) {
		echo "ERROR: Patch did not define '$func' function\n";
		exit(1);
	}
	$patchset = $func();
	echo "Adding ", count($patchset), " queries\n";
	$changes[$i] = $patchset;
}

if (count($changes) == 0) {
	echo "No database changes\n";
	exit(0);
}

for ($i = $version + 1; $i <= $target; $i++) {
	echo "Applying patch ", $i, "\n";
	apply_db_changes($changes[$i]);
	db_query("UPDATE %TABLE_PREFIX%version SET ver_version=$i");
}

if (php_sapi_name() != 'cli') {
	echo "</pre>\n";
}
