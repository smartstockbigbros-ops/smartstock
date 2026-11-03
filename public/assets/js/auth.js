/* SmartStock prototype sign-in. Accounts live in this browser's localStorage only -
 * fine for a navigable prototype, NOT real security. Swap for server auth later. */
(function () {
  'use strict';
  var USERS = 'smartstock.users', SESSION = 'smartstock.session';
  var form = document.getElementById('authForm'), errEl = document.getElementById('authError');

  function read(k, d) { try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? d : v; } catch (e) { return d; } }
  function write(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } }
  function fail(msg) { errEl.textContent = msg; errEl.hidden = false; }
  function hex(buf) { return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
  function hash(salt, pw) {
    var text = salt + ':' + pw;
    if (window.crypto && crypto.subtle) {
      return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(hex);
    }
    var h = 5381, i; for (i = 0; i < text.length; i++) h = ((h << 5) + h + text.charCodeAt(i)) | 0; // non-crypto fallback (insecure origins)
    return Promise.resolve('x' + (h >>> 0).toString(16));
  }
  function enter(username) {
    if (!write(SESSION, { username: username, at: Date.now() })) { fail('This browser is blocking storage, so you cannot stay signed in.'); return; }
    location.href = SS_AUTH.dashboard;
  }

  if (read(SESSION, null)) { location.replace(SS_AUTH.dashboard); return; }

  form.addEventListener('submit', function (e) {
    e.preventDefault(); errEl.hidden = true;
    var mode = form.dataset.mode, name = form.elements.username.value.trim(), pw = form.elements.password.value, users = read(USERS, []);
    var found = users.filter(function (u) { return u.username.toLowerCase() === name.toLowerCase(); })[0];

    if (mode === 'register') {
      if (name.length < 3) return fail('Username must be at least 3 characters.');
      if (!/^[\w.\-]+$/.test(name)) return fail('Username can only use letters, numbers, dot, dash and underscore.');
      if (pw.length < 6) return fail('Password must be at least 6 characters.');
      if (pw !== form.elements.confirm.value) return fail('Passwords do not match.');
      if (found) return fail('That username is already taken.');
      var salt = Math.random().toString(36).slice(2);
      hash(salt, pw).then(function (h) {
        users.push({ username: name, salt: salt, hash: h });
        if (!write(USERS, users)) return fail('This browser is blocking storage, so the account cannot be saved.');
        enter(name);
      });
    } else {
      if (!name || !pw) return fail('Enter your username and password.');
      if (!found) return fail('Invalid username or password.');
      hash(found.salt, pw).then(function (h) { if (h === found.hash) enter(found.username); else fail('Invalid username or password.'); });
    }
  });
})();
