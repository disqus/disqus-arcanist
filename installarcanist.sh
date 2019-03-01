#!/bin/bash
set -eo pipefail
set -xv

# Downloads arcanist, libphutil, etc and configures your system

: ${LOC_DIR:="/usr/local"}
: ${BIN_DIR:="$LOC_DIR/bin"}
: ${PHP_DIR:="$LOC_DIR/include/php"}

if [ ! -w "$LOC_DIR" ]; then
    if [ -z "$SUDO_USER" ]; then
        echo "Re-running installation with sudo (no permission on $LOC_DIR for current user)."
        exec sudo /bin/sh $0 $*
    else
        echo "We can't seem to access ${LOC_DIR}. Please check permissions on this folder and try again."
        exit -1
    fi;
fi;

# In the name of symlinks, test twice.
[ -e "$PHP_DIR" ] || mkdir -pv "$PHP_DIR"

# Install or update libphutil
echo "Updating libphutil.."
if [ -e "$PHP_DIR/libphutil" ]; then
    arc upgrade
else
    git clone 'https://secure.phabricator.com/diffusion/PHU/libphutil.git'  "$PHP_DIR/libphutil"
    git clone 'https://secure.phabricator.com/diffusion/ARC/arcanist.git'   "$PHP_DIR/arcanist"
fi

# Install or update libdisqus
echo "Updating libdisqus.."
if [ -e "$PHP_DIR/libdisqus" ]; then
    cd "$PHP_DIR/libdisqus" && git pull origin master
else
   git clone https://github.com/disqus/disqus-arcanist.git "$PHP_DIR/libdisqus"
fi

# Register arc commands
echo "Registering arc commands.."

ln -sfvr "$PHP_DIR/libdisqus/bin"/{create-arcconfig,update-arcanist} "$BIN_DIR/"
ln -sfvr "$PHP_DIR/arcanist/scripts/arcanist.php" "$BIN_DIR/arc"

# Because some versions of git for windows (read: non-cygwin) are simply not sane.
# This, like you'd expect, applies to the source, not the destination, as symlinks do not use such bits.
chmod +x "$BIN_DIR"/{create-arcconfig,update-arcanist,arc}

echo "Done!"
