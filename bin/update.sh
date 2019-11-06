#!/bin/sh
wget --quiet --timestamping --directory-prefix /home/public --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/index.php
EOF

# Nuke DB while still testing
rm /home/protected/cornchan.db
