<?php
$scripts = $scripts ?? [];
foreach ($scripts as $src) {
    echo '  <script src="' . htmlspecialchars((string)$src, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
?>
