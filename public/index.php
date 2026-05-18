<?php

declare(strict_types=1);

?>

<!doctype html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PoW-Clicker</title>
  <script type="module" src="/assets/app.js"></script>
  <link rel="icon" href="/favicon.png" />
  <link rel="stylesheet" href="/style.css"/>
</head>
<body class="cover">
  <header></header>
  <main class="cover-center vt-page">

  <div class="card stack fill-page">
  <h1><span style="white-space: nowrap;">Proof-of-Work</span> Clicker</h1>

  <p id="wallet-status"></p>

    <div class="stack" id="wallet-actions" style="margin-top: auto;" hidden>
        <button id="create-private-key-button">
            Create new private key
        </button>

        <button class="secondary" id="import-private-key-button">
            Import private key from clipboard
        </button>
    </div>

  <h3 id="wallet-balance"></h3>
  <button id="solve-challenge-button" style="font-size: 2rem;" hidden>
    Click Me!
  </button>
  <div id="solve-spinner" class="loading-spinner" style="margin-top: auto; margin-bottom: auto; font-size: 2rem;" hidden></div>

  <div class="stack" style="font-size: 0.7em; color: var(--muted);">
  <p id=display-challenge></p>
  <p id=display-work-nonce style="--stack-gap: var(--s-2);"></p>
  <p id=display-hash style="--stack-gap: var(--s-2);"></p>
  </div>
  <button id="export-private-key-button" class="secondary" hidden>Export privkey</button>
  </div>

  </main>
  <footer></footer>
</body>
</html>
