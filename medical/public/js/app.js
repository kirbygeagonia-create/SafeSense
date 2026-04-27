/**
 * SafeSense Hospital Management — app.js
 * Centralized AJAX, Modal state management, DataTables API, SweetAlert2
 */
(function () {
  'use strict';

  /* ──────────────────────────────────────────────
     AJAX Helper — enforces X-Requested-With header
  ────────────────────────────────────────────── */
  function ajax(url, opts = {}) {
    const defaults = {
      method: opts.method || 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
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

  /* ──────────────────────────────────────────────
     Session Flash → SweetAlert2
  ────────────────────────────────────────────── */
  const flashEl = document.getElementById('ssFlashData');
  if (flashEl) {
    const s = flashEl.dataset.success;
    const e = flashEl.dataset.error;
    if (s) Swal.fire({ icon: 'success', title: s, timer: 2500, showConfirmButton: false, toast: true, position: 'top-end' });
    if (e) Swal.fire({ icon: 'error', title: e });
    flashEl.remove();
  }

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
      columnDefs: [{ orderable: false, targets: -1 }]
    });

    const modalEl = document.getElementById(cfg.modalId);
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById(cfg.formId);
    const modalTitle = modalEl.querySelector('.modal-title');
    let editingRow = null;

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
      const id = btn.dataset.id;
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
      const id = btn.dataset.id;
      const row = btn.closest('tr');

      Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone.',
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
      const url = form.getAttribute('data-action');
      const formData = new FormData(form);
      const data = {};
      formData.forEach((v, k) => data[k] = v);

      ajaxPost(url, data)
        .then(res => {
          if (!res.success) throw res;
          modal.hide();
          const d = res.data;
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
        });
    });
  }

  /* ──────────────────────────────────────────────
     PATIENTS MODULE
  ────────────────────────────────────────────── */
  initCrudModule({
    tableId: 'patientsTable',
    modalId: 'patientModal',
    formId: 'patientForm',
    addBtnId: 'addPatientBtn',
    addTitle: 'Add Patient',
    editTitle: 'Edit Patient',
    storeUrl: '/patients/store',
    updateUrl: '/patients/update',
    deleteUrl: '/patients/delete',
    editUrl: '/patients/edit',
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
    tableId: 'doctorsTable',
    modalId: 'doctorModal',
    formId: 'doctorForm',
    addBtnId: 'addDoctorBtn',
    addTitle: 'Add Doctor',
    editTitle: 'Edit Doctor',
    storeUrl: '/doctors/store',
    updateUrl: '/doctors/update',
    deleteUrl: '/doctors/delete',
    editUrl: '/doctors/edit',
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
    // Build dropdown options from embedded data
    function buildOptions(arr, labelKey) {
      return arr.map(item => `<option value="${item.id}">${esc(item[labelKey] || item.name)}</option>`).join('');
    }

    const patientOpts = typeof PATIENTS !== 'undefined' ? buildOptions(PATIENTS, 'name') : '';
    const doctorOpts = typeof DOCTORS !== 'undefined' ? buildOptions(DOCTORS, 'name') : '';

    function populateDropdowns(form) {
      const ps = form.querySelector('[name="patient_id"]');
      const ds = form.querySelector('[name="doctor_id"]');
      if (ps && !ps.dataset.filled) { ps.innerHTML = '<option value="">Select Patient</option>' + patientOpts; ps.dataset.filled = '1'; }
      if (ds && !ds.dataset.filled) { ds.innerHTML = '<option value="">Select Doctor</option>' + doctorOpts; ds.dataset.filled = '1'; }
    }

    // Status badge helper for row rendering
    function statusBadge(s) {
      const colors = { pending: 'warning', confirmed: 'success', completed: 'secondary', cancelled: 'danger' };
      return `<span class="badge bg-${colors[s] || 'secondary'}">${s ? s.charAt(0).toUpperCase() + s.slice(1) : 'Pending'}</span>`;
    }

    // Find name by ID from embedded arrays
    function findName(arr, id) {
      if (!arr) return '—';
      const item = arr.find(i => String(i.id) === String(id));
      return item ? item.name : '—';
    }

    initCrudModule({
      tableId: 'appointmentsTable',
      modalId: 'appointmentModal',
      formId: 'appointmentForm',
      addBtnId: 'addAppointmentBtn',
      addTitle: 'Schedule Appointment',
      editTitle: 'Edit Appointment',
      storeUrl: '/appointments/store',
      updateUrl: '/appointments/update',
      deleteUrl: '/appointments/delete',
      editUrl: '/appointments/edit',
      fields: ['id', 'patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status', 'reason'],
      onOpenAdd: (form) => populateDropdowns(form),
      onOpenEdit: (form, d) => {
        populateDropdowns(form);
        form.querySelector('[name="patient_id"]').value = d.patient_id;
        form.querySelector('[name="doctor_id"]').value = d.doctor_id;
      },
      buildRow: (d) => [
        d.id,
        esc(findName(typeof PATIENTS !== 'undefined' ? PATIENTS : [], d.patient_id)),
        esc(findName(typeof DOCTORS !== 'undefined' ? DOCTORS : [], d.doctor_id)),
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
     Utility
  ────────────────────────────────────────────── */
  function esc(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
  }

})();
