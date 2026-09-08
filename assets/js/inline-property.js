(function () {
  'use strict';

  function init(form) {
    var prefix = (window.MostaagerInlineProperty && MostaagerInlineProperty.storage_prefix) || 'ms_inline_property_';
    var key = prefix + (form.dataset.userId || 'user');
    var steps = Array.prototype.slice.call(form.querySelectorAll('.ms-inline-property-step'));
    var progress = Array.prototype.slice.call(form.querySelectorAll('.ms-inline-property-progress span'));
    var current = 1;
    var timer = null;

    function fields() {
      var data = {};
      Array.prototype.forEach.call(form.querySelectorAll('[name]'), function (input) {
        if (input.name === 'action' || input.name === 'security' || input.name === 'submit_mode') return;
        data[input.name] = input.value;
      });
      return data;
    }

    function showStatus(message, error) {
      var box = form.querySelector('[data-status]');
      if (box) {
        box.textContent = message || '';
        box.classList.toggle('is-error', !!error);
      }
    }

    function populateBuildingPeople() {
      var source = form.querySelector('[data-building-people]');
      var people = [];
      try { people = JSON.parse(source ? source.textContent : '[]') || []; } catch (e) { people = []; }
      var building = form.querySelector('[name="building_id"]');
      var buildingId = building ? String(building.value || '0') : '0';
      ['owner_id', 'tenant_id', 'buyer_id'].forEach(function (name) {
        var select = form.querySelector('[name="' + name + '"]');
        if (!select) return;
        var previous = select.value;
        var type = name === 'owner_id' ? 'owner' : 'tenant';
        select.innerHTML = '<option value="0">' + (buildingId === '0' ? 'اختر المبنى أولًا' : 'لا يوجد شخص مرتبط بهذا المبنى') + '</option>';
        var matches = people.filter(function (item) { return String(item.building_id) === buildingId && (type === 'owner' ? item.person_type === 'owner' : item.person_type === 'tenant'); });
        matches.forEach(function (item) {
          var option = document.createElement('option'); option.value = item.user_id; option.textContent = item.label; select.appendChild(option);
        });
        if (Array.prototype.some.call(select.options || [], function (option) { return option.value === previous; })) select.value = previous;
      });
    }

    function renderStep(step) {
      current = Math.max(1, Math.min(3, step));
      steps.forEach(function (node) { node.classList.toggle('is-active', Number(node.dataset.step) === current); });
      progress.forEach(function (node, index) { node.classList.toggle('is-active', index + 1 === current); node.classList.toggle('is-complete', index + 1 < current); });
      if (current === 3) {
        var review = form.querySelector('[data-review]');
        if (review) {
          var data = fields();
          var building = form.querySelector('[name="building_id"] option:checked');
          var status = form.querySelector('[name="listing_status"] option:checked');
          var owner = form.querySelector('[name="owner_id"] option:checked');
          var tenant = form.querySelector('[name="tenant_id"] option:checked');
          var buyer = form.querySelector('[name="buyer_id"] option:checked');
          review.innerHTML = '<strong>' + (data.title || 'بدون اسم') + '</strong><span>المبنى: ' + ((building && building.textContent) || 'غير محدد') + ' — الحالة: ' + ((status && status.textContent) || 'غير محددة') + '</span><span>' + (data.property_type || 'نوع غير محدد') + ' — ' + (data.unit_number || 'بدون رقم وحدة') + '</span><span>المالك: ' + ((owner && owner.value !== '0' && owner.textContent) || 'غير محدد') + ' | المستأجر: ' + ((tenant && tenant.value !== '0' && tenant.textContent) || 'غير محدد') + ' | المشتري: ' + ((buyer && buyer.value !== '0' && buyer.textContent) || 'غير محدد') + '</span><span>السعر: ' + (data.price || 'غير محدد') + ' | المساحة: ' + (data.size || 'غير محددة') + '</span>';
        }
      }
    }

    function saveLocal() {
      try { localStorage.setItem(key, JSON.stringify(fields())); } catch (e) {}
    }

    function restoreLocal() {
      try {
        var saved = JSON.parse(localStorage.getItem(key) || '{}');
        Object.keys(saved).forEach(function (name) { var input = form.querySelector('[name="' + name + '"]'); if (input && !input.value) input.value = saved[name]; });
      } catch (e) {}
    }

    function saveServer(mode) {
      var body = new FormData(form);
      body.set('submit_mode', mode || 'draft');
      var button = form.querySelector(mode === 'submit' ? '[type="submit"]' : '.ms-inline-draft');
      if (button) button.disabled = true;
      showStatus(mode === 'submit' ? 'جارٍ إرسال العقار للمراجعة...' : 'جارٍ حفظ المسودة...');
      fetch((window.MostaagerAjax && MostaagerAjax.ajax_url) || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: body })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result.success) throw new Error((result.data && result.data.message) || 'تعذر الحفظ.');
          var data = result.data || {};
          form.querySelector('[name="property_id"]').value = data.property_id || 0;
          showStatus(data.message || 'تم الحفظ بنجاح.');
          try { localStorage.removeItem(key); } catch (e) {}
        })
        .catch(function (error) { showStatus(error.message, true); })
        .finally(function () { if (button) button.disabled = false; });
    }

    var buildingSelect = form.querySelector('[name="building_id"]');
    if (buildingSelect) buildingSelect.addEventListener('change', populateBuildingPeople);
    restoreLocal();
    populateBuildingPeople();
    renderStep(1);
    form.addEventListener('input', function () {
      saveLocal();
      clearTimeout(timer);
      timer = setTimeout(function () { if (form.querySelector('[name="title"]').value.trim()) saveServer('draft'); }, 1200);
    });
    form.addEventListener('click', function (event) {
      if (event.target.closest('.ms-inline-next')) { event.preventDefault(); if (current === 1 && !form.querySelector('[name="title"]').value.trim()) { showStatus('اكتب اسم العقار أولًا.', true); return; } if (current === 1 && (!form.querySelector('[name="building_id"]') || form.querySelector('[name="building_id"]').value === '0')) { showStatus('اختر المبنى أولًا.', true); return; } renderStep(current + 1); }
      if (event.target.closest('.ms-inline-prev')) { event.preventDefault(); renderStep(current - 1); }
      if (event.target.closest('.ms-inline-draft')) { event.preventDefault(); saveServer('draft'); }
    });
    form.addEventListener('submit', function (event) { event.preventDefault(); if (!form.querySelector('[name="title"]').value.trim()) { renderStep(1); showStatus('اسم العقار مطلوب.', true); return; } saveServer('submit'); });
  }

  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.ms-inline-property-form').forEach(init); });
}());
