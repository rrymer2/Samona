(function () {
  'use strict';

  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

  // ---------- Header shadow on scroll ----------
  const header = $('.site-header');
  if (header) {
    const onScroll = () => {
      header.classList.toggle('scrolled', window.scrollY > 30);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // ---------- Mobile nav ----------
  const toggle = $('.nav-toggle');
  const links = $('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', () => {
      const open = links.classList.toggle('open');
      toggle.classList.toggle('open', open);
      document.body.style.overflow = open ? 'hidden' : '';
    });
    $$('.nav-links a').forEach(a => a.addEventListener('click', () => {
      links.classList.remove('open');
      toggle.classList.remove('open');
      document.body.style.overflow = '';
    }));
  }

  // ---------- Reveal on scroll ----------
  const revealEls = $$('.reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach(el => io.observe(el));
  } else {
    revealEls.forEach(el => el.classList.add('is-visible'));
  }

  // ---------- Counter animation ----------
  const counters = $$('[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    const animateCount = (el) => {
      const target = parseFloat(el.dataset.count);
      const suffix = el.dataset.suffix || '';
      const decimals = (target % 1 !== 0) ? 1 : 0;
      const duration = 1600;
      const start = performance.now();
      const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        const value = target * eased;
        el.textContent = value.toFixed(decimals) + suffix;
        if (t < 1) requestAnimationFrame(step);
        else el.textContent = target.toFixed(decimals) + suffix;
      };
      requestAnimationFrame(step);
    };
    const co = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          co.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    counters.forEach(c => co.observe(c));
  }

  // ---------- Login form ----------
  const loginForm = $('#login-form');
  if (loginForm) {
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const banner  = $('#login-banner');
    const submit  = $('button[type="submit"]', loginForm);
    const submitOriginalHTML = submit ? submit.innerHTML : '';

    const showBanner = (msg) => {
      if (!banner) return;
      banner.textContent = msg;
      banner.hidden = false;
    };
    const hideBanner = () => { if (banner) banner.hidden = true; };
    const resetSubmit = () => {
      if (!submit) return;
      submit.disabled = false;
      submit.innerHTML = submitOriginalHTML;
    };

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideBanner();

      const emailField = $('[data-field="email"]', loginForm);
      const passField  = $('[data-field="password"]', loginForm);
      const emailInput = $('input', emailField);
      const passInput  = $('input', passField);

      emailField.classList.remove('has-error');
      passField.classList.remove('has-error');

      let ok = true;
      if (!emailRe.test(emailInput.value.trim())) {
        emailField.classList.add('has-error');
        ok = false;
      }
      if (passInput.value.length < 1) {
        passField.classList.add('has-error');
        ok = false;
      }
      if (!ok) return;

      submit.disabled = true;
      submit.textContent = 'Signing in…';

      try {
        const res = await fetch(loginForm.action, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            email: emailInput.value.trim(),
            password: passInput.value,
          }),
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok && data.ok) {
          window.location.href = data.redirect || 'dashboard.php';
          return;
        }
        if (res.status === 401) {
          showBanner('Invalid email or password.');
        } else if (res.status === 400) {
          showBanner('Please enter your email and password.');
        } else {
          showBanner('Sign-in failed. Please try again.');
        }
      } catch (err) {
        showBanner('Network error. Please try again.');
      } finally {
        resetSubmit();
      }
    });
  }

  // ---------- Current year in footer ----------
  const yearEl = $('#year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();
})();
