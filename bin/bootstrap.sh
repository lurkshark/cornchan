#!/bin/sh
# This script is used to execute the update script
wget --quiet --timestamping --directory-prefix /home/private \
  https://raw.githubusercontent.com/lurkshark/cornchan/master/bin/update.sh
chmod a+x /home/private/update.sh
/home/private/update.sh
