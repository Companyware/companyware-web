(function (root, factory) {
    'use strict';
    var sdk = factory(root);
    if (typeof module === 'object' && module.exports) module.exports = sdk;
    if (root) {
        root.CompanywareAdmin = root.CompanywareAdmin || sdk;
        root.AdminFramework = root.AdminFramework || {version: sdk.version, on: sdk.hooks.on, emit: sdk.hooks.emit};
    }
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
    'use strict';
    var semanticVersion = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/;
    var sequence = 0;

    function assertRegistration(value) {
        if (!value || typeof value !== 'object' || !/^[a-z][a-z0-9._-]*$/.test(value.id || '') || !/^[a-z][a-z0-9-]*$/.test(value.owner || '') || !semanticVersion.test(value.version || '')) {
            throw new TypeError('A registration requires a stable id, owner and semantic version.');
        }
    }
    function major(version) { return String(version).split('.')[0]; }
    function createRegistry(validate) {
        var entries = new Map();
        return {
            register: function (entry) {
                assertRegistration(entry);
                if (validate) validate(entry);
                var current = entries.get(entry.id);
                if (!current) {
                    if (entry.replaces) throw new Error('Replacement target "' + entry.replaces.id + '" does not exist.');
                    entries.set(entry.id, Object.freeze(Object.assign({_sequence: ++sequence}, entry)));
                    return entries.get(entry.id);
                }
                var replacement = entry.replaces;
                if (!replacement || replacement.id !== current.id || !current.replaceable) throw new Error('Registration "' + entry.id + '" already exists and is not replaceable.');
                if (major(replacement.version) !== major(current.version)) throw new Error('Replacement for "' + entry.id + '" is incompatible with version ' + current.version + '.');
                entries.set(entry.id, Object.freeze(Object.assign({_sequence: current._sequence}, entry)));
                return entries.get(entry.id);
            },
            get: function (id) { return entries.get(id); },
            all: function () { return Array.from(entries.values()).sort(function (a, b) { return (a.priority || 0) - (b.priority || 0) || a._sequence - b._sequence; }); }
        };
    }

    var hooks = new Map();
    var hookApi = {
        on: function (name, listener) {
            if (typeof listener !== 'function') throw new TypeError('Hook listener must be a function.');
            var listeners = hooks.get(name) || [];
            listeners.push(listener); hooks.set(name, listeners);
            return function () { hooks.set(name, (hooks.get(name) || []).filter(function (candidate) { return candidate !== listener; })); };
        },
        emit: function (name, detail) { (hooks.get(name) || []).slice().forEach(function (listener) { listener(detail); }); }
    };
    var routeRegistry = createRegistry(function (route) {
        if (typeof route.path !== 'string' || route.path.charAt(0) !== '/' || typeof route.load !== 'function') throw new TypeError('A route requires an absolute path and loader.');
    });
    var navigationRegistry = createRegistry(function (item) {
        if (typeof item.label !== 'string' || typeof item.route !== 'string') throw new TypeError('Navigation requires label and route.');
    });
    var moduleRegistry = createRegistry();
    var componentRegistry = createRegistry();
    var services = new Map();
    var slotRegistries = new Map();
    var granted = new Set();
    var apiOptions = {baseUrl: '/api/v1', csrfToken: '', accessToken: ''};
    var notificationListeners = [];
    var modalListeners = [];
    var modals = new Map();

    function subscribe(list, listener) {
        if (typeof listener !== 'function') throw new TypeError('Subscriber must be a function.');
        list.push(listener);
        return function () { var index = list.indexOf(listener); if (index >= 0) list.splice(index, 1); };
    }
    function serializeForm(form) {
        if (!root.FormData) throw new Error('FormData is unavailable.');
        var output = {};
        new root.FormData(form).forEach(function (value, key) {
            if (Object.prototype.hasOwnProperty.call(output, key)) output[key] = Array.isArray(output[key]) ? output[key].concat([value]) : [output[key], value];
            else output[key] = value;
        });
        return output;
    }
    function table(rows) {
        return {
            rows: Array.isArray(rows) ? rows.slice() : [], sortBy: null, direction: 'asc',
            setRows: function (next) { this.rows = Array.isArray(next) ? next.slice() : []; },
            sort: function (field, direction) {
                this.sortBy = field; this.direction = direction || 'asc';
                var multiplier = this.direction === 'desc' ? -1 : 1;
                this.rows.sort(function (left, right) { return multiplier * String(left[field] == null ? '' : left[field]).localeCompare(String(right[field] == null ? '' : right[field]), undefined, {numeric: true}); });
                return this.rows;
            }
        };
    }
    var sdk = Object.freeze({
        version: '1.0.0',
        routes: Object.freeze({register: routeRegistry.register, all: routeRegistry.all, resolve: function (path) { return routeRegistry.all().find(function (route) { return route.path === path; }); }}),
        navigation: Object.freeze({register: navigationRegistry.register, all: navigationRegistry.all}),
        modules: Object.freeze({register: moduleRegistry.register, get: moduleRegistry.get, all: moduleRegistry.all}),
        components: Object.freeze({register: componentRegistry.register, get: componentRegistry.get, all: componentRegistry.all}),
        services: Object.freeze({register: function (name, service) { if (services.has(name)) throw new Error('Service "' + name + '" is already registered.'); services.set(name, service); return service; }, get: function (name) { return services.get(name); }}),
        slots: Object.freeze({
            add: function (name, item) { if (!slotRegistries.has(name)) slotRegistries.set(name, createRegistry(function (slot) { if (typeof slot.render !== 'function') throw new TypeError('A slot contribution requires render().'); })); return slotRegistries.get(name).register(item); },
            render: function (name, context) { return (slotRegistries.has(name) ? slotRegistries.get(name).all() : []).map(function (item) { return item.render(context); }); }
        }),
        permissions: Object.freeze({configure: function (privileges) { granted = new Set(Array.isArray(privileges) ? privileges : []); }, has: function (privilege) { return granted.has('*') || granted.has(privilege); }, any: function (privileges) { return privileges.some(sdk.permissions.has); }, all: function (privileges) { return privileges.every(sdk.permissions.has); }}),
        api: Object.freeze({
            configure: function (options) { apiOptions = Object.assign({}, apiOptions, options || {}); },
            request: function (path, options) {
                if (!root.fetch) return Promise.reject(new Error('Fetch API is unavailable.'));
                var request = Object.assign({credentials: 'same-origin'}, options || {});
                request.headers = Object.assign({'Accept': 'application/json'}, request.headers || {});
                if (apiOptions.accessToken) request.headers.Authorization = 'Bearer ' + apiOptions.accessToken;
                if (apiOptions.csrfToken && !/^(GET|HEAD)$/i.test(request.method || 'GET')) request.headers['X-CSRF-TOKEN'] = apiOptions.csrfToken;
                var url = /^https?:/.test(path) || path.indexOf(apiOptions.baseUrl) === 0 ? path : apiOptions.baseUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
                return root.fetch(url, request).then(function (response) {
                    if (response.status === 204) return undefined;
                    return response.text().then(function (body) { var data; try { data = body ? JSON.parse(body) : undefined; } catch (error) { data = body; } if (!response.ok) { var failure = new Error((data && (data.detail || data.title)) || 'Request failed with status ' + response.status + '.'); failure.status = response.status; failure.problem = data; throw failure; } return data; });
                });
            }
        }),
        notifications: Object.freeze({push: function (notification) { var value = Object.freeze(Object.assign({id: ++sequence, type: 'info'}, notification)); notificationListeners.slice().forEach(function (listener) { listener(value); }); hookApi.emit('notification', value); return value; }, subscribe: function (listener) { return subscribe(notificationListeners, listener); }}),
        forms: Object.freeze({values: serializeForm}),
        tables: Object.freeze({create: table}),
        modals: Object.freeze({open: function (options) { var modal = Object.freeze({id: ++sequence, options: options || {}}); modals.set(modal.id, modal); modalListeners.slice().forEach(function (listener) { listener({type: 'open', modal: modal}); }); return modal; }, close: function (id, result) { var modal = modals.get(id); if (!modal) return false; modals.delete(id); modalListeners.slice().forEach(function (listener) { listener({type: 'close', modal: modal, result: result}); }); return true; }, subscribe: function (listener) { return subscribe(modalListeners, listener); }}),
        hooks: Object.freeze(hookApi)
    });
    return sdk;
});
