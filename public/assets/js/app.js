(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const sidebar = document.querySelector('#sidebar');
  const backdrop = document.querySelector('#sidebar-backdrop');
  const openSidebar = () => {
    sidebar?.classList.remove('-translate-x-full');
    backdrop?.classList.remove('hidden');
  };
  const closeSidebar = () => {
    sidebar?.classList.add('-translate-x-full');
    backdrop?.classList.add('hidden');
  };

  document.querySelectorAll('[data-sidebar-open]').forEach((button) => button.addEventListener('click', openSidebar));
  document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', closeSidebar));

  // data-motion="fade-up" entrance animation lives in motion.js (resources/js/motion.js, bundled as
  // motion-bundle.js) - it replaced this file's original native-WAAPI version so the two don't both
  // animate the same elements at once.

  document.querySelectorAll('[data-add-line]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.querySelector(button.getAttribute('data-add-line'));
      const template = document.querySelector(button.getAttribute('data-template'));
      if (!target || !template) return;
      target.insertAdjacentHTML('beforeend', template.innerHTML);
    });
  });

  document.querySelectorAll('[data-client-select]').forEach((select) => {
    const form = select.closest('form');
    const panel = form?.querySelector('[data-new-client-panel]');
    const requiredFields = form ? form.querySelectorAll('[data-new-client-required]') : [];
    const syncClientPanel = () => {
      const creatingClient = select.value === '__new__';
      panel?.classList.toggle('hidden', !creatingClient);
      requiredFields.forEach((field) => {
        field.toggleAttribute('required', creatingClient);
      });
    };

    select.addEventListener('change', syncClientPanel);
    syncClientPanel();
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove-line]');
    if (remove) {
      const row = remove.closest('[data-line-row]');
      if (row) row.remove();
    }
  });

  // Disables submit buttons after first submit so a double-click, slow network, or
  // resubmitting a cached form after pressing "back" can't create duplicate records.
  document.querySelectorAll('form').forEach((form) => {
    if (form.hasAttribute('data-allow-resubmit')) return;
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        button.disabled = true;
        button.dataset.submitting = '1';
      });
    });
  });

  window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[data-submitting="1"]').forEach((button) => {
      button.disabled = false;
      delete button.dataset.submitting;
    });
  });

  document.querySelectorAll('[data-chart]').forEach((canvas) => {
    const values = JSON.parse(canvas.getAttribute('data-values') || '[]');
    const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
    const ctx = canvas.getContext('2d');
    if (!ctx || values.length === 0) return;

    const width = canvas.width = canvas.offsetWidth * window.devicePixelRatio;
    const height = canvas.height = canvas.offsetHeight * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    const cssWidth = width / window.devicePixelRatio;
    const cssHeight = height / window.devicePixelRatio;
    const max = Math.max(...values, 1);
    const padding = 20;
    const step = (cssWidth - padding * 2) / Math.max(values.length - 1, 1);

    ctx.lineWidth = 3;
    ctx.strokeStyle = '#0ea394';
    ctx.beginPath();
    values.forEach((value, index) => {
      const x = padding + index * step;
      const y = cssHeight - padding - ((value / max) * (cssHeight - padding * 2));
      if (index === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.fillStyle = '#8b5cf6';
    values.forEach((value, index) => {
      const x = padding + index * step;
      const y = cssHeight - padding - ((value / max) * (cssHeight - padding * 2));
      ctx.beginPath();
      ctx.arc(x, y, 4, 0, Math.PI * 2);
      ctx.fill();
    });

    ctx.fillStyle = '#637487';
    ctx.font = '11px system-ui';
    labels.forEach((label, index) => {
      if (index % Math.ceil(labels.length / 4) !== 0) return;
      ctx.fillText(label, padding + index * step - 10, cssHeight - 4);
    });
  });

  document.querySelectorAll('[data-test-connection]').forEach((button) => {
    button.addEventListener('click', async () => {
      const form = button.closest('form');
      const url = button.getAttribute('data-test-connection');
      const fields = (button.getAttribute('data-test-fields') || '').split(',').filter(Boolean);
      const result = document.getElementById(button.getAttribute('data-test-result') || '');
      const label = button.querySelector('[data-test-label]');
      const csrfInput = form?.querySelector('input[name="_csrf"]');

      const body = new URLSearchParams();
      fields.forEach((name) => {
        const field = form?.querySelector(`[name="${name}"]`);
        if (field) body.set(name, field.value);
      });
      if (csrfInput) body.set('_csrf', csrfInput.value);

      const originalLabel = label ? label.textContent : '';
      button.disabled = true;
      if (label) label.textContent = 'Testing…';
      if (result) {
        result.textContent = '';
        result.className = 'text-sm font-semibold';
      }

      try {
        const response = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (result) {
          result.textContent = data.message || (data.ok ? 'Connection succeeded.' : 'Connection failed.');
          result.classList.add(data.ok ? 'text-brand-700' : 'text-red-700');
        }
      } catch {
        if (result) {
          result.textContent = 'Could not reach the server to test the connection.';
          result.classList.add('text-red-700');
        }
      } finally {
        button.disabled = false;
        if (label) label.textContent = originalLabel;
      }
    });
  });

  const logoInput = document.querySelector('#logo-input');
  const logoPreview = document.querySelector('#logo-preview');
  const logoPreviewEmpty = document.querySelector('#logo-preview-empty');
  logoInput?.addEventListener('change', () => {
    const file = logoInput.files?.[0];
    if (!file || !logoPreview) return;
    const reader = new FileReader();
    reader.onload = () => {
      logoPreview.src = String(reader.result);
      logoPreview.classList.remove('hidden');
      logoPreviewEmpty?.classList.add('hidden');
    };
    reader.readAsDataURL(file);
  });

  const cookieBanner = document.querySelector('#cookie-banner');
  if (cookieBanner && localStorage.getItem('ledgerflow_cookie_notice') !== 'accepted') {
    cookieBanner.classList.remove('hidden');
  }

  document.querySelectorAll('[data-cookie-accept]').forEach((button) => {
    button.addEventListener('click', () => {
      localStorage.setItem('ledgerflow_cookie_notice', 'accepted');
      cookieBanner?.classList.add('hidden');
    });
  });
})();
