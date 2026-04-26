/**
 * Admin manage users: tab panels, collapsible add forms, CSV bulk import (students + assessors).
 * (Success toasts: auto-dismiss is in app.js for all .admin-messages [data-auto-dismiss].)
 */
(function () {
  'use strict';

  const IMPORT_TOAST_KEY = 'admin_manage_users_import_toast';

  function showImportSuccessFromStorage() {
    var msg = sessionStorage.getItem(IMPORT_TOAST_KEY);
    if (!msg) {
      return;
    }
    sessionStorage.removeItem(IMPORT_TOAST_KEY);
    var main = document.querySelector('main.dashboard-main');
    var header = main && main.querySelector('.dashboard-page-header');
    if (!main || !header) {
      return;
    }
    var host = document.getElementById('admin-messages');
    if (!host) {
      host = document.createElement('div');
      host.id = 'admin-messages';
      host.className = 'admin-messages';
      host.setAttribute('aria-live', 'polite');
      header.insertAdjacentElement('afterend', host);
    }
    var toast = document.createElement('div');
    toast.className = 'admin-toast admin-toast--success';
    toast.setAttribute('role', 'status');
    toast.setAttribute('data-auto-dismiss', 'true');
    var span = document.createElement('span');
    span.className = 'admin-toast__text';
    span.textContent = msg;
    toast.appendChild(span);
    host.appendChild(toast);
  }

  showImportSuccessFromStorage();

  var root = document.getElementById('admin-manage-users');
  if (!root) {
    return;
  }

  var tabStudents = document.getElementById('tab-students');
  var tabAssessors = document.getElementById('tab-assessors');
  var panelStudents = document.getElementById('panel-students');
  var panelAssessors = document.getElementById('panel-assessors');
  if (!tabStudents || !tabAssessors || !panelStudents || !panelAssessors) {
    return;
  }

  var initial = root.getAttribute('data-initial-tab') === 'assessors' ? 'assessors' : 'students';

  /**
   * @param {'students'|'assessors'} which
   * @param {boolean} pushUrl
   */
  function setTabActive(which, pushUrl) {
    var showStudents = which === 'students';
    tabStudents.classList.toggle('is-active', showStudents);
    tabAssessors.classList.toggle('is-active', !showStudents);
    tabStudents.setAttribute('aria-selected', showStudents ? 'true' : 'false');
    tabAssessors.setAttribute('aria-selected', !showStudents ? 'true' : 'false');
    tabStudents.tabIndex = showStudents ? 0 : -1;
    tabAssessors.tabIndex = !showStudents ? 0 : -1;
    panelStudents.toggleAttribute('hidden', !showStudents);
    panelAssessors.toggleAttribute('hidden', showStudents);

    if (pushUrl) {
      try {
        var u = new URL(window.location.href, window.location.origin);
        u.searchParams.set('tab', showStudents ? 'students' : 'assessors');
        window.history.pushState({}, '', u);
      } catch (e) {
        /* ignore */
      }
    }
  }

  setTabActive(initial, false);

  tabStudents.addEventListener('click', function () {
    setTabActive('students', true);
  });
  tabAssessors.addEventListener('click', function () {
    setTabActive('assessors', true);
  });

  /** @param {string} btnId @param {string} wrapId */
  function wireCollapse(btnId, wrapId) {
    var btn = document.getElementById(btnId);
    var wrap = document.getElementById(wrapId);
    if (!btn || !wrap) {
      return;
    }
    btn.addEventListener('click', function () {
      var open = !wrap.classList.contains('is-open');
      wrap.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      wrap.setAttribute('aria-hidden', open ? 'false' : 'true');
    });
  }

  wireCollapse('btn-toggle-add-student', 'admin-add-student-wrap');
  wireCollapse('btn-toggle-add-assessor', 'admin-add-assessor-wrap');

  /* ——— CSV import modal (students + assessors) ——— */
  var modal = document.getElementById('csvImportModal');
  var btnOpenStudent = document.getElementById('btnOpenImport');
  var btnOpenAssessor = document.getElementById('btnOpenImportAssessors');
  var dropZone = document.getElementById('csvDropZone');
  var fileInput = document.getElementById('csvFileInput');
  var dropText = document.getElementById('csvDropText');
  var dropLoading = document.getElementById('csvDropLoading');
  var errEl = document.getElementById('csvImportError');
  var titleEl = document.getElementById('csv-import-title');
  var templateLink = document.getElementById('csvTemplateLink');
  var importApiStudent = root.getAttribute('data-import-api') || '';
  var importApiAssessor = root.getAttribute('data-assessor-import-api') || '';
  var importCsrf = root.getAttribute('data-csrf') || '';
  var importMode = 'student';
  var activeApi = importApiStudent;
  if (templateLink) {
    var t0 = root.getAttribute('data-template-csv');
    if (t0) {
      templateLink.setAttribute('href', t0);
    }
  }

  if (!modal || !dropZone || !fileInput || !dropText || !dropLoading || !errEl || !titleEl) {
    return;
  }
  if (!importApiStudent || !importApiAssessor) {
    return;
  }
  if (!btnOpenStudent && !btnOpenAssessor) {
    return;
  }

  var dragCount = 0;
  var prevBodyOverflow = '';

  function setError(text) {
    if (text) {
      errEl.textContent = text;
      errEl.removeAttribute('hidden');
    } else {
      errEl.setAttribute('hidden', '');
    }
  }

  function setUploading(on) {
    if (on) {
      dropZone.classList.add('is-uploading');
      dropText.setAttribute('hidden', '');
      dropLoading.removeAttribute('hidden');
    } else {
      dropZone.classList.remove('is-uploading');
      dropText.removeAttribute('hidden');
      dropLoading.setAttribute('hidden', '');
    }
  }

  function resetDropVisual() {
    dropZone.classList.remove('is-dragover');
    dragCount = 0;
  }

  function showModalShell() {
    setError('');
    setUploading(false);
    resetDropVisual();
    fileInput.value = '';
    modal.removeAttribute('hidden');
    prevBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }

  function applyImportMode(mode) {
    importMode = mode;
    activeApi = mode === 'assessor' ? importApiAssessor : importApiStudent;
    if (mode === 'assessor') {
      titleEl.textContent = 'Bulk Import Assessors';
      if (templateLink) {
        var h = root.getAttribute('data-template-csv-assessors') || '';
        if (h) {
          templateLink.setAttribute('href', h);
        }
      }
      dropZone.setAttribute(
        'aria-label',
        'Drop zone: drag an assessor CSV file here or press Enter to browse'
      );
    } else {
      titleEl.textContent = 'Bulk Import Students';
      if (templateLink) {
        var hs = root.getAttribute('data-template-csv') || '';
        if (hs) {
          templateLink.setAttribute('href', hs);
        }
      }
      dropZone.setAttribute(
        'aria-label',
        'Drop zone: drag a student CSV file here or press Enter to browse'
      );
    }
  }

  function openModalFor(mode) {
    applyImportMode(mode === 'assessor' ? 'assessor' : 'student');
    showModalShell();
  }

  function closeModal() {
    if (dropZone.classList.contains('is-uploading')) {
      return;
    }
    modal.setAttribute('hidden', '');
    document.body.style.overflow = prevBodyOverflow;
  }

  if (btnOpenStudent) {
    btnOpenStudent.addEventListener('click', function () {
      openModalFor('student');
    });
  }
  if (btnOpenAssessor) {
    btnOpenAssessor.addEventListener('click', function () {
      openModalFor('assessor');
    });
  }

  modal.addEventListener('click', function (e) {
    var t = e.target;
    if (t && t.getAttribute && t.getAttribute('data-csv-modal-dismiss') !== null) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
      closeModal();
    }
  });

  dropZone.addEventListener('click', function () {
    if (!dropZone.classList.contains('is-uploading')) {
      fileInput.click();
    }
  });

  dropZone.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (!dropZone.classList.contains('is-uploading')) {
        fileInput.click();
      }
    }
  });

  dropZone.addEventListener('dragenter', function (e) {
    e.preventDefault();
    dragCount += 1;
    dropZone.classList.add('is-dragover');
  });

  dropZone.addEventListener('dragleave', function (e) {
    e.preventDefault();
    dragCount -= 1;
    if (dragCount <= 0) {
      dragCount = 0;
      dropZone.classList.remove('is-dragover');
    }
  });

  dropZone.addEventListener('dragover', function (e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
  });

  dropZone.addEventListener('drop', function (e) {
    e.preventDefault();
    resetDropVisual();
    var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) {
      uploadFile(f);
    }
  });

  fileInput.addEventListener('change', function () {
    var f = fileInput.files && fileInput.files[0];
    if (f) {
      uploadFile(f);
    }
  });

  function uploadFile(file) {
    setError('');
    if (importCsrf === '') {
      setError('Session token missing. Refresh the page and try again.');
      return;
    }
    if (!activeApi) {
      setError('Import endpoint is not configured.');
      return;
    }
    setUploading(true);

    var fd = new FormData();
    fd.append('csrf_token', importCsrf);
    fd.append('csv', file);

    fetch(activeApi, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = null;
          try {
            data = text ? JSON.parse(text) : null;
          } catch (ex) {
            data = null;
          }
          return { res: res, data: data };
        });
      })
      .then(function (o) {
        if (!o.data) {
          throw new Error('The server did not return JSON.');
        }
        if (!o.res.ok || !o.data.ok) {
          var err = o.data && o.data.error ? o.data.error : 'Import failed.';
          throw new Error(err);
        }
        var message = o.data.message || 'Import completed.';
        sessionStorage.setItem(IMPORT_TOAST_KEY, message);
        if (importMode === 'assessor') {
          var u2 = new URL(window.location.href, window.location.origin);
          u2.searchParams.set('tab', 'assessors');
          u2.searchParams.set('ap', '1');
          window.location.href = u2.toString();
        } else {
          window.location.reload();
        }
      })
      .catch(function (err) {
        setUploading(false);
        setError(err && err.message ? err.message : 'Import failed.');
      });
  }
})();
