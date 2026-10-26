(function(){'use strict';window.AdminFramework=window.AdminFramework||{version:'1.0.0',hooks:new Map(),on:function(name,callback){const list=this.hooks.get(name)||[];list.push(callback);this.hooks.set(name,list)},emit:function(name,detail){(this.hooks.get(name)||[]).forEach(function(callback){callback(detail)})}};document.addEventListener('click',function(event){const menu=event.target.closest('[data-admin-menu]');if(menu){document.body.classList.toggle('menu-open');return}const toggle=event.target.closest('[data-password-toggle]');if(toggle){const input=document.getElementById(toggle.dataset.passwordToggle);if(input)input.type=input.type==='password'?'text':'password'}});document.addEventListener('DOMContentLoaded',function(){const groups=document.querySelectorAll('[data-nav-accordion]');groups.forEach(function(group){group.addEventListener('toggle',function(){if(!group.open)return;groups.forEach(function(other){if(other!==group)other.open=false})})});window.AdminFramework.emit('ready',{document:document})})})();

(function () {
    'use strict';

    function initializeDashboard() {
        const dashboard = document.querySelector('[data-dashboard]');
        if (!dashboard) return;

        const owner = dashboard.dataset.dashboardOwner || 'anonymous';
        const errorMessage = dashboard.querySelector('[data-dashboard-error]');
        let state = {order: {}, hidden: []};
        try {
            const source = dashboard.querySelector('[data-dashboard-state]');
            if (source) state = Object.assign(state, JSON.parse(source.textContent || '{}'));
        } catch (error) {
            window.AdminFramework.emit('dashboard:storage-error', {error: error});
        }
        state.hidden = Array.isArray(state.hidden) ? state.hidden : [];
        state.order = state.order && typeof state.order === 'object' ? state.order : {};

        const save = function () {
            if (errorMessage) errorMessage.hidden = true;
            return window.fetch(dashboard.dataset.dashboardSaveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': dashboard.dataset.dashboardToken},
                body: JSON.stringify(state)
            }).then(function (response) {
                if (!response.ok) throw new Error('Dashboard state could not be saved.');
            }).catch(function (error) {
                if (errorMessage) errorMessage.hidden = false;
                window.AdminFramework.emit('dashboard:storage-error', {error: error});
                throw error;
            });
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

            const controls = document.createElement('div');
            controls.className = 'dashboard-widget-controls';
            [
                ['drag', '\u2637', dashboard.dataset.labelDrag],
                ['hide', '\u00d7', dashboard.dataset.labelHide]
            ].forEach(function (definition) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.dashboardAction = definition[0];
                if (definition[0] === 'drag') button.draggable = true;
                button.textContent = definition[1];
                button.title = definition[2] || '';
                button.setAttribute('aria-label', definition[2] || '');
                controls.appendChild(button);
            });
            widget.appendChild(controls);
        });

        const hiddenManager = dashboard.querySelector('[data-dashboard-hidden-manager]');
        const hiddenList = dashboard.querySelector('[data-dashboard-hidden-list]');
        const addButton = dashboard.querySelector('[data-dashboard-add]');
        const renderHiddenManager = function () {
            if (!hiddenManager || !hiddenList) return;
            hiddenList.replaceChildren();
            state.hidden.forEach(function (id) {
                const widget = widgets().find(function (candidate) { return candidate.dataset.dashboardWidget === id; });
                if (!widget) return;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'secondary';
                button.dataset.dashboardRestore = id;
                const heading = widget.querySelector('h3');
                button.textContent = (heading ? heading.textContent.trim() : id) + ' · ' + dashboard.dataset.labelRestore;
                hiddenList.appendChild(button);
            });
            const available = hiddenList.children.length > 0;
            if (addButton) addButton.hidden = !dashboard.classList.contains('dashboard-customizing') || !available;
            if (!available) {
                hiddenManager.hidden = true;
                if (addButton) addButton.setAttribute('aria-expanded', 'false');
            }
        };

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
                if (!active && hiddenManager) hiddenManager.hidden = true;
                if (addButton) addButton.setAttribute('aria-expanded', 'false');
                renderHiddenManager();
                return;
            }

            const add = event.target.closest('[data-dashboard-add]');
            if (add && hiddenManager) {
                hiddenManager.hidden = !hiddenManager.hidden;
                add.setAttribute('aria-expanded', hiddenManager.hidden ? 'false' : 'true');
                return;
            }

            if (event.target.closest('[data-dashboard-reset]')) {
                window.fetch(dashboard.dataset.dashboardSaveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': dashboard.dataset.dashboardToken},
                    body: JSON.stringify({reset: true})
                }).then(function (response) {
                    if (!response.ok) throw new Error('Dashboard state could not be reset.');
                    window.location.reload();
                }).catch(function (error) { window.AdminFramework.emit('dashboard:storage-error', {error: error}); });
                return;
            }

            const restore = event.target.closest('[data-dashboard-restore]');
            if (restore) {
                const id = restore.dataset.dashboardRestore;
                const widget = widgets().find(function (candidate) { return candidate.dataset.dashboardWidget === id; });
                state.hidden = state.hidden.filter(function (hiddenId) { return hiddenId !== id; });
                if (widget) widget.hidden = false;
                save().then(function () {
                    renderHiddenManager();
                    if (hiddenManager) hiddenManager.hidden = true;
                    if (addButton) addButton.setAttribute('aria-expanded', 'false');
                }).catch(function () {
                    if (!state.hidden.includes(id)) state.hidden = state.hidden.concat(id);
                    if (widget) widget.hidden = true;
                    renderHiddenManager();
                });
                return;
            }

            const action = event.target.closest('[data-dashboard-action]');
            if (!action) {
                if (dashboard.classList.contains('dashboard-customizing') && event.target.closest('[data-dashboard-widget] a')) event.preventDefault();
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const widget = action.closest('[data-dashboard-widget]');
            const id = widget.dataset.dashboardWidget;
            const zone = widget.closest('[data-dashboard-zone]');
            const direction = action.dataset.dashboardAction;

            if (direction === 'hide') {
                if (!state.hidden.includes(id)) state.hidden = state.hidden.concat(id);
                widget.hidden = true;
                save().then(renderHiddenManager).catch(function () {
                    state.hidden = state.hidden.filter(function (hiddenId) { return hiddenId !== id; });
                    widget.hidden = false;
                    renderHiddenManager();
                });
            }
        });

        let draggedWidget = null;
        dashboard.addEventListener('dragstart', function (event) {
            const handle = event.target.closest('[data-dashboard-action="drag"]');
            if (!handle || !dashboard.classList.contains('dashboard-customizing')) {
                event.preventDefault();
                return;
            }
            draggedWidget = handle.closest('[data-dashboard-widget]');
            draggedWidget.classList.add('dashboard-widget-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedWidget.dataset.dashboardWidget);
        });
        dashboard.addEventListener('dragover', function (event) {
            const zone = event.target.closest('[data-dashboard-zone]');
            if (!draggedWidget || !zone || draggedWidget.closest('[data-dashboard-zone]') !== zone) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            const next = Array.from(zone.querySelectorAll(':scope > [data-dashboard-widget]:not(.dashboard-widget-dragging)'))
                .find(function (candidate) {
                    const rectangle = candidate.getBoundingClientRect();
                    return event.clientY < rectangle.top + rectangle.height / 2;
                });
            zone.insertBefore(draggedWidget, next || null);
        });
        dashboard.addEventListener('drop', function (event) {
            if (!draggedWidget) return;
            event.preventDefault();
            const zone = draggedWidget.closest('[data-dashboard-zone]');
            persistOrder(zone);
        });
        dashboard.addEventListener('dragend', function () {
            if (!draggedWidget) return;
            const zone = draggedWidget.closest('[data-dashboard-zone]');
            draggedWidget.classList.remove('dashboard-widget-dragging');
            persistOrder(zone);
            draggedWidget = null;
        });

        window.AdminFramework.emit('dashboard:ready', {dashboard: dashboard, owner: owner, state: state});
    }

    document.addEventListener('DOMContentLoaded', initializeDashboard);
})();
