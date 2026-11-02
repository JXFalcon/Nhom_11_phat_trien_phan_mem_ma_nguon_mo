<?php
$pageTitle = $pageTitle ?? 'Shopee';
$headHtml = $headHtml ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars((string)$pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<?= $headHtml ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars((string)$bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
