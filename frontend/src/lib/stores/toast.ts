import { writable } from 'svelte/store';

export interface ToastMessage {
  id: string;
  type: 'success' | 'error' | 'info' | 'warning';
  title?: string;
  message: string;
  duration?: number;
}

function createToastStore() {
  const { subscribe, update } = writable<ToastMessage[]>([]);

  function show(message: string, type: ToastMessage['type'] = 'info', title?: string, duration = 4000) {
    const id = Math.random().toString(36).substring(2, 9);
    const toast: ToastMessage = { id, type, title, message, duration };

    update((toasts) => [...toasts, toast]);

    if (duration > 0) {
      setTimeout(() => {
        dismiss(id);
      }, duration);
    }
  }

  function dismiss(id: string) {
    update((toasts) => toasts.filter((t) => t.id !== id));
  }

  return {
    subscribe,
    success: (message: string, title = 'Berhasil') => show(message, 'success', title),
    error: (message: string, title = 'Terjadi Kesalahan') => show(message, 'error', title, 5000),
    warning: (message: string, title = 'Peringatan') => show(message, 'warning', title),
    info: (message: string, title = 'Informasi') => show(message, 'info', title),
    dismiss,
  };
}

export const toast = createToastStore();
