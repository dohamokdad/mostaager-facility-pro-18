(function(){
    'use strict';
    function getTarget(){
        var candidates = [document.querySelector('#listings:not(a)'), document.querySelector('[data-tab="listings"]:not(a)'), document.querySelector('[data-dashboard-tab="listings"]:not(a)')];
        return candidates.find(function(el){ return el && el.nodeType === 1; });
    }
    function load(){
        var target = getTarget();
        if (!target || typeof MostaagerAjax === 'undefined') return;
        var body = new URLSearchParams({action:'ms_get_agent_properties_cards', security:MostaagerAjax.nonce});
        fetch(MostaagerAjax.ajax_url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body})
            .then(function(r){return r.json();}).then(function(json){
                if (!json.success || !json.data || !json.data.html) return;
                target.innerHTML = json.data.html;
                bindFilters(target);
            }).catch(function(err){ console.warn('Mostaager listings UI:', err); });
    }
    function bindFilters(root){
        root.querySelectorAll('[data-msfp-property-filter]').forEach(function(btn){
            btn.addEventListener('click', function(){
                var filter = btn.getAttribute('data-msfp-property-filter');
                root.querySelectorAll('[data-msfp-property-filter]').forEach(function(b){b.classList.remove('is-active');});
                btn.classList.add('is-active');
                root.querySelectorAll('[data-msfp-property-card]').forEach(function(card){
                    card.hidden = filter !== 'all' && card.getAttribute('data-property-status') !== filter;
                });
            });
        });
    }
    document.addEventListener('DOMContentLoaded', load);
    window.addEventListener('hashchange', function(){ if (location.hash === '#listings') load(); });
})();
