#!/bin/bash
set -e

INSTALL_SCRIPT="/tmp/installarcanist.sh"

curl -L https://raw.github.com/disqus/disqus-arcanist/master/installarcanist.sh -o $INSTALL_SCRIPT
/bin/bash $INSTALL_SCRIPT
rm $INSTALL_SCRIPT
