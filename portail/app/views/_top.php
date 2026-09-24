<?php
/** @var string $title  @var string|null $body_class  @var array|null $c */
$body_class = $body_class ?? '';
$logo_url = (isset($c) && $c && $c['logo'] !== null) ? '/' . h($c['slug']) . '/media/' . rawurlencode($c['logo']) : null;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#ffffff">
<title><?= h($title) ?></title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<?php if ($logo_url): ?><link rel="apple-touch-icon" href="<?= $logo_url ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(__DIR__ . '/../../public/assets/app.css') ?>">
</head>
<body class="<?= h($body_class) ?>"<?= (isset($c) && $c) ? ' data-slug="' . h($c['slug']) . '"' : '' ?>>
