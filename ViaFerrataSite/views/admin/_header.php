<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? escape($pageTitle) . ' — Admin' : 'Panel Admin' ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#ecfdf5', 100:'#d1fae5', 200:'#a7f3d0', 500:'#10b981', 600:'#059669', 700:'#047857', 900:'#064e3b' }
                    }
                }
            }
        }
    </script>
    <style>body { -webkit-font-smoothing: antialiased; }</style>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
