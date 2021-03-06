#!/bin/sh
# vim: set noexpandtab tabstop=4 shiftwidth=4 encoding=utf-8:
# +----------------------------------------------------------------------+
# | Eventum - Issue Tracking System                                      |
# +----------------------------------------------------------------------+
# | Copyright 2011, Elan Ruusamäe <glen@delfi.ee>                        |
# | Copyright (c) 2011 - 2014 Eventum Team.                              |
# +----------------------------------------------------------------------+
# |                                                                      |
# | This program is free software; you can redistribute it and/or modify |
# | it under the terms of the GNU General Public License as published by |
# | the Free Software Foundation; either version 2 of the License, or    |
# | (at your option) any later version.                                  |
# |                                                                      |
# | This program is distributed in the hope that it will be useful,      |
# | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
# | GNU General Public License for more details.                         |
# |                                                                      |
# | You should have received a copy of the GNU General Public License    |
# | along with this program; if not, write to:                           |
# |                                                                      |
# | Free Software Foundation, Inc.                                       |
# | 51 Franklin Street, Suite 330                                          |
# | Boston, MA 02110-1301, USA.                                          |
# +----------------------------------------------------------------------+

set -e
# download and update PEAR packages
pear_pkgs="
DB-stable
Auth_SASL-stable
Date-stable
File_Util
Mail-stable
Mail_Mime-stable
Mail_mimeDecode-stable
Math_Stats
Net_POP3-stable
Net_SMTP-stable
Net_SmartIRC-stable
Net_Socket-stable
Net_URL-stable
PEAR-stable
Text_Diff-stable
XML_RPC-stable
Net_LDAP2-stable
"

t=pear-root

download_pear() {
	for p in $pear_pkgs; do
		pear download $p
	done
	touch pear.download
}

install_pear() {
	install -d $t
	> $t/VERSIONS
	for p in $pear_pkgs; do
		p=${p%-*}
		f=$(echo $p-*.tgz)
		pear install -O -n -l -f -P $t $f
		v=${f#$p-}
		v=${v%.tgz}
		echo "- $p $v" >> $t/VERSIONS
	done
	touch pear.install
}

clean_pear() {
	local t=$1
	test -n "$t"
	rm -rf $t/usr/bin $t/usr/share/doc $t/usr/share/pear/tests $t/usr/share/pear/.??*

	# Mail_Mime
	rm -rf data/Mail_Mime

	# Mail
	rm -f Mail/mock.php
	rm -f Mail/smtpmx.php

	# Math_Stats
	rm -rf Math/examples

	# XML_RPC
	rm -f XML/RPC/Dump.php

	# Structures_Graph
	rm -rf data/Structures_Graph

	# PEAR
	rm -rf data/PEAR
	rm -f pearcmd.php peclcmd.php
	rm -rf OS PEAR
	rm -f System.php

	# DB
	for a in DB/*.php; do
		case "${a##*/}" in
		common.php | mysql*.php)
			;;
		*)
			rm -f $a
		esac
	done

	# Math_Stats
	rm -rf data/Math_Stats
	rm -rf contrib/ignatius_reilly

	test ! -d data || rmdir data
	test ! -d contrib || rmdir contrib

	# here's shell oneliner to remove ?> from all files which have it on their last line:
	find -name '*.php' | xargs -r sed -i -e '${/^?>$/d}'
	# sometimes if you are hit by this problem, you need to kill last empty line first:
	find -name '*.php' | xargs -r sed -i -e '${/^$/d}'
	# and as well can remove trailing spaces/tabs:
	find -name '*.php' | xargs -r sed -i -e 's/[\t ]\+$//'
	# remove DOS EOL
	find -name '*.php' | xargs -r sed -i -e 's,\r$,,'
}

[ -f pear.download ] || download_pear
[ -f pear.install ] || install_pear
[ -f pear.clean ] || { cd $t/usr/share/pear; clean_pear $t; }

r=$(pwd)
for t in $r/vendor/pear-pear.php.net/*; do
	cd $t
	clean_pear $t
done

cd $r/vendor/pear-pear.php.net
rm -rf Archive_Tar
rm -rf Console_Getopt
rm -rf Structures_Graph
