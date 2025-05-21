#!/usr/bin/env bash

if [ "$#" -lt 4 ]; then
	cat << EOM
Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]
EOM
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

set -ex

install_wp() {
	mkdir -p "$WP_CORE_DIR"

	if [ "$WP_VERSION" == 'latest' ]; then
		download_url=https://wordpress.org/latest.tar.gz
	else
		download_url=https://wordpress.org/wordpress-$WP_VERSION.tar.gz
	fi

	curl -o /tmp/wordpress.tar.gz -fSL $download_url
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"

	curl -o "$WP_CORE_DIR/wp-tests-config.php" -fSL https://raw.githubusercontent.com/WordPress/wordpress-develop/master/wp-tests-config-sample.php
	sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_CORE_DIR/wp-tests-config.php"
	sed -i "s/yourusernamehere/$DB_USER/" "$WP_CORE_DIR/wp-tests-config.php"
	sed -i "s/yourpasswordhere/$(echo $DB_PASS | sed 's/[\&/]/\\&/g')/" "$WP_CORE_DIR/wp-tests-config.php"
	sed -i "s|localhost|$DB_HOST|" "$WP_CORE_DIR/wp-tests-config.php"
}

install_test_suite() {
	mkdir -p "$WP_TESTS_DIR"

	curl -o /tmp/wordpress-tests-lib.tar.gz -fSL https://github.com/WordPress/wordpress-develop/archive/master.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress-tests-lib.tar.gz -C "$WP_TESTS_DIR"
}

install_db() {
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" || true
}

install_wp
install_test_suite
install_db
