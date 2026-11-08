export type VersionedRegistration = {
    id: string;
    owner: string;
    version: `${number}.${number}.${number}`;
    replaceable?: boolean;
    replaces?: {id: string; version: string};
};

export type RouteRegistration = VersionedRegistration & {path: string; load: () => unknown};
export type NavigationRegistration = VersionedRegistration & {label: string; route: string; position?: number; privilege?: string};
export type SlotRegistration<T = unknown> = VersionedRegistration & {priority?: number; render: (context: T) => unknown};
export type Notification = {type?: 'info' | 'success' | 'warning' | 'error'; title: string; message?: string};

export interface CompanywareAdminSdk {
    readonly version: '1.0.0';
    readonly routes: {register(route: RouteRegistration): RouteRegistration; all(): RouteRegistration[]; resolve(path: string): RouteRegistration | undefined};
    readonly navigation: {register(item: NavigationRegistration): NavigationRegistration; all(): NavigationRegistration[]};
    readonly slots: {add<T>(name: string, item: SlotRegistration<T>): SlotRegistration<T>; render<T>(name: string, context: T): unknown[]};
    readonly modules: {register<T extends VersionedRegistration>(module: T): T; get<T>(id: string): T | undefined; all<T>(): T[]};
    readonly permissions: {configure(privileges: string[]): void; has(privilege: string): boolean; any(privileges: string[]): boolean; all(privileges: string[]): boolean};
    readonly api: {configure(options: {baseUrl?: string; csrfToken?: string; accessToken?: string}): void; request(path: string, options?: RequestInit): Promise<unknown>};
    readonly notifications: {push(notification: Notification): Notification & {id: number}; subscribe(listener: (notification: Notification & {id: number}) => void): () => void};
    readonly forms: {values(form: HTMLFormElement): Record<string, unknown>};
    readonly tables: {create<T>(rows?: T[]): {rows: T[]; sortBy: string | null; direction: 'asc' | 'desc'; setRows(rows: T[]): void; sort(field: keyof T & string, direction?: 'asc' | 'desc'): T[]}};
    readonly modals: {open(options: Record<string, unknown>): {id: number; options: Record<string, unknown>}; close(id: number, result?: unknown): boolean; subscribe(listener: (event: {type: 'open' | 'close'; modal: unknown; result?: unknown}) => void): () => void};
    readonly hooks: {on(name: string, listener: (detail: unknown) => void): () => void; emit(name: string, detail?: unknown): void};
}

declare global { interface Window { CompanywareAdmin: CompanywareAdminSdk; AdminFramework: {version: string; on(name: string, listener: (detail: unknown) => void): () => void; emit(name: string, detail?: unknown): void}; Shopware: {Module: {register(name: string, config: Record<string, unknown>): unknown}; Component: {register(name: string, definition: unknown): unknown; extend(name: string, target: string, definition: unknown): unknown; override(name: string, definition: unknown): unknown}; Service: {register(name: string, service: unknown): unknown; get(name: string): unknown}; Context: {api: {apiPath: string}}}; } }
