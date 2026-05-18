'use strict';

import init, {
  generate_private_key,
  public_key_from_private_key,
  is_valid_private_key,
  sign_message,
  solve_challenge,
} from '/pkg/pow_clicker_wasm.js';

const PRIVATE_KEY_STORAGE_KEY = 'pow_clicker_private_key';

const statusElement = document.getElementById('wallet-status');
const balanceElement = document.getElementById('wallet-balance');
const actionsElement = document.getElementById('wallet-actions');

const createButton = document.getElementById('create-private-key-button');
const importButton = document.getElementById('import-private-key-button');
const export_privkey_btn = document.getElementById('export-private-key-button');
const solve_challenge_btn = document.getElementById('solve-challenge-button');
const solve_spinner = document.getElementById('solve-spinner');
const display_challenge = document.getElementById('display-challenge');
const display_work_nonce = document.getElementById('display-work-nonce');
const display_hash = document.getElementById('display-hash');

await init();

function getPrivateKey() {
  const privateKey = localStorage.getItem(PRIVATE_KEY_STORAGE_KEY);

  if (privateKey === null || privateKey.trim() === '') {
    return null;
  }

  return privateKey.trim().toLowerCase();
}

function savePrivateKey(privateKey) {
  localStorage.setItem(PRIVATE_KEY_STORAGE_KEY, privateKey.toLowerCase());
}

async function getBalance(privateKey, publicKey) {
  const message = "Get Balance";
  const nonce = await getNonce(publicKey);

  const signature = sign_message(nonce, privateKey, message);
  const response = await fetch("/api/balance.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      nonce: nonce,
      public_key: publicKey,
      message: message,
      signature: signature,
    }),
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error ?? `HTTP error ${response.status}`);
  }

  return data.balance;
}

async function getNonce(publicKey) {
  const response = await fetch("/api/nonce.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      public_key: publicKey,
    }),
  })

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error ?? `HTTP error ${response.status}`);
  }

  return data.nonce;
}

async function refreshWalletUi() {
  const privateKey = getPrivateKey();

  if (privateKey === null) {
    statusElement.textContent = 'No private key found.';
    balanceElement.textContent = '';
    actionsElement.hidden = false;
    return;
  }

  if (!is_valid_private_key(privateKey)) {
    statusElement.textContent = 'Invalid private key in localStorage.';
    balanceElement.textContent = '';
    actionsElement.hidden = false;
    return;
  }

  statusElement.textContent = 'Private key found.';
  balanceElement.textContent = 'Balance: loading...';
  actionsElement.hidden = true;
  export_privkey_btn.hidden = false;

  const publicKey = public_key_from_private_key(privateKey);
  const balance = await getBalance(privateKey, publicKey);

  balanceElement.textContent = `Balance: ${balance}`;
  solve_challenge_btn.hidden = false;
}

async function getChallenge(privateKey, publicKey) {
  const message = "Get Challenge";
  const nonce = await getNonce(publicKey);

  const signature = sign_message(nonce, privateKey, message);
  const response = await fetch("/api/get_task.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      nonce: nonce,
      public_key: publicKey,
      message: message,
      signature: signature,
    }),
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error ?? `HTTP error ${response.status}`);
  }

  return data.challenge;
}

async function submitProof(privateKey, publicKey, work_nonce) {
  const message = work_nonce;
  const nonce = await getNonce(publicKey);

  const signature = sign_message(nonce, privateKey, message);
  const response = await fetch("/api/submit_work.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      nonce: nonce,
      public_key: publicKey,
      message: message,
      signature: signature,
    }),
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error ?? `HTTP error ${response.status}`);
  }

  solve_spinner.hidden = true;
  await refreshWalletUi();
}

async function createPrivateKey() {
  const privateKey = generate_private_key();

  savePrivateKey(privateKey);

  await refreshWalletUi();
}

async function importPrivateKeyFromClipboard() {
  const text = await navigator.clipboard.readText();
  const privateKey = text.trim().toLowerCase();

  if (!is_valid_private_key(privateKey)) {
    alert('Invalid private key. Expected 64 hex characters.');
    return;
  }

  savePrivateKey(privateKey);

  await refreshWalletUi();
}

async function exportPrivateKeyToClipboard() {
  const privateKey = getPrivateKey();
  await navigator.clipboard.writeText(privateKey);
}

function nextFrame() {
  return new Promise((resolve) => {
    requestAnimationFrame(resolve);
  });
}

async function solveChallenge() {
  solve_challenge_btn.hidden = true;
  solve_spinner.hidden = false;
  const privateKey = getPrivateKey();
  const publicKey = public_key_from_private_key(privateKey);

  const challenge = await getChallenge(privateKey, publicKey);
  display_challenge.textContent = `Got challenge: 0x${challenge}`;
  display_work_nonce.textContent = ``;
  display_hash.textContent = ``;
  await nextFrame();
  await nextFrame();

  const work_res = solve_challenge(challenge, 5);
  const work_nonce = work_res.work_nonce;
  const combined_num = work_res.combined_num;
  const hash = work_res.hash;

  display_work_nonce.textContent = `Found work-nonce: ${work_nonce}`
  display_hash.textContent = `hash( 0x${combined_num} ) = 0x${hash}`

  await submitProof(privateKey, publicKey, work_nonce);
}

createButton.addEventListener('click', () => {
  createPrivateKey().catch((error) => {
    console.error(error);
    alert(`Failed to create private key: ${error.message}`);
  });
});

importButton.addEventListener('click', () => {
  importPrivateKeyFromClipboard().catch((error) => {
    console.error(error);
    alert(`Failed to import private key: ${error.message}`);
  });
});

export_privkey_btn.addEventListener('click', () => {
  exportPrivateKeyToClipboard().catch((error) => {
    console.error(error);
    alert(`Failed to export private key: ${error.message}`);
  })
});

solve_challenge_btn.addEventListener('click', () => {
  solveChallenge().catch((error) => {
    console.error(error);
    alert(`Failed to get or solve challenge: ${error.message}`);
  })
});

refreshWalletUi().catch((error) => {
  console.error(error);
  statusElement.textContent = 'Wallet error.';
  balanceElement.textContent = error.message;
});
