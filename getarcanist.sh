#!/bin/bash
set -ex

curl -L https://raw.github.com/disqus/disqus-arcanist/master/installarcanist.sh -o /tmp/installarcanist.sh
/bin/bash /tmp/installarcanist.sh
