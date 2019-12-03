#!/bin/sh
# This script is used to keep the deployment up-to-date
wget --quiet --timestamping --output-document /home/private/current.json --input-file - << 'EOF'
https://api.github.com/repos/lurkshark/cornchan/branches/master
EOF

# If nothing has changed then just exit
if cmp -s /home/private/current.json /home/private/previous.json ; then
  exit 0
fi

# Master branch got updated since previous change
cp /home/private/current.json /home/private/previous.json

# Download main application files needed for operation
wget --quiet --timestamping --directory-prefix /home/public --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/index.php
EOF

# Download static files for styling
wget --quiet --timestamping --directory-prefix /home/public/static --input-file - << 'EOF'
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/.htaccess
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/favicon.png
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/normalize.css
https://raw.githubusercontent.com/lurkshark/cornchan/master/src/static/style.css
EOF

# Nuke DB while still testing
rm /home/protected/cornchan.db
