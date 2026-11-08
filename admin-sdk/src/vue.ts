import {createApp, type App, type Component} from 'vue';
import type {CompanywareAdminSdk} from './index';

export type CompanywareVueOptions = {
    component: Component;
    target: string | Element;
    sdk?: CompanywareAdminSdk;
    props?: Record<string, unknown>;
};

export function mountCompanywareApp(options: CompanywareVueOptions): App {
    const sdk = options.sdk ?? window.CompanywareAdmin;
    const target = typeof options.target === 'string' ? document.querySelector(options.target) : options.target;
    if (!target) throw new Error('Companyware Vue mount target was not found.');
    const app = createApp(options.component, options.props ?? {});
    app.provide('companyware.admin', sdk);
    app.config.globalProperties.$companyware = sdk;
    app.mount(target);
    return app;
}
