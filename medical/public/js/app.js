/**
 * SafeSense Hospital Management — app.js
 * Centralized AJAX, Modal state management, DataTables API, SweetAlert2
 */
(function () {
  'use strict';

  /* ──────────────────────────────────────────────
     AJAX Helper — enforces X-Requested-With + CSRF
  ────────────────────────────────────────────── */
  function ajax(url, opts = {}) {
    // Task 2 — inject CSRF token into every AJAX request
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const defaults = {
      method: opts.method || 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken,
        ...(opts.headers || {})
      }
    };
    if (opts.body) defaults.body = opts.body;
    return fetch(url, defaults).then(r => {
      if (!r.ok) return r.json().then(d => Promise.reject(d));
      return r.json();
    });
  }

  function ajaxPost(url, formData) {
    return ajax(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(formData).toString()
    });
  }
  
  window.ajax = ajax;
  window.ajaxPost = ajaxPost;

  /* ──────────────────────────────────────────────
     Shared Utilities — hoisted to prevent re-declaration
  ────────────────────────────────────────────── */
  function esc(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
  }

  function buildOptions(arr, labelKey) {
    return arr.map(item => `<option value="${item.id}">${esc(item[labelKey] || item.name)}</option>`).join('');
  }

  function findName(arr, id) {
    if (!arr) return '—';
    const item = arr.find(i => String(i.id) === String(id));
    return item ? item.name : '—';
  }

  /* ──────────────────────────────────────────────
     Fix DataTables "Show N entries" select overlap
  ────────────────────────────────────────────── */
  function fixDtLengthSelect() {
    document.querySelectorAll('.dataTables_length select').forEach(sel => {
      sel.style.setProperty('min-width',     '76px',  'important');
      sel.style.setProperty('padding-right', '1.8rem','important');
      sel.style.setProperty('width',         'auto',  'important');
    });
  }
  setTimeout(fixDtLengthSelect, 400);


  const flashEl = document.getElementById('ssFlashData');
  if (flashEl) {
    const s = flashEl.dataset.success;
    const e = flashEl.dataset.error;
    if (s) Swal.fire({ icon: 'success', title: s, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
    if (e) Swal.fire({ icon: 'error', title: e });
    flashEl.remove();
  }

  /* ──────────────────────────────────────────────
     Logout Confirmation
  ────────────────────────────────────────────── */
  document.querySelectorAll('form[action*="/logout"]').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      Swal.fire({
        title: 'Log out?',
        text: 'You will be returned to the login screen.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, log out',
        cancelButtonText: 'Cancel',
        reverseButtons: true
      }).then(result => {
        if (result.isConfirmed) form.submit();
      });
    });
  });


  /* ──────────────────────────────────────────────
     Generic CRUD Modal System
  ────────────────────────────────────────────── */
  function initCrudModule(cfg) {
    const tableEl = document.getElementById(cfg.tableId);
    if (!tableEl) return;

    // Init DataTables
    const dt = new DataTable('#' + cfg.tableId, {
      pageLength: 10,
      order: [[0, 'desc']],
      language: { search: '', searchPlaceholder: 'Search...' },
      columnDefs: [{ orderable: false, targets: -1 }],
      dom: '<"dt-header"lf>rtip',
      initComplete: function() {
        const lengthSel = tableEl.closest('.dataTables_wrapper')
          ?.querySelector('.dataTables_length select');
        if (lengthSel) {
          lengthSel.style.setProperty('min-width', '80px', 'important');
          lengthSel.style.setProperty('padding-right', '2rem', 'important');
          lengthSel.style.setProperty('width', 'auto', 'important');
        }
      }
    });

    const modalEl    = document.getElementById(cfg.modalId);
    const modal      = new bootstrap.Modal(modalEl);
    const form       = document.getElementById(cfg.formId);
    const modalTitle = modalEl.querySelector('.modal-title');
    let editingRow   = null;

    // ADD button
    const addBtn = document.getElementById(cfg.addBtnId);
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        form.reset();
        if (form.querySelector('[name="id"]')) form.querySelector('[name="id"]').value = '';
        modalTitle.textContent = cfg.addTitle;
        form.setAttribute('data-action', cfg.storeUrl);
        editingRow = null;
        if (cfg.onOpenAdd) cfg.onOpenAdd(form);
        modal.show();
      });
    }

    // EDIT buttons (delegated)
    tableEl.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-edit');
      if (!btn) return;
      const id  = btn.dataset.id;
      const row = btn.closest('tr');

      ajax(cfg.editUrl + '?id=' + id)
        .then(res => {
          if (!res.success) throw res;
          const d = res.data;
          cfg.fields.forEach(f => {
            const input = form.querySelector('[name="' + f + '"]');
            if (input) input.value = d[f] || '';
          });
          form.querySelector('[name="id"]').value = d.id;
          modalTitle.textContent = cfg.editTitle;
          form.setAttribute('data-action', cfg.updateUrl);
          editingRow = dt.row(row);
          if (cfg.onOpenEdit) cfg.onOpenEdit(form, d);
          modal.show();
        })
        .catch(err => Swal.fire({ icon: 'error', title: err.message || 'Failed to load record' }));
    });

    // DELETE buttons (delegated)
    tableEl.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-delete');
      if (!btn) return;
      const id  = btn.dataset.id;
      const row = btn.closest('tr');

      // Task 5 — cascade delete text is passed per-module via cfg.deleteText
      Swal.fire({
        title: 'Are you sure?',
        text: cfg.deleteText || 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete it'
      }).then(result => {
        if (!result.isConfirmed) return;
        ajaxPost(cfg.deleteUrl, { id })
          .then(res => {
            if (!res.success) throw res;
            dt.row(row).remove().draw();
            Swal.fire({ icon: 'success', title: res.message, timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
          })
          .catch(err => Swal.fire({ icon: 'error', title: err.message || 'Delete failed' }));
      });
    });

    // FORM SUBMIT
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const url       = form.getAttribute('data-action');
      const formData  = new FormData(form);
      const data      = {};
      formData.forEach((v, k) => data[k] = v);
      const submitBtn = form.querySelector('[type="submit"]');
      const originalBtn = submitBtn ? submitBtn.innerHTML : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
      }

      ajaxPost(url, data)
        .then(res => {
          if (!res.success) throw res;
          modal.hide();
          const d       = res.data;
          const rowData = cfg.buildRow(d);

          if (editingRow) {
            editingRow.data(rowData).draw();
          } else {
            dt.row.add(rowData).draw();
          }
          Swal.fire({ icon: 'success', title: res.message, timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
        })
        .catch(err => {
          // Modal stays open on error
          Swal.fire({ icon: 'error', title: err.message || 'Operation failed' });
        })
        .finally(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtn;
          }
        });
    });
  }

  /* ──────────────────────────────────────────────
     PATIENTS MODULE
  ────────────────────────────────────────────── */
  initCrudModule({
    tableId:    'patientsTable',
    modalId:    'patientModal',
    formId:     'patientForm',
    addBtnId:   'addPatientBtn',
    addTitle:   'Add Patient',
    editTitle:  'Edit Patient',
    storeUrl:   window.BASE_URL + '/patients/store',
    updateUrl:  window.BASE_URL + '/patients/update',
    deleteUrl:  window.BASE_URL + '/patients/delete',
    editUrl:    window.BASE_URL + '/patients/edit',
    // Task 5 — cascade delete warning for patients
    deleteText: 'This will also permanently delete all appointments for this patient.',
    fields: ['id', 'name', 'email', 'phone', 'date_of_birth', 'gender', 'address'],
    buildRow: (d) => [
      d.id,
      esc(d.name),
      esc(d.email),
      esc(d.phone),
      esc(d.date_of_birth),
      esc(d.gender),
      `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
      `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
    ]
  });

  /* ──────────────────────────────────────────────
     DOCTORS MODULE
  ────────────────────────────────────────────── */
  initCrudModule({
    tableId:   'doctorsTable',
    modalId:   'doctorModal',
    formId:    'doctorForm',
    addBtnId:  'addDoctorBtn',
    addTitle:  'Add Doctor',
    editTitle: 'Edit Doctor',
    storeUrl:  window.BASE_URL + '/doctors/store',
    updateUrl: window.BASE_URL + '/doctors/update',
    deleteUrl: window.BASE_URL + '/doctors/delete',
    editUrl:   window.BASE_URL + '/doctors/edit',
    fields: ['id', 'name', 'email', 'phone', 'specialization', 'license_number'],
    buildRow: (d) => [
      d.id,
      esc(d.name),
      esc(d.email),
      esc(d.phone),
      esc(d.specialization),
      esc(d.license_number),
      `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
      `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
    ]
  });

  /* ──────────────────────────────────────────────
     APPOINTMENTS MODULE
  ────────────────────────────────────────────── */
  const apptEl = document.getElementById('appointmentsTable');
  if (apptEl) {
    const patientOpts = typeof PATIENTS !== 'undefined' ? buildOptions(PATIENTS, 'name') : '';
    const doctorOpts  = typeof DOCTORS  !== 'undefined' ? buildOptions(DOCTORS,  'name') : '';

    function populateDropdowns(form) {
      const ps = form.querySelector('[name="patient_id"]');
      const ds = form.querySelector('[name="doctor_id"]');
      if (ps && !ps.dataset.filled) { ps.innerHTML = '<option value="">Select Patient</option>' + patientOpts; ps.dataset.filled = '1'; }
      if (ds && !ds.dataset.filled) { ds.innerHTML = '<option value="">Select Doctor</option>'  + doctorOpts;  ds.dataset.filled = '1'; }
    }

    function statusBadge(s) {
      const colors = { pending: 'warning', confirmed: 'success', completed: 'secondary', cancelled: 'danger' };
      return `<span class="badge bg-${colors[s] || 'secondary'}">${s ? s.charAt(0).toUpperCase() + s.slice(1) : 'Pending'}</span>`;
    }

    initCrudModule({
      tableId:   'appointmentsTable',
      modalId:   'appointmentModal',
      formId:    'appointmentForm',
      addBtnId:  'addAppointmentBtn',
      addTitle:  'Schedule Appointment',
      editTitle: 'Edit Appointment',
      storeUrl:  window.BASE_URL + '/appointments/store',
      updateUrl: window.BASE_URL + '/appointments/update',
      deleteUrl: window.BASE_URL + '/appointments/delete',
      editUrl:   window.BASE_URL + '/appointments/edit',
      fields: ['id', 'patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status', 'reason'],
      onOpenAdd:  (form)    => populateDropdowns(form),
      onOpenEdit: (form, d) => {
        populateDropdowns(form);
        form.querySelector('[name="patient_id"]').value = d.patient_id;
        form.querySelector('[name="doctor_id"]').value  = d.doctor_id;
      },
      buildRow: (d) => [
        d.id,
        esc(findName(typeof PATIENTS !== 'undefined' ? PATIENTS : [], d.patient_id)),
        esc(findName(typeof DOCTORS  !== 'undefined' ? DOCTORS  : [], d.doctor_id)),
        esc(d.appointment_date),
        esc(d.appointment_time),
        statusBadge(d.status),
        esc(d.reason || ''),
        `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
        `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
      ]
    });
  }

  /* ──────────────────────────────────────────────
     USERS MODULE
  ────────────────────────────────────────────── */
  initCrudModule({
    tableId:   'usersTable',
    modalId:   'userModal',
    formId:    'userForm',
    addBtnId:  'addUserBtn',
    addTitle:  'Add User',
    editTitle: 'Edit User',
    storeUrl:  window.BASE_URL + '/users/store',
    updateUrl: window.BASE_URL + '/users/update',
    deleteUrl: window.BASE_URL + '/users/delete',
    editUrl:   window.BASE_URL + '/users/edit',
    fields: ['id', 'name', 'email', 'role', 'password'],
    onOpenEdit: (form, d) => {
      const pw = form.querySelector('[name="password"]');
      if (pw) { pw.value = ''; pw.placeholder = 'Leave blank to keep current'; }
      const hint = document.getElementById('pwHint');
      if (hint) hint.textContent = 'Leave blank to keep existing password';
    },
    buildRow: (d) => [
      d.id,
      esc(d.name),
      esc(d.email),
      esc(d.role ? d.role.charAt(0).toUpperCase() + d.role.slice(1) : ''),
      esc(d.created_at ? d.created_at.slice(0, 10) : ''),
      `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
      `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
    ]
  });

  /* ──────────────────────────────────────────────
     EMR MODULE
  ────────────────────────────────────────────── */
  const emrEl = document.getElementById('emrTable');
  if (emrEl) {
    const emrPatientOpts = typeof PATIENTS !== 'undefined' ? buildOptions(PATIENTS, 'name') : '';
    const emrDoctorOpts  = typeof DOCTORS  !== 'undefined' ? buildOptions(DOCTORS,  'name') : '';

    function populateEmrDropdowns(form) {
      const ps = form.querySelector('[name="patient_id"]');
      const ds = form.querySelector('[name="doctor_id"]');
      if (ps && !ps.dataset.filled) { ps.innerHTML = '<option value="">Select Patient</option>' + emrPatientOpts; ps.dataset.filled = '1'; }
      if (ds && !ds.dataset.filled) { ds.innerHTML = '<option value="">Select Doctor</option>'  + emrDoctorOpts;  ds.dataset.filled = '1'; }
    }

    initCrudModule({
      tableId:   'emrTable',
      modalId:   'emrModal',
      formId:    'emrForm',
      addBtnId:  'addEmrBtn',
      addTitle:  'Add Medical Record',
      editTitle: 'Edit Medical Record',
      storeUrl:  window.BASE_URL + '/emr/store',
      updateUrl: window.BASE_URL + '/emr/update',
      deleteUrl: window.BASE_URL + '/emr/delete',
      editUrl:   window.BASE_URL + '/emr/edit',
      fields: ['id','patient_id','doctor_id','visit_date','chief_complaint','diagnosis','prescription','notes','blood_pressure','temperature','heart_rate','weight'],
      onOpenAdd:  (form)    => populateEmrDropdowns(form),
      onOpenEdit: (form, d) => {
        populateEmrDropdowns(form);
        form.querySelector('[name="patient_id"]').value = d.patient_id;
        form.querySelector('[name="doctor_id"]').value  = d.doctor_id;
      },
      buildRow: (d) => [
        d.id,
        esc(findName(typeof PATIENTS !== 'undefined' ? PATIENTS : [], d.patient_id)),
        esc(findName(typeof DOCTORS  !== 'undefined' ? DOCTORS  : [], d.doctor_id)),
        esc(d.visit_date),
        esc(d.diagnosis),
        esc(d.blood_pressure || '—'),
        `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
        `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
      ]
    });
  }

  /* ──────────────────────────────────────────────
     BILLING MODULE
  ────────────────────────────────────────────── */
  const billEl = document.getElementById('billingTable');
  if (billEl) {
    const billPatientOpts = typeof PATIENTS !== 'undefined' ? buildOptions(PATIENTS, 'name') : '';

    function populateBillDropdown(form) {
      const ps = form.querySelector('[name="patient_id"]');
      if (ps && !ps.dataset.filled) { ps.innerHTML = '<option value="">Select Patient</option>' + billPatientOpts; ps.dataset.filled = '1'; }
    }

    function paymentStatusBadge(s) {
      const colors = { unpaid: 'danger', paid: 'success', partial: 'warning', cancelled: 'secondary' };
      return `<span class="badge bg-${colors[s] || 'secondary'}">${s ? s.charAt(0).toUpperCase() + s.slice(1) : 'Unpaid'}</span>`;
    }

    initCrudModule({
      tableId:   'billingTable',
      modalId:   'billingModal',
      formId:    'billingForm',
      addBtnId:  'addBillingBtn',
      addTitle:  'Create Invoice',
      editTitle: 'Edit Invoice',
      storeUrl:  window.BASE_URL + '/billing/store',
      updateUrl: window.BASE_URL + '/billing/update',
      deleteUrl: window.BASE_URL + '/billing/delete',
      editUrl:   window.BASE_URL + '/billing/edit',
      fields: ['id','patient_id','appointment_id','service_description','amount','discount','tax','payment_status','payment_method','payment_date','notes'],
      onOpenAdd:  (form)    => populateBillDropdown(form),
      onOpenEdit: (form, d) => {
        populateBillDropdown(form);
        form.querySelector('[name="patient_id"]').value = d.patient_id;
      },
      buildRow: (d) => [
        esc(d.invoice_number),
        esc(findName(typeof PATIENTS !== 'undefined' ? PATIENTS : [], d.patient_id)),
        Number(d.amount || 0).toFixed(2),
        Number(d.discount || 0).toFixed(2),
        Number(d.tax || 0).toFixed(2),
        Number(d.total_amount || 0).toFixed(2),
        paymentStatusBadge(d.payment_status),
        esc(d.payment_method || '—'),
        esc(d.payment_date || '—'),
        `<button class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${d.id}"><i class="fas fa-edit"></i></button>` +
        `<button class="btn btn-sm btn-outline-danger btn-delete" data-id="${d.id}"><i class="fas fa-trash"></i></button>`
      ]
    });
  }

})();
