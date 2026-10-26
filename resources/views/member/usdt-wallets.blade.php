<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>LumiFlowOn - Verify USDT Wallet</title>
<style>
body{font:15px system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:1rem;background:#f8fafc;color:#172033}
section{background:white;padding:1.5rem;border-radius:12px;margin:1rem 0;border:1px solid #e2e8f0}
button{padding:.8rem;cursor:pointer;background:#1646a0;color:white;border:0;border-radius:7px}
button:disabled{opacity:.5}
#status{font-weight:600;overflow-wrap:anywhere}
</style>
<style id="LUMIFLOWON_MEMBER_GOLD_THEME_V1">
body{background:#090d16!important;color:#e2e8f0!important}
section,.card,table{background:#101e31!important;color:#e2e8f0!important;border-color:#b68b41!important}
h1,h2,h3{color:#f4c66b!important}
a{color:#f4c66b!important}
input,select,textarea{background:#0a1626!important;color:#fff!important;border-color:#826c48!important}
button{background:#b68b41!important;color:#08111e!important;border-radius:8px}
th,td{border-color:#41546c!important}
</style></head>
<body>
<h1>Verify BEP20 Wallet</h1>
<p><a href="{{ route('member.withdrawals.index') }}">Back to Withdrawals</a></p>
<section>
<p>Connect MetaMask and sign an ownership message. Never enter your seed phrase or private key. No USDT transfer is required.</p>
<button id="verify" type="button">Connect MetaMask &amp; Verify</button>
<p id="status" role="status" aria-live="polite"></p>
</section>
<section>
<h2>Verified Wallets</h2>
<div id="wallets">Loading...</div>
</section>
<script>
(() => {
'use strict';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const button = document.getElementById('verify');
const status = document.getElementById('status');
const wallets = document.getElementById('wallets');

async function api(url, body) {
  const response = await fetch(url, {
    method: body === undefined ? 'GET' : 'POST',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf
    },
    ...(body === undefined ? {} : {body: JSON.stringify(body)})
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.message || 'Request failed');
  return result;
}

async function refresh() {
  try {
    const result = await api('/member/usdt-wallets');
    wallets.replaceChildren();
    if (!Array.isArray(result.wallets) || !result.wallets.length) {
      wallets.textContent = 'No verified wallets yet.';
      return;
    }
    for (const wallet of result.wallets) {
      const p = document.createElement('p');
      p.textContent = wallet.network + ': ' + wallet.address;
      wallets.appendChild(p);
    }
  } catch (error) {
    wallets.textContent = error.message;
  }
}

button.addEventListener('click', async () => {
  button.disabled = true;
  try {
    if (!window.ethereum?.request) throw new Error('MetaMask is required.');
    status.textContent = 'Connecting wallet...';
    const accounts = await window.ethereum.request({method:'eth_requestAccounts'});
    const address = accounts[0];
    if (!address) throw new Error('No account selected.');

    const challenge = await api('/member/usdt-wallets/challenge', {
      network:'BEP20', address
    });

    status.textContent = 'Please sign the ownership message.';
    const signature = await window.ethereum.request({
      method:'personal_sign',
      params:[challenge.message,address]
    });

    const result = await api('/member/usdt-wallets/verify', {
      challenge_id:challenge.challenge_id,
      message:challenge.message,
      signature
    });

    if (!result.verified) throw new Error('Verification failed.');
    status.textContent = 'Wallet verified successfully.';
    await refresh();
  } catch (error) {
    status.textContent = error.message || 'Verification failed.';
  } finally {
    button.disabled = false;
  }
});
refresh();
})();
</script>
</body>
</html>