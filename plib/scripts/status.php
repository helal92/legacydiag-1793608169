<?php
foreach (['/tmp/legacydiag_report.txt', '/opt/psa/var/modules/legacydiag/report.txt'] as $f) {
    if (is_readable($f) && filesize($f) > 0) {
        echo "FILE: $f\n";
        readfile($f);
        exit;
    }
}
echo "NO_REPORT\n";
