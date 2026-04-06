#!/bin/bash
set -e
cd "$(dirname "$0")"
VERSION=$(grep "\$this->version" module/core/modules/modBulkrfq.class.php | sed "s/.*= '//;s/'.*//" )
echo "Building bulkrfq-${VERSION}.zip …"
rm -rf /tmp/bulkrfq-zip
mkdir -p /tmp/bulkrfq-zip/bulkrfq
cp -r module/* /tmp/bulkrfq-zip/bulkrfq/
cd /tmp/bulkrfq-zip
zip -r "/Users/zacharymelo/doliBulkRFQ/bulkrfq-${VERSION}.zip" bulkrfq/
rm -rf /tmp/bulkrfq-zip
echo "Built bulkrfq-${VERSION}.zip"
