interface Tenant {
	id: string;
	name: string;
	domain: string;
	subdomain: string;
}

class TenantStore {
	private _tenant = $state<Tenant | null>(null);
	private _loading = $state(false);

	get tenant(): Tenant | null {
		return this._tenant;
	}

	get loading(): boolean {
		return this._loading;
	}

	get isMultiTenant(): boolean {
		return this._tenant !== null;
	}

	async loadFromHeaders(headers: Headers) {
		const tenantHeader = headers.get('x-tenant');
		const host = headers.get('host') || '';

		if (tenantHeader) {
			this._loading = true;
			try {
				// Extract subdomain from host
				const parts = host.split('.');
				const subdomain = parts.length > 2 ? parts[0] : null;

				// In production, fetch tenant data from API
				// For now, create a placeholder
				this._tenant = {
					id: tenantHeader,
					name: tenantHeader,
					domain: host,
					subdomain: subdomain || tenantHeader
				};
			} catch (error) {
				console.error('Failed to load tenant:', error);
				this._tenant = null;
			} finally {
				this._loading = false;
			}
		}
	}

	setTenant(tenant: Tenant | null) {
		this._tenant = tenant;
	}

	clear() {
		this._tenant = null;
	}
}

export const tenantStore = new TenantStore();
