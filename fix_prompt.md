# Fix: Raw fetch() Violation in alerts/index.php

## Context
SafeSense is a PHP MVC hospital management system. The codebase has a strict
architecture rule: **all `fetch()` calls must go through the `ajaxPost()` helper
defined in `medical/public/js/app.js`** — never raw `fetch()` directly. This
ensures every request carries the required `X-Requested-With: XMLHttpRequest`
and `X-CSRF-Token` headers automatically.

## The Problem
`medical/app/Views/alerts/index.php` contains inline JavaScript at the bottom
of the file with two raw `fetch()` calls that bypass the `ajaxPost()` helper:

**Line ~142 — dismiss button handler:**
```js
fetch(window.BASE_URL+'/api/alerts/dismiss', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: 'id='+id
})
```

**Line ~148 — mark all read handler:**
```js
fetch(window.BASE_URL+'/api/alerts/read', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: 'id=all'
})
```

## The Fix
Replace both raw `fetch()` calls with `ajaxPost()`. The `ajaxPost(url, dataObject)`
helper accepts a plain object and handles serialization, headers, and error
handling internally.

**Fixed dismiss handler:**
```js
ajaxPost(window.BASE_URL + '/api/alerts/dismiss', { id: id })
  .then(() => {
    card.style.opacity = '0';
    card.style.transition = '.3s';
    setTimeout(() => card.remove(), 300);
  });
```

**Fixed mark all read handler:**
```js
ajaxPost(window.BASE_URL + '/api/alerts/read', { id: 'all' })
  .then(d => {
    document.querySelectorAll('.ss-alert-card-unread')
      .forEach(el => el.classList.remove('ss-alert-card-unread'));
    document.querySelectorAll('.badge.bg-primary').forEach(el => el.remove());
    const ub = document.getElementById('unreadBadge');
    if (ub) ub.textContent = '0 Unread';
    if (typeof setBadge === 'function') setBadge(0);
  });
```

## What NOT to Touch
- Do not modify `AlertController.php` — it is in the Do Not Touch list
- Do not modify `Alert.php` model methods
- Do not touch any other part of `alerts/index.php` — only the two fetch() calls
- Do not touch the IoT JS block inside `main.php` (polling, drawer, toast, modal
  queue) — those raw fetch() calls are intentionally inline and exempt

## Verification Steps (run after fixing)
1. Re-read `alerts/index.php` — confirm zero instances of raw `fetch(` remain
   in the inline script block at the bottom of the file
2. Confirm both handlers still call the correct URLs:
   `/api/alerts/dismiss` and `/api/alerts/read`
3. Confirm the dismiss button still removes the card from the DOM after success
4. Confirm the mark-all-read button still clears unread badges and the unread count
5. Confirm `ajaxPost` is defined and accessible at the point these calls are made
   (it is defined in `app.js` which is loaded in `main.php` before any view script)
6. Grep the entire `medical/app/Views/` directory for any other raw `fetch(`
   calls outside of `main.php` — there should be none
