<?php
// HEADER — OPEN <html> + CSS + OPEN layout wrapper
if (!function_exists('url')) {
  define('BASE_URL', '/'); // untuk stockbarang.test pakai '/'
  function url(string $path = ''): string
  {
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $base . '/' . $path;
  }
  function asset(string $path = ''): string
  {
    return url('assets/' . ltrim($path, '/'));
  }
}
$companyName = $companyName ?? 'PT Mandiri Jaya Top';
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($companyName) ?></title>

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/styles.css') ?>" rel="stylesheet">

  <style>
    :root {
      --sidebar-w: 200px;
    }

    html,
    body {
      height: 100%;
    }

    body {
      background: #f5f7fb;
      overflow-x: hidden;
    }

    .layout {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar kiri */
    .sidebar {
      width: var(--sidebar-w);
      flex: 0 0 var(--sidebar-w);
      background: #111827;
      color: #e5e7eb;
      display: flex;
      flex-direction: column;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: 1rem .9rem;
      border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .brand-logo {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      object-fit: cover;
      background: #fff;
    }

    .brand-fallback {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      background: #fff;
      color: #111827;
      display: none;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .nav-section-title {
      font-size: .78rem;
      text-transform: uppercase;
      opacity: .6;
      letter-spacing: .02em;
      padding: .6rem .9rem .25rem;
    }

    .sidebar .nav-link {
      color: #cbd5e1;
      display: flex;
      align-items: center;
      gap: .55rem;
      padding: .5rem .9rem;
      border-radius: .45rem;
      margin: .12rem .45rem;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      background: rgba(255, 255, 255, .08);
      color: #fff;
    }

    .sidebar-footer {
      margin-top: auto;
      padding: .9rem;
    }

    /* Area konten */
    .content {
      flex: 1 1 auto;
      padding: 1.25rem 1.25rem 2rem;
      min-width: 0;
    }

    .content .container,
    .content .container-sm,
    .content .container-md,
    .content .container-lg,
    .content .container-xl,
    .content .container-xxl {
      max-width: 100% !important;
    }

    .card {
      border-radius: .75rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .07);
    }

    .card-header {
      background: #f8f9fa;
      font-weight: 500;
    }

    .empty-state {
      border: 1px dashed #c9ced6;
      background: #fff;
      border-radius: .75rem;
      padding: 2rem 1rem;
      text-align: center;
    }

    .empty-state i {
      font-size: 2rem;
      opacity: .7;
    }
  </style>
</head>

<body>
  <div class="layout"><!-- OPEN layout -->