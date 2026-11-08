const test = require('node:test');
const assert = require('node:assert/strict');
const sdk = require('../dist/companyware-admin-sdk.js');

test('registers routes and resolves them', () => {
    sdk.routes.register({id: 'test.route', owner: 'test', version: '1.0.0', path: '/admin/test', load: () => 'loaded'});
    assert.equal(sdk.routes.resolve('/admin/test').load(), 'loaded');
});

test('keeps slot contributions deterministic', () => {
    sdk.slots.add('test.slot', {id: 'test.later', owner: 'test', version: '1.0.0', priority: 20, render: () => 'later'});
    sdk.slots.add('test.slot', {id: 'test.first', owner: 'test', version: '1.0.0', priority: 10, render: () => 'first'});
    assert.deepEqual(sdk.slots.render('test.slot', {}), ['first', 'later']);
});

test('requires explicit compatible replacement', () => {
    sdk.modules.register({id: 'test.module', owner: 'test', version: '1.2.0', replaceable: true, value: 'core'});
    assert.throws(() => sdk.modules.register({id: 'test.module', owner: 'other', version: '1.0.0'}), /not replaceable/);
    assert.throws(() => sdk.modules.register({id: 'test.module', owner: 'other', version: '2.0.0', replaces: {id: 'test.module', version: '2.0.0'}}), /incompatible/);
    sdk.modules.register({id: 'test.module', owner: 'other', version: '1.1.0', replaces: {id: 'test.module', version: '1.0.0'}, value: 'plugin'});
    assert.equal(sdk.modules.get('test.module').value, 'plugin');
});

test('applies permissions and table helpers', () => {
    sdk.permissions.configure(['documents.read']);
    assert.equal(sdk.permissions.has('documents.read'), true);
    assert.equal(sdk.permissions.has('documents.delete'), false);
    const table = sdk.tables.create([{name: 'B'}, {name: 'A'}]);
    assert.deepEqual(table.sort('name').map(row => row.name), ['A', 'B']);
});

test('maps the stable Shopware administration registration pattern', () => {
    globalThis.Shopware.Component.register('swag-example-list', {template: '<div>Example</div>'});
    globalThis.Shopware.Module.register('swag-example', {
        title: 'Example',
        routes: {list: {component: 'swag-example-list', path: 'swag/example/index'}},
        navigation: [{id: 'swag-example-menu', label: 'Example', path: 'swag.example.list', position: 100}]
    });
    assert.equal(sdk.routes.resolve('/admin/swag/example/index').id, 'swag-example.list');
    assert.equal(sdk.navigation.all().some(item => item.id === 'swag-example-menu'), true);
    assert.equal(globalThis.Shopware.__companywareCompatibility.scope, 'administration-registration');
});
