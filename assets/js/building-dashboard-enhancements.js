(function(){'use strict';
function qs(s,r){return (r||document).querySelector(s)}
function addQuickActions(){
 var content=qs('.ms-dashboard .ms-content');
 if(!content||qs('.ms-building-quick-actions',content))return;
 var selector=qs('#ms-building-selector');
 var id=selector&&selector.value?selector.value:'';
 var base=window.location.href.split('#')[0];
 var wrap=document.createElement('nav');
 wrap.className='ms-building-quick-actions';
 wrap.setAttribute('aria-label','إجراءات سريعة');
 [['#maintenance','🛠️ الصيانة','ms-primary'],['#invoices','🧾 الفواتير',''],['#discussions','💬 المناقشات','']].forEach(function(item){
  var a=document.createElement('a');a.href=base+(id?'?building_id='+encodeURIComponent(id):'')+item[0];a.className=item[2];a.textContent=item[1];wrap.appendChild(a);
 });
 var first=content.firstElementChild;
 if(first)content.insertBefore(wrap,first);else content.appendChild(wrap);
}
function addDataWarning(){
 var overview=qs('#overview');
 if(!overview||qs('.ms-building-data-warning',overview))return;
 var units=qs('#ms-selected-building-units-count');
 var maintenance=qs('#ms-active-maintenance');
 if(units&&maintenance&&Number(units.textContent||0)===0&&Number(maintenance.textContent||0)>0){
  var el=document.createElement('div');el.className='ms-building-data-warning';el.setAttribute('role','status');el.textContent='تنبيه: توجد طلبات صيانة لهذا المبنى، لكن لا توجد وحدات مرتبطة به. راجع ربط عقارات Houzez بالمبنى قبل إنشاء فواتير جديدة.';
  overview.insertBefore(el,overview.firstElementChild);
 }
}
function bindFacilityFilters(){
 document.querySelectorAll('.ms-facility-filter').forEach(function(btn){if(btn.dataset.bound)return;btn.dataset.bound='1';btn.addEventListener('click',function(){var status=btn.dataset.facilityStatus;document.querySelectorAll('.ms-facility-filter').forEach(function(b){b.style.background=b===btn?'#2563eb':'#fff';b.style.color=b===btn?'#fff':'#334155';b.classList.toggle('active',b===btn)});document.querySelectorAll('.ms-facility-row').forEach(function(row){row.style.display=status==='all'||row.dataset.facilityStatus===status?'':'none'})})})
}
function markActive(){
 var hash=(location.hash||'#overview').replace('#','').split('&')[0]||'overview';
 document.querySelectorAll('.ms-dashboard .ms-tab-content').forEach(function(el){el.dataset.section=el.id===hash?'active':'inactive'});
}
function refresh(){addQuickActions();addDataWarning();bindFacilityFilters();markActive()}
document.addEventListener('DOMContentLoaded',refresh);
window.addEventListener('hashchange',function(){markActive();setTimeout(addDataWarning,50)});
document.addEventListener('mostaager:building_changed',function(){setTimeout(function(){addDataWarning();},100)});
})();
