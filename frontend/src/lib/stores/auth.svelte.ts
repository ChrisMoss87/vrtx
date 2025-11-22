interface User {
	id: number;
	name: string;
	email: string;
}

interface AuthState {
	user: User | null;
	token: string | null;
	isAuthenticated: boolean;
}

class AuthStore {
	private _user = $state<User | null>(null);
	private _token = $state<string | null>(null);
	private _loading = $state(false);

	constructor() {
		// Load from localStorage on init
		if (typeof window !== 'undefined') {
			const storedToken = localStorage.getItem('auth_token');
			const storedUser = localStorage.getItem('auth_user');

			if (storedToken && storedUser) {
				this._token = storedToken;
				this._user = JSON.parse(storedUser);
			}
		}
	}

	get user(): User | null {
		return this._user;
	}

	get token(): string | null {
		return this._token;
	}

	get loading(): boolean {
		return this._loading;
	}

	get isAuthenticated(): boolean {
		return this._user !== null && this._token !== null;
	}

	setAuth(user: User, token: string) {
		this._user = user;
		this._token = token;

		if (typeof window !== 'undefined') {
			localStorage.setItem('auth_token', token);
			localStorage.setItem('auth_user', JSON.stringify(user));
		}
	}

	clearAuth() {
		this._user = null;
		this._token = null;

		if (typeof window !== 'undefined') {
			localStorage.removeItem('auth_token');
			localStorage.removeItem('auth_user');
		}
	}

	setLoading(loading: boolean) {
		this._loading = loading;
	}
}

export const authStore = new AuthStore();
