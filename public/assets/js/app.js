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

  const syncDiscountInput = (select) => {
    const input = select.closest('[data-line-row]')?.querySelector('[data-discount-value]');
    if (!input) return;
    if (select.value === 'percent') input.setAttribute('max', '100');
    else input.removeAttribute('max');
  };

  document.querySelectorAll('[data-discount-type]').forEach(syncDiscountInput);
  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-discount-type]')) syncDiscountInput(event.target);
  });

  document.querySelectorAll('[data-add-line]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.querySelector(button.getAttribute('data-add-line'));
      const template = document.querySelector(button.getAttribute('data-template'));
      if (!target || !template) return;
      target.insertAdjacentHTML('beforeend', template.innerHTML);
      target.querySelectorAll('[data-discount-type]').forEach(syncDiscountInput);
    });
  });

  let entityLookupId = 0;

  const setupEntityLookup = ({ input, entity, select = null, allowCreate = false, profileBase = '' }) => {
    const label = entity === 'client' ? 'client' : 'vendor';
    const plural = `${label}s`;
    const listboxId = `entity-listbox-${++entityLookupId}`;
    const wrapper = document.createElement('div');
    const listbox = document.createElement('div');
    const live = document.createElement('span');
    let options = [];
    let activeIndex = -1;
    let debounceTimer = null;
    let controller = null;
    let requestNumber = 0;

    wrapper.className = 'entity-combobox';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    input.classList.add('entity-combobox-input');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listboxId);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('maxlength', '190');

    listbox.id = listboxId;
    listbox.className = 'entity-listbox';
    listbox.setAttribute('role', 'listbox');
    listbox.hidden = true;
    wrapper.appendChild(listbox);

    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('aria-atomic', 'true');
    wrapper.appendChild(live);

    const close = () => {
      listbox.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    };

    const open = () => {
      listbox.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    const setActive = (index) => {
      if (options.length === 0) return;
      activeIndex = (index + options.length) % options.length;
      options.forEach((option, optionIndex) => {
        const active = optionIndex === activeIndex;
        option.setAttribute('aria-selected', active ? 'true' : 'false');
        if (active) {
          input.setAttribute('aria-activedescendant', option.id);
          option.scrollIntoView({ block: 'nearest' });
        }
      });
    };

    const addOption = ({ name, meta = '', create = false, onChoose }) => {
      const option = document.createElement('div');
      const nameElement = document.createElement('span');
      option.id = `${listboxId}-option-${options.length}`;
      option.className = create ? 'entity-option entity-option-create' : 'entity-option';
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.tabIndex = -1;
      nameElement.className = 'entity-option-name';
      nameElement.textContent = name;
      option.appendChild(nameElement);

      if (meta) {
        const metaElement = document.createElement('span');
        metaElement.className = 'entity-option-meta';
        metaElement.textContent = meta;
        option.appendChild(metaElement);
      }

      option.addEventListener('mouseenter', () => setActive(options.indexOf(option)));
      option.addEventListener('mousedown', (event) => event.preventDefault());
      option.addEventListener('click', onChoose);
      listbox.appendChild(option);
      options.push(option);
    };

    const render = (results, query, prompt = '') => {
      listbox.replaceChildren();
      options = [];
      activeIndex = -1;

      if (prompt) {
        const status = document.createElement('p');
        status.className = 'entity-status';
        status.textContent = prompt;
        listbox.appendChild(status);
      } else if (results.length === 0) {
        const status = document.createElement('p');
        status.className = 'entity-status';
        status.textContent = `No existing ${plural} match “${query}”.`;
        listbox.appendChild(status);
      }

      const normalizedQuery = query.trim().toLocaleLowerCase();
      results.forEach((result) => {
        const meta = [result.contact_name, result.email, result.currency].filter(Boolean).join(' · ');
        addOption({
          name: result.name,
          meta: meta || `Existing ${label}`,
          onChoose: () => {
            if (select) {
              if (!Array.from(select.options).some((option) => option.value === String(result.id))) {
                select.add(new Option(result.name, String(result.id)));
              }
              select.value = String(result.id);
              input.value = result.name;
              input.setCustomValidity('');
              select.dispatchEvent(new Event('change', { bubbles: true }));
              close();
              input.focus();
              return;
            }

            window.location.assign(`${profileBase}${result.id}`);
          }
        });
      });

      if (allowCreate && query.trim() !== '') {
        const hasExactMatch = results.some((result) => result.name.trim().toLocaleLowerCase() === normalizedQuery);
        addOption({
          name: hasExactMatch
            ? `Create a different ${label} named “${query.trim()}”`
            : `Create new ${label} “${query.trim()}”`,
          meta: hasExactMatch ? 'Use this only if it is a separate record.' : `Continue with a new ${label} profile.`,
          create: true,
          onChoose: () => {
            if (select) {
              const form = select.closest('form');
              const newName = form?.querySelector(`[name="new_${entity}_name"]`);
              select.value = '__new__';
              input.value = query.trim();
              if (newName) newName.value = query.trim();
              input.setCustomValidity('');
              select.dispatchEvent(new Event('change', { bubbles: true }));
              close();
              newName?.focus();
              return;
            }

            close();
            const fields = Array.from(input.form?.elements || []);
            const nextField = fields[fields.indexOf(input) + 1];
            nextField?.focus?.();
          }
        });
      }

      open();
      live.textContent = results.length === 1
        ? `1 existing ${label} found.`
        : `${results.length} existing ${plural} found.`;
    };

    const showLoading = () => {
      listbox.replaceChildren();
      options = [];
      const status = document.createElement('p');
      status.className = 'entity-status';
      status.textContent = `Searching ${plural}…`;
      listbox.appendChild(status);
      open();
      live.textContent = `Searching ${plural}.`;
    };

    const search = async (query) => {
      controller?.abort();
      controller = new AbortController();
      const currentRequest = ++requestNumber;
      const url = new URL(`/${plural}/search`, window.location.origin);
      url.searchParams.set('q', query);
      showLoading();

      try {
        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
          signal: controller.signal
        });
        if (!response.ok) throw new Error(`Search returned ${response.status}`);
        const data = await response.json();
        if (currentRequest !== requestNumber) return;
        render(Array.isArray(data.results) ? data.results : [], query);
      } catch (error) {
        if (error.name === 'AbortError') return;
        if (select) {
          const selectWasRequired = input.required;
          close();
          wrapper.hidden = true;
          input.disabled = true;
          input.required = false;
          input.setCustomValidity('');
          select.classList.remove('hidden');
          select.removeAttribute('aria-hidden');
          select.removeAttribute('tabindex');
          select.required = selectWasRequired;
          const notice = document.createElement('p');
          notice.className = 'field-help text-amber-700';
          notice.textContent = `Search is unavailable. Choose a ${label} from the list.`;
          select.insertAdjacentElement('afterend', notice);
        } else {
          render([], query, `Search is unavailable. You can still create this ${label}; duplicates are checked when you save.`);
        }
      }
    };

    input.addEventListener('input', () => {
      const query = input.value.trim();
      clearTimeout(debounceTimer);
      controller?.abort();

      if (select) {
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        input.setCustomValidity(query === '' ? '' : `Choose an existing ${label} or choose the create option.`);
      }

      if (query === '') {
        close();
        live.textContent = '';
        return;
      }

      if (query.length < 2) {
        render([], query, `Type one more character to search existing ${plural}.`);
        return;
      }

      debounceTimer = window.setTimeout(() => search(query), 250);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (listbox.hidden) input.dispatchEvent(new Event('input'));
        else setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        event.preventDefault();
        options[activeIndex].click();
      } else if (event.key === 'Escape') {
        close();
      } else if (event.key === 'Tab') {
        close();
      }
    });

    input.addEventListener('focus', () => {
      if (input.value.trim().length >= 2 && listbox.childElementCount > 0) open();
    });
    document.addEventListener('click', (event) => {
      if (!wrapper.contains(event.target)) close();
    });

    return { close };
  };

  document.querySelectorAll('[data-entity-select]').forEach((select) => {
    const entity = select.getAttribute('data-entity-select');
    const form = select.closest('form');
    const allowCreate = select.hasAttribute('data-allow-create');
    const panel = form?.querySelector(`[data-new-${entity}-panel]`);
    const newName = form?.querySelector(`[name="new_${entity}_name"]`);
    const selectedOption = select.selectedOptions[0];
    const selectWasRequired = select.required;
    const input = document.createElement('input');

    input.className = 'field';
    input.type = 'text';
    input.placeholder = `Type to search ${entity}s…`;
    input.required = selectWasRequired;
    input.value = select.value === '__new__'
      ? (newName?.value || '')
      : (select.value ? selectedOption?.textContent.trim() || '' : '');

    select.insertAdjacentElement('beforebegin', input);
    select.classList.add('hidden');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;
    select.required = false;

    const syncCreatePanel = () => {
      const creating = allowCreate && select.value === '__new__';
      panel?.classList.toggle('hidden', !creating);
      panel?.querySelectorAll('input, textarea, select').forEach((field) => {
        field.disabled = !creating;
      });
      panel?.querySelectorAll(`[data-new-${entity}-required]`).forEach((field) => {
        field.toggleAttribute('required', creating);
      });
    };

    select.addEventListener('change', syncCreatePanel);
    syncCreatePanel();
    setupEntityLookup({ input, entity, select, allowCreate });
  });

  document.querySelectorAll('[data-entity-name-lookup]').forEach((input) => {
    const entity = input.getAttribute('data-entity-name-lookup');
    setupEntityLookup({
      input,
      entity,
      allowCreate: true,
      profileBase: `/${entity}s/`
    });
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
