#!/bin/bash
############################################################
#
# PHPStateMapper / autoload.sh
#
# This script removes all "require_once" statements from the
# PHPStateMapper project. It's intended to be run if
# autoloading is enabled to increase load times.
#
# Author:   Andrew Kandels <me@andrewkandels.com>
# Date:     October 28, 2010
# Project:  http://andrewkandels.com/PHPStateMapper
#
############################################################

SED_BIN=$(which sed)
if [ ! -x $SED_BIN ]; then
    echo "sed is required by this script."
    exit 1
fi

FIND_BIN=$(which find)
if [ ! -x $FIND_BIN ]; then
    echo "find is required by this script."
    exit 1
fi

EGREP_BIN=$(which egrep)
if [ ! -x $EGREP_BIN ]; then
    echo "grep extended (egrep) is required by this script."
    exit 1
fi

XARGS_BIN=$(which xargs)
if [ ! -x $XARGS_BIN ]; then
    echo "xargs is required by this script."
    exit 1
fi

$FIND_BIN $0 . -type f | \
    $EGREP_BIN "\.php$" | \
    $XARGS_BIN $SED_BIN -i '' '/^require_once /d'

echo "All require_once statements have been removed from the project source."
exit 0
