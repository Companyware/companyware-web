<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Anmelden · Administration</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="login-body"><main class="login-card">
  <section class="login-visual"><div class="brand-mark">A</div><div class="visual-copy"><span class="eyebrow">ADMIN FRAMEWORK</span><h1>Willkommen<br>zurück.</h1><p>Eine modulare Oberfläche für deine Werkzeuge, Prozesse und Plugins.</p></div><div class="visual-grid"></div></section>
  <section class="login-panel"><div class="login-form-wrap"><span class="eyebrow blue">SICHERER ZUGANG</span><h2>In der Administration anmelden</h2><p class="muted">Gib deine Zugangsdaten ein, um fortzufahren.</p>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif ?>
    <form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>">
      <label>Benutzername<input name="username" autocomplete="username" required autofocus placeholder="Dein Benutzername"></label>
      <label>Passwort<span class="password-wrap"><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Dein Passwort"><button type="button" class="show-password" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'">Anzeigen</button></span></label>
      <label class="check"><input type="checkbox" name="remember"> <span>Angemeldet bleiben</span></label><button class="primary wide" type="submit">Anmelden <span>→</span></button>
    </form><p class="login-help">Probleme bei der Anmeldung? Wende dich an deine Administration.</p>
  </div></section>
</main></body></html>
