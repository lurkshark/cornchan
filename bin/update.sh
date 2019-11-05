#!/bin/sh
wget --quiet --directory-prefix /home/public --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/index.php
EOF
