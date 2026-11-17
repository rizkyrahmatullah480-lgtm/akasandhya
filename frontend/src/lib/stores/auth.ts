import { writable, derived } from 'svelte/store';
import type { User } from '$lib/types';
import { api } from '$lib/services/api';
import { browser } from '$app/env';

interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
  initialized: boolean;
}

function createAuthStore() {
  const initialState: AuthState = {
    user: null,
    token: null,
    loading: true,
    initialized: false
  };

  const { subscribe, set, update } = writable<AuthState>(initialState);

  async function init() {
    if (!browser) return;

    const token = localStorage.getItem('invitaku_token');
    const storedUser = localStorage.getItem('invitaku_user');

    if (!token) {
      update((state) => ({ ...state, loading: false, initialized: true }));
      return;
    }

    let parsedUser: User | null = null;
    try {
      if (storedUser) parsedUser = JSON.parse(storedUser);
    } catch {
      parsedUser = null;
    }

    update((state) => ({ ...state, token, user: parsedUser, loading: true }));

    try {
      const res = await api.getMe();
      const freshUser = res.data.user;
      localStorage.setItem('invitaku_user', JSON.stringify(freshUser));
      update((state) => ({ ...state, user: freshUser, loading: false, initialized: true }));
    } catch {
      localStorage.removeItem('invitaku_token');
      localStorage.removeItem('invitaku_user');
      update((state) => ({ ...state, user: null, token: null, loading: false, initialized: true }));
    }
  }

  function setAuth(user: User, token: string) {
    if (browser) {
      localStorage.setItem('invitaku_token', token);
      localStorage.setItem('invitaku_user', JSON.stringify(user));
    }
    update((state) => ({ ...state, user, token, loading: false, initialized: true }));
  }

  async function logout() {
    try {
      await api.logout();
    } catch {
      // ignore network errors on logout
    } finally {
      if (browser) {
        localStorage.removeItem('invitaku_token');
        localStorage.removeItem('invitaku_user');
      }
      set({ user: null, token: null, loading: false, initialized: true });
    }
  }

  function updateUser(updatedUser: Partial<User>) {
    update((state) => {
      if (!state.user) return state;
      const newUser = { ...state.user, ...updatedUser };
      if (browser) {
        localStorage.setItem('invitaku_user', JSON.stringify(newUser));
      }
      return { ...state, user: newUser };
    });
  }

  return { subscribe, init, setAuth, logout, updateUser };
}

export const auth = createAuthStore();

export const isAuthenticated = derived(auth, ($auth) => !!$auth.user && !!$auth.token);
export const isAdmin = derived(auth, ($auth) => $auth.user?.role === 'admin');
export const isScanner = derived(auth, ($auth) => $auth.user?.role === 'scanner');
export const currentUser = derived(auth, ($auth) => $auth.user);
