#!/bin/bash
set -e

# Downloads arcanist, libphutil, etc and configures your system

LOC_DIR="/usr/local"
BIN_DIR="$LOC_DIR/bin"
PHP_DIR="$LOC_DIR/include/php"

if [ ! -w "$LOC_DIR" ]; then
    if [ -z "$SUDO_USER" ]; then
        echo "Re-running installation with sudo (no permission on $LOC_DIR for current user)."
        exec sudo /bin/sh $0 $*
    else
        echo "We can't seem to access ${LOC_DIR}. Please check permissions on this folder and try again."
        exit -1
    fi;
fi;

if [ ! -e "$PHP_DIR" ]; then
    mkdir -p $PHP_DIR
fi;

# Install or update libphutil
echo "Updating libphutil.."
if [ -e "$PHP_DIR/libphutil" ]; then
    arc upgrade
else
    git clone git://github.com/facebook/libphutil.git "$PHP_DIR/libphutil"
    git clone git://github.com/facebook/arcanist.git "$PHP_DIR/arcanist"
    git clone git://github.com/facebook/phabricator.git "$PHP_DIR/phabricator"
fi

# Install or update libdisqus
echo "Updating libdisqus.."
if [ -e "$PHP_DIR/libdisqus" ]; then
    cd "$PHP_DIR/libdisqus" && git pull origin master
else
    git clone git://github.com/disqus/disqus-arcanist.git "$PHP_DIR/libdisqus"
fi

# Register arc commands
echo "Registering arc commands.."

## create-arcconfig
ln -fs "$PHP_DIR/libdisqus/bin/create-arcconfig" "$BIN_DIR/create-arcconfig"
chmod +x "$BIN_DIR/create-arcconfig"

## update-arcanist
ln -fs "$PHP_DIR/libdisqus/bin/update-arcanist" "$BIN_DIR/update-arcanist"
chmod +x "$BIN_DIR/update-arcanist"

## arc
echo "php $PHP_DIR/arcanist/scripts/arcanist.php \"\$@\"" > "$BIN_DIR/arc"
chmod +x "$BIN_DIR/arc"

echo "Done!"
