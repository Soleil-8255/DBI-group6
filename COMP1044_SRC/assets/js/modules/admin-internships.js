/**
 * Admin internships: collapsible add form; creatable company combobox on add + edit (shared data + create API).
 */
(function () {
  'use strict';

  const root = document.getElementById('admin-internships');
  if (!root) {
    return;
  }

  const btn = document.getElementById('btn-toggle-add-internship');
  const addWrap = document.getElementById('admin-add-internship-wrap');
  if (btn && addWrap) {
    btn.addEventListener('click', function () {
      const open = !addWrap.classList.contains('is-open');
      addWrap.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      addWrap.setAttribute('aria-hidden', open ? 'false' : 'true');
    });
  }

  const dataEl = document.getElementById('admin-internships-company-data');
  if (!dataEl) {
    return;
  }

  let companies = [];
  try {
    companies = JSON.parse(dataEl.textContent || '[]');
    if (!Array.isArray(companies)) {
      companies = [];
    }
  } catch (e) {
    companies = [];
  }

  const apiUrl = root.getAttribute('data-api-create-company') || '';
  const csrf = root.getAttribute('data-csrf') || '';

  const comboboxEls = root.querySelectorAll('.company-combobox');
  if (comboboxEls.length === 0) {
    return;
  }

  function findCompany(id) {
    const sid = String(id);
    for (let i = 0; i < companies.length; i += 1) {
      if (String(companies[i].company_id) === sid) {
        return companies[i];
      }
    }
    return null;
  }

  function showCompanyToast() {
    const t = document.createElement('div');
    t.className = 'company-combobox-toast';
    t.setAttribute('role', 'status');
    t.textContent = 'New company added successfully';
    document.body.appendChild(t);
    window.setTimeout(function () {
      t.classList.add('company-combobox-toast--out');
    }, 2200);
    window.setTimeout(function () {
      if (t.parentNode) {
        t.remove();
      }
    }, 2800);
  }

  function initCompanyCombobox(combo) {
    const hidden = combo.querySelector('input[type="hidden"][name="company_id"]');
    const input = combo.querySelector('.company-combobox__input');
    const panel = combo.querySelector('.suggestions-dropdown');
    const form = combo.closest('form');
    if (!hidden || !input || !panel) {
      return;
    }

    let openPanel = false;
    let ignoreNextBlur = false;

    function showPanel(on) {
      openPanel = on;
      if (on) {
        panel.style.display = 'block';
        input.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
      } else {
        panel.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
      }
    }

    function filterCompanies(q) {
      const t = (q || '').trim().toLowerCase();
      if (t === '') {
        return companies.slice(0, 20);
      }
      return companies.filter(function (c) {
        return c.company_name && String(c.company_name).toLowerCase().indexOf(t) >= 0;
      });
    }

    function hasExactName(q) {
      const t = (q || '').trim().toLowerCase();
      if (t === '') {
        return false;
      }
      return companies.some(function (c) {
        return String(c.company_name).toLowerCase() === t;
      });
    }

    function renderSuggestions() {
      const q = input.value;
      const matches = filterCompanies(q);
      const t = (q || '').trim();
      const needCreate = t.length > 0 && !hasExactName(t);
      const esc = function (s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      };

      const parts = [];
      matches.forEach(function (c) {
        parts.push(
          '<div class="suggestion-item" role="option" data-company-id="' +
            String(Number(c.company_id)) +
            '" tabindex="-1">',
          esc(String(c.company_name)),
          '</div>'
        );
      });
      if (needCreate) {
        parts.push(
          '<div class="suggestion-item suggestion-item--create" role="option" data-create="1" tabindex="-1">',
          'Create new company: <strong>',
          esc(t),
          '</strong>',
          '</div>'
        );
      }
      if (parts.length === 0) {
        panel.innerHTML = '<div class="suggestion-empty" role="presentation">No matches. Type a name to create below.</div>';
      } else {
        panel.innerHTML = parts.join('');
      }
    }

    function selectCompany(id, name) {
      hidden.value = String(id);
      input.value = name;
      showPanel(false);
    }

    function createCompanyAsync() {
      const name = (input.value || '').trim();
      if (name === '' || apiUrl === '' || csrf === '') {
        return;
      }
      const prev = input.value;
      input.value = 'Saving...';
      input.setAttribute('disabled', '');
      input.setAttribute('aria-busy', 'true');
      showPanel(false);

      fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ company_name: name, csrf_token: csrf }),
      })
        .then(function (r) {
          return r.text().then(function (text) {
            let data = null;
            try {
              data = text ? JSON.parse(text) : null;
            } catch (e) {
              data = null;
            }
            return { r: r, data: data };
          });
        })
        .then(function (o) {
          if (!o.data || !o.data.ok) {
            const err = (o.data && o.data.error) || 'Could not create company.';
            throw new Error(err);
          }
          const id = Number(o.data.company_id);
          const cname = (o.data.company_name && String(o.data.company_name)) || name;
          companies.push({ company_id: id, company_name: cname });
          selectCompany(id, cname);
          showCompanyToast();
        })
        .catch(function (e) {
          input.value = prev;
          window.alert((e && e.message) || 'Request failed.');
        })
        .finally(function () {
          input.removeAttribute('disabled');
          input.removeAttribute('aria-busy');
        });
    }

    input.addEventListener('input', function () {
      hidden.value = '';
      renderSuggestions();
      if (input.value.trim() !== '' || openPanel) {
        showPanel(true);
      }
    });

    input.addEventListener('focus', function () {
      renderSuggestions();
      showPanel(true);
    });

    input.addEventListener('blur', function () {
      window.setTimeout(function () {
        if (ignoreNextBlur) {
          ignoreNextBlur = false;
          return;
        }
        showPanel(false);
      }, 200);
    });

    panel.addEventListener('mousedown', function () {
      ignoreNextBlur = true;
    });

    panel.addEventListener('click', function (e) {
      const t = e.target;
      if (!t || typeof t.closest !== 'function') {
        return;
      }
      const el = t.closest('[data-company-id], [data-create]');
      if (!el) {
        return;
      }
      if (el.getAttribute('data-create') === '1') {
        createCompanyAsync();
        return;
      }
      const id = el.getAttribute('data-company-id');
      if (id) {
        const c = findCompany(id);
        const label = c ? c.company_name : el.textContent.trim();
        selectCompany(id, String(label));
      }
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        const v = (hidden.value || '').trim();
        if (!/^[1-9][0-9]*$/.test(v)) {
          e.preventDefault();
          input.setCustomValidity('Select a company or create a new one.');
          input.reportValidity();
          return;
        }
        input.setCustomValidity('');
      });
    }
  }

  comboboxEls.forEach(function (combo) {
    initCompanyCombobox(combo);
  });
})();
