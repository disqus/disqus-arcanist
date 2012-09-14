#!/bin/bash

curl -L https://raw.github.com/disqus/disqus-arcanist/master/installarcanist.sh -o /tmp/installarcanist.sh || exit 1
/bin/sh /tmp/installarcanist.sh || exit 1