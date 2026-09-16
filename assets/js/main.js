(() => {
  'use strict';

  const header = document.querySelector('[data-header]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const mobileMenu = document.querySelector('[data-mobile-menu]');

  const updateHeader = () => {
    if (!header) return;
    header.classList.toggle('scrolled', window.scrollY > 18);
  };
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });

  const setMenu = (open) => {
    if (!menuToggle || !mobileMenu) return;
    menuToggle.setAttribute('aria-expanded', String(open));
    mobileMenu.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
  };

  menuToggle?.addEventListener('click', () => {
    setMenu(menuToggle.getAttribute('aria-expanded') !== 'true');
  });
  mobileMenu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenu(false));
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenu(false);
  });

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px' });
    revealItems.forEach((item) => observer.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  const serviceSelect = document.querySelector('[data-service-select]');
  document.querySelectorAll('.js-service-link').forEach((link) => {
    link.addEventListener('click', () => {
      const service = link.dataset.service;
      if (serviceSelect && service) {
        serviceSelect.value = service;
        setTimeout(() => serviceSelect.focus({ preventScroll: true }), 450);
      }
    });
  });

  const form = document.querySelector('[data-repair-form]');
  if (!form) return;

  // Static pages: refresh the anti-spam timestamp on every page load.
  const formStarted = form.querySelector('input[name="form_started"]');
  if (formStarted) formStarted.value = Math.floor(Date.now() / 1000).toString();

  const submitButton = form.querySelector('[data-submit]');
  const submitLabel = form.querySelector('[data-submit-label]');
  const status = form.querySelector('[data-form-status]');
  const defaultLabel = submitLabel?.textContent || '';

  const clearErrors = () => {
    form.querySelectorAll('.field.has-error').forEach((field) => field.classList.remove('has-error'));
    form.querySelectorAll('.field-error').forEach((error) => error.remove());
  };

  const markInvalid = (input) => {
    const field = input.closest('.field');
    if (!field) return;
    field.classList.add('has-error');
    if (!field.querySelector('.field-error')) {
      const error = document.createElement('span');
      error.className = 'field-error';
      error.textContent = form.dataset.required || 'Required';
      field.appendChild(error);
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    status.textContent = '';
    status.className = 'form-status';

    const invalid = [...form.querySelectorAll(':invalid')].filter((el) => el.name !== 'website');
    if (invalid.length) {
      invalid.forEach(markInvalid);
      invalid[0].focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.setAttribute('aria-busy', 'true');
    if (submitLabel) submitLabel.textContent = form.dataset.sending || 'Sending...';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok !== true) throw new Error(data.message || 'Request failed');

      status.textContent = form.dataset.success || 'Thank you.';
      status.classList.add('success');
      form.reset();
      const started = form.querySelector('input[name="form_started"]');
      if (started) started.value = Math.floor(Date.now() / 1000).toString();
    } catch (error) {
      status.textContent = form.dataset.error || 'Something went wrong.';
      status.classList.add('error');
    } finally {
      submitButton.disabled = false;
      submitButton.removeAttribute('aria-busy');
      if (submitLabel) submitLabel.textContent = defaultLabel;
    }
  });
})();
