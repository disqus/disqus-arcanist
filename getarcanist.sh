#!/usr/bin/bash

# Downloads arcanist, libphutil, etc and configures your system

BIN_DIR="/usr/local/bin"
PHP_DIR="/usr/local/include/php"

mkdir -p $PHP_DIR || exit -1

# Install or update libphutil
echo "Updating libphutil.."
if [ -e "$PHP_DIR/libphutil" ]; then
    cd "$PHP_DIR/libphutil" && git pull || exit -1
else
    git clone git://github.com/facebook/libphutil.git "$PHP_DIR/libphutil" || exit -1
fi

# Install or update arcanist
echo "Updating arcanist.."
if [ -e "$PHP_DIR/arcanist" ]; then
    cd "$PHP_DIR/arcanist" && git pull || exit -1
else
    git clone git://github.com/facebook/arcanist.git "$PHP_DIR/arcanist" || exit -1
fi

# Install or update phabricator
echo "Updating phabricator.."
if [ -e "$PHP_DIR/phabricator" ]; then
    cd "$PHP_DIR/phabricator" && git pull || exit -1
else
    git clone git://github.com/facebook/phabricator.git "$PHP_DIR/phabricator" || exit -1
fi

# Install or update libdisqus
echo "Updating libdisqus.."
if [ -e "$PHP_DIR/libdisqus" ]; then
    cd "$PHP_DIR/libdisqus" && git pull || exit -1
else
    git clone git://github.com/disqus/disqus-arcanist.git "$PHP_DIR/libdisqus" || exit -1
fi

# Build xphast
echo "Building xphast.."
sh "$PHP_DIR/libphutil/scripts/build_xhpast.sh" > /dev/null || exit -1

# Register arc commands
echo "Registering arc commands.."

## create-arcconfig
ln -fs "$PHP_DIR/libdisqus/bin/create-arcconfig" "$BIN_DIR/create-arcconfig" || exit -1
chmod +x "$BIN_DIR/create-arcconfig"

## update-arcanist
ln -fs "$PHP_DIR/libdisqus/bin/update-arcanist" "$BIN_DIR/update-arcanist" || exit -1
chmod +x "$BIN_DIR/update-arcanist"

## arc
echo "php $PHP_DIR/arcanist/scripts/arcanist.php  --load-phutil-library='$PHP_DIR/libdisqus/src' \"\$@\"" > "$BIN_DIR/arc"
chmod +x "$BIN_DIR/arc"

echo "Done!"