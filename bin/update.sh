#!/bin/sh
# This script is used to keep the deployment up-to-date
wget --quiet --timestamping --directory-prefix /home/public --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/index.php
EOF

wget --quiet --timestamping --directory-prefix /home/public/static --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/favicon.png
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/normalize.css
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/style.css
EOF

# Nuke DB while still testing
rm /home/protected/cornchan.db
