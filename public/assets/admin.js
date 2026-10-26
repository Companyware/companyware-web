(function(){'use strict';window.AdminFramework=window.AdminFramework||{version:'1.0.0',hooks:new Map(),on:function(name,callback){const list=this.hooks.get(name)||[];list.push(callback);this.hooks.set(name,list)},emit:function(name,detail){(this.hooks.get(name)||[]).forEach(function(callback){callback(detail)})}};document.addEventListener('click',function(event){const menu=event.target.closest('[data-admin-menu]');if(menu){document.body.classList.toggle('menu-open');return}const toggle=event.target.closest('[data-password-toggle]');if(toggle){const input=document.getElementById(toggle.dataset.passwordToggle);if(input)input.type=input.type==='password'?'text':'password'}});document.addEventListener('DOMContentLoaded',function(){const groups=document.querySelectorAll('[data-nav-accordion]');groups.forEach(function(group){group.addEventListener('toggle',function(){if(!group.open)return;groups.forEach(function(other){if(other!==group)other.open=false})})});window.AdminFramework.emit('ready',{document:document})})})();

(function () {
    'use strict';

    function initializeDashboard() {
        const dashboard = document.querySelector('[data-dashboard]');
        if (!dashboard) return;

        const owner = dashboard.dataset.dashboardOwner || 'anonymous';
        const storageKey = 'companyware.dashboard.' + owner + '.v1';
        let state = {order: {}, hidden: [], sizes: {}};

        try {
            const stored = window.localStorage.getItem(storageKey);
            if (stored) state = Object.assign(state, JSON.parse(stored));
        } catch (error) {
            window.AdminFramework.emit('dashboard:storage-error', {error: error});
        }

        const save = function () {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (error) {
                window.AdminFramework.emit('dashboard:storage-error', {error: error});
            }
        };
        const zones = Array.from(dashboard.querySelectorAll('[data-dashboard-zone]'));
        const widgets = function () { return Array.from(dashboard.querySelectorAll('[data-dashboard-widget]')); };

        zones.forEach(function (zone) {
            const orderedIds = Array.isArray(state.order[zone.dataset.dashboardZone]) ? state.order[zone.dataset.dashboardZone] : [];
            orderedIds.forEach(function (id) {
                const widget = Array.from(zone.children).find(function (child) {
                    return child.dataset && child.dataset.dashboardWidget === id;
                });
                if (widget) zone.appendChild(widget);
            });
        });

        widgets().forEach(function (widget) {
            const id = widget.dataset.dashboardWidget;
            widget.hidden = state.hidden.includes(id);
            widget.dataset.dashboardSize = state.sizes[id] === 'wide' ? 'wide' : (widget.dataset.dashboardSize || 'normal');

            const controls = document.createElement('div');
            controls.className = 'dashboard-widget-controls';
            [
                ['before', '\u2190', dashboard.dataset.labelBefore],
                ['after', '\u2192', dashboard.dataset.labelAfter],
                ['size', '\u2194', dashboard.dataset.labelSize],
                ['hide', '\u00d7', widget.hidden ? dashboard.dataset.labelShow : dashboard.dataset.labelHide]
            ].forEach(function (definition) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.dashboardAction = definition[0];
                button.textContent = definition[1];
                button.title = definition[2] || '';
                button.setAttribute('aria-label', definition[2] || '');
                controls.appendChild(button);
            });
            widget.appendChild(controls);
        });

        const persistOrder = function (zone) {
            state.order[zone.dataset.dashboardZone] = Array.from(zone.children)
                .filter(function (child) { return child.dataset && child.dataset.dashboardWidget; })
                .map(function (child) { return child.dataset.dashboardWidget; });
            save();
        };

        dashboard.addEventListener('click', function (event) {
            const customize = event.target.closest('[data-dashboard-customize]');
            if (customize) {
                dashboard.classList.toggle('dashboard-customizing');
                const active = dashboard.classList.contains('dashboard-customizing');
                customize.setAttribute('aria-pressed', active ? 'true' : 'false');
                const reset = dashboard.querySelector('[data-dashboard-reset]');
                if (reset) reset.hidden = !active;
                return;
            }

            if (event.target.closest('[data-dashboard-reset]')) {
                try {
                    window.localStorage.removeItem(storageKey);
                } catch (error) {
                    window.AdminFramework.emit('dashboard:storage-error', {error: error});
                }
                window.location.reload();
                return;
            }

            const action = event.target.closest('[data-dashboard-action]');
            if (!action) {
                if (dashboard.classList.contains('dashboard-customizing') && event.target.closest('[data-dashboard-widget] a')) event.preventDefault();
                return;
            }

            const widget = action.closest('[data-dashboard-widget]');
            const id = widget.dataset.dashboardWidget;
            const zone = widget.closest('[data-dashboard-zone]');
            const direction = action.dataset.dashboardAction;

            if (direction === 'before' && widget.previousElementSibling) {
                zone.insertBefore(widget, widget.previousElementSibling);
                persistOrder(zone);
            } else if (direction === 'after' && widget.nextElementSibling) {
                zone.insertBefore(widget.nextElementSibling, widget);
                persistOrder(zone);
            } else if (direction === 'size') {
                const size = widget.dataset.dashboardSize === 'wide' ? 'normal' : 'wide';
                widget.dataset.dashboardSize = size;
                state.sizes[id] = size;
                save();
            } else if (direction === 'hide') {
                const isHidden = state.hidden.includes(id);
                widget.hidden = !isHidden;
                state.hidden = isHidden ? state.hidden.filter(function (hiddenId) { return hiddenId !== id; }) : state.hidden.concat(id);
                action.title = isHidden ? dashboard.dataset.labelHide : dashboard.dataset.labelShow;
                action.setAttribute('aria-label', action.title);
                save();
            }
        });

        window.AdminFramework.emit('dashboard:ready', {dashboard: dashboard, owner: owner, state: state});
    }

    document.addEventListener('DOMContentLoaded', initializeDashboard);
})();
