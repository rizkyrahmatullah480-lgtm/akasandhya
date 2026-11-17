<script lang="ts">
  import { onMount } from 'svelte';
  import { auth } from '$lib/stores/auth';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { Role, User } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import ConfirmModal from '$lib/components/ConfirmModal.svelte';
  import {
    User as UserIcon,
    Lock,
    ShieldCheck,
    Plus,
    Pencil,
    Trash2,
    Loader2,
    Mail,
    Phone,
    KeyRound,
    Save,
    Users,
    Crown,
    ScanLine
  } from '@lucide/svelte';

  let user = $derived($auth.user);

  // Profile
  let profileName = $state('');
  let profilePhone = $state('');
  let profileLoaded = $state(false);
  let savingProfile = $state(false);
  let profileErrors = $state<Record<string, string[]>>({});
  let profileMessage = $state('');

  // Password
  let currentPassword = $state('');
  let newPassword = $state('');
  let confirmPassword = $state('');
  let savingPassword = $state(false);
  let passwordErrors = $state<Record<string, string[]>>({});
  let passwordMessage = $state('');

  // Officers / accounts
  let users = $state<User[]>([]);
  let loadingUsers = $state(false);
  let usersLoaded = $state(false);

  let showUserForm = $state(false);
  let editingUser = $state<User | null>(null);
  let formName = $state('');
  let formEmail = $state('');
  let formPhone = $state('');
  let formRole = $state<Role>('scanner');
  let formPassword = $state('');
  let formPasswordConfirm = $state('');
  let savingUser = $state(false);
  let userErrors = $state<Record<string, string[]>>({});
  let userMessage = $state('');

  let deletingUser = $state<User | null>(null);
  let removingUser = $state(false);

  $effect(() => {
    const current = $auth.user;
    if (current && !profileLoaded) {
      profileName = current.name;
      profilePhone = current.phone ?? '';
      profileLoaded = true;
    }
  });

  onMount(async () => {
    if (user?.role === 'admin') {
      await loadUsers();
    }
  });

  async function loadUsers() {
    loadingUsers = true;
    try {
      const res = await api.getUsers();
      users = res.data;
      usersLoaded = true;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat akun petugas.');
    } finally {
      loadingUsers = false;
    }
  }

  async function saveProfile(e: SubmitEvent) {
    e.preventDefault();
    savingProfile = true;
    profileErrors = {};
    profileMessage = '';

    try {
      const res = await api.updateProfile({
        name: profileName.trim(),
        phone: profilePhone.trim() || undefined
      });
      auth.updateUser(res.data.user);
      toast.success('Profil berhasil diperbarui.');
      profileMessage = 'Profil berhasil diperbarui.';
    } catch (err: any) {
      profileErrors = err.errors ?? {};
      profileMessage = err.message || 'Gagal memperbarui profil.';
    } finally {
      savingProfile = false;
    }
  }

  async function savePassword(e: SubmitEvent) {
    e.preventDefault();
    savingPassword = true;
    passwordErrors = {};
    passwordMessage = '';

    if (newPassword !== confirmPassword) {
      passwordErrors = { new_password_confirmation: ['Konfirmasi password tidak sama.'] };
      passwordMessage = 'Konfirmasi password tidak sama.';
      savingPassword = false;
      return;
    }

    try {
      await api.updatePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword
      });
      currentPassword = '';
      newPassword = '';
      confirmPassword = '';
      toast.success('Password berhasil diubah.');
      passwordMessage = 'Password berhasil diubah.';
    } catch (err: any) {
      passwordErrors = err.errors ?? {};
      passwordMessage = err.message || 'Gagal mengubah password.';
    } finally {
      savingPassword = false;
    }
  }

  function openCreateUser() {
    editingUser = null;
    formName = '';
    formEmail = '';
    formPhone = '';
    formRole = 'scanner';
    formPassword = '';
    formPasswordConfirm = '';
    userErrors = {};
    userMessage = '';
    showUserForm = true;
  }

  function openEditUser(target: User) {
    editingUser = target;
    formName = target.name;
    formEmail = target.email;
    formPhone = target.phone ?? '';
    formRole = target.role;
    formPassword = '';
    formPasswordConfirm = '';
    userErrors = {};
    userMessage = '';
    showUserForm = true;
  }

  async function saveUser(e: SubmitEvent) {
    e.preventDefault();
    savingUser = true;
    userErrors = {};
    userMessage = '';

    const payload: Record<string, any> = {
      name: formName.trim(),
      email: formEmail.trim(),
      role: formRole,
      phone: formPhone.trim() || null
    };

    if (formPassword) {
      payload.password = formPassword;
      payload.password_confirmation = formPasswordConfirm;
    }

    try {
      if (editingUser) {
        await api.updateUser(editingUser.id, payload);
        toast.success('Akun berhasil diperbarui.');
      } else {
        await api.createUser({
          name: payload.name,
          email: payload.email,
          role: payload.role,
          phone: payload.phone ?? undefined,
          password: formPassword,
          password_confirmation: formPasswordConfirm
        });
        toast.success('Akun petugas berhasil dibuat.');
      }
      showUserForm = false;
      await loadUsers();
    } catch (err: any) {
      userErrors = err.errors ?? {};
      userMessage = err.message || 'Gagal menyimpan akun.';
    } finally {
      savingUser = false;
    }
  }

  async function confirmDeleteUser() {
    if (!deletingUser) return;
    removingUser = true;
    try {
      await api.deleteUser(deletingUser.id);
      toast.success('Akun berhasil dihapus.');
      deletingUser = null;
      await loadUsers();
    } catch (err: any) {
      toast.error(err.message || 'Gagal menghapus akun.');
    } finally {
      removingUser = false;
    }
  }

  function fieldError(errors: Record<string, string[]>, key: string) {
    return errors[key]?.[0];
  }

  function formatDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  }
</script>

<svelte:head><title>Pengaturan — Invitaku</title></svelte:head>

{#if !user}
  <Spinner label="Memuat profil..." full />
{:else}
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-semibold text-stone-900">Pengaturan</h2>
      <p class="text-sm text-stone-500 mt-1">
        Kelola profil, keamanan akun, dan akun petugas scanner.
      </p>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
      <!-- Profile -->
      <section class="card p-5 sm:p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-xl bg-[#7b2d43]/10 text-[#7b2d43] flex items-center justify-center">
            <UserIcon class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-semibold text-stone-900">Profil Saya</h3>
            <p class="text-xs text-stone-500">Informasi akun yang sedang digunakan.</p>
          </div>
        </div>

        <form class="space-y-4" onsubmit={saveProfile}>
          {#if profileMessage}
            <div class="rounded-xl border px-3 py-2.5 text-sm {Object.keys(profileErrors).length
              ? 'border-rose-200 bg-rose-50 text-rose-700'
              : 'border-emerald-200 bg-emerald-50 text-emerald-700'}">
              {profileMessage}
            </div>
          {/if}

          <div>
            <label class="label" for="profile-name">Nama lengkap</label>
            <input id="profile-name" class="input" bind:value={profileName} required />
            {#if fieldError(profileErrors, 'name')}
              <p class="text-xs text-rose-600 mt-1">{fieldError(profileErrors, 'name')}</p>
            {/if}
          </div>

          <div>
            <label class="label" for="profile-email">Email</label>
            <input id="profile-email" class="input bg-stone-50 text-stone-500" value={user.email} readonly disabled />
            <p class="text-[11px] text-stone-400 mt-1">Email tidak dapat diubah sendiri oleh pengguna.</p>
          </div>

          <div>
            <label class="label" for="profile-phone">Nomor telepon</label>
            <input id="profile-phone" class="input" bind:value={profilePhone} placeholder="08xxxxxxxxxx" />
            {#if fieldError(profileErrors, 'phone')}
              <p class="text-xs text-rose-600 mt-1">{fieldError(profileErrors, 'phone')}</p>
            {/if}
          </div>

          <div class="flex items-center gap-2 text-xs text-stone-500">
            <ShieldCheck class="w-4 h-4 text-emerald-500" />
            Role: <span class="font-medium capitalize text-stone-700">{user.role === 'admin' ? 'Administrator' : 'Petugas Scanner'}</span>
          </div>

          <button type="submit" class="btn-primary w-full text-sm" disabled={savingProfile}>
            {#if savingProfile}
              <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
            {:else}
              <Save class="w-4 h-4" /> Simpan Profil
            {/if}
          </button>
        </form>
      </section>

      <!-- Password -->
      <section class="card p-5 sm:p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
            <Lock class="w-5 h-5" />
          </div>
          <div>
            <h3 class="font-semibold text-stone-900">Ubah Password</h3>
            <p class="text-xs text-stone-500">Gunakan password yang kuat dan mudah Anda ingat.</p>
          </div>
        </div>

        <form class="space-y-4" onsubmit={savePassword}>
          {#if passwordMessage}
            <div class="rounded-xl border px-3 py-2.5 text-sm {Object.keys(passwordErrors).length
              ? 'border-rose-200 bg-rose-50 text-rose-700'
              : 'border-emerald-200 bg-emerald-50 text-emerald-700'}">
              {passwordMessage}
            </div>
          {/if}

          <div>
            <label class="label" for="current-password">Password saat ini</label>
            <input
              id="current-password"
              type="password"
              class="input"
              bind:value={currentPassword}
              autocomplete="current-password"
              required
            />
            {#if fieldError(passwordErrors, 'current_password')}
              <p class="text-xs text-rose-600 mt-1">{fieldError(passwordErrors, 'current_password')}</p>
            {/if}
          </div>

          <div>
            <label class="label" for="new-password">Password baru</label>
            <input
              id="new-password"
              type="password"
              class="input"
              bind:value={newPassword}
              autocomplete="new-password"
              minlength="8"
              required
            />
            <p class="text-[11px] text-stone-400 mt-1">Minimal 8 karakter.</p>
            {#if fieldError(passwordErrors, 'new_password')}
              <p class="text-xs text-rose-600 mt-1">{fieldError(passwordErrors, 'new_password')}</p>
            {/if}
          </div>

          <div>
            <label class="label" for="confirm-password">Konfirmasi password baru</label>
            <input
              id="confirm-password"
              type="password"
              class="input"
              bind:value={confirmPassword}
              autocomplete="new-password"
              required
            />
            {#if fieldError(passwordErrors, 'new_password_confirmation')}
              <p class="text-xs text-rose-600 mt-1">{fieldError(passwordErrors, 'new_password_confirmation')}</p>
            {/if}
          </div>

          <button type="submit" class="btn-secondary w-full text-sm" disabled={savingPassword}>
            {#if savingPassword}
              <Loader2 class="w-4 h-4 animate-spin" /> Mengubah...
            {:else}
              <KeyRound class="w-4 h-4" /> Ubah Password
            {/if}
          </button>
        </form>
      </section>
    </div>

    <!-- Officer accounts (admin only) -->
    {#if user.role === 'admin'}
      <section class="card overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-stone-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <Users class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-semibold text-stone-900">Akun Petugas</h3>
              <p class="text-xs text-stone-500">Admin dan petugas scanner yang dapat mengakses aplikasi.</p>
            </div>
          </div>
          <button class="btn-primary text-sm" onclick={openCreateUser}>
            <Plus class="w-4 h-4" /> Tambah Akun
          </button>
        </div>

        {#if loadingUsers && !usersLoaded}
          <Spinner label="Memuat akun..." />
        {:else if users.length === 0}
          <div class="py-12 text-center text-sm text-stone-500">Belum ada akun terdaftar.</div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
              <thead>
                <tr>
                  <th class="th w-12">No</th>
                  <th class="th">Nama</th>
                  <th class="th">Email</th>
                  <th class="th">Role</th>
                  <th class="th">Telepon</th>
                  <th class="th">Dibuat</th>
                  <th class="th text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-100">
                {#each users as account, index (account.id)}
                  <tr class="hover:bg-stone-50/70">
                    <td class="td text-stone-400">{index + 1}</td>
                    <td class="td">
                      <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-stone-100 text-stone-600 flex items-center justify-center text-xs font-semibold">
                          {account.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p class="font-medium text-stone-800">{account.name}</p>
                          {#if account.id === user.id}
                            <p class="text-[10px] text-[#7b2d43] font-medium">Akun Anda</p>
                          {/if}
                        </div>
                      </div>
                    </td>
                    <td class="td text-stone-600">{account.email}</td>
                    <td class="td">
                      {#if account.role === 'admin'}
                        <span class="badge bg-[#7b2d43]/10 text-[#7b2d43] border border-[#7b2d43]/20">
                          <Crown class="w-3 h-3" /> Admin
                        </span>
                      {:else}
                        <span class="badge bg-sky-50 text-sky-700 border border-sky-200">
                          <ScanLine class="w-3 h-3" /> Scanner
                        </span>
                      {/if}
                    </td>
                    <td class="td text-stone-500">{account.phone ?? '-'}</td>
                    <td class="td text-stone-500">{formatDate(account.created_at)}</td>
                    <td class="td">
                      <div class="flex items-center justify-end gap-1">
                        <button
                          class="p-1.5 rounded-md text-stone-500 hover:bg-stone-100"
                          title="Edit akun"
                          onclick={() => openEditUser(account)}
                        >
                          <Pencil class="w-4 h-4" />
                        </button>
                        <button
                          class="p-1.5 rounded-md text-rose-500 hover:bg-rose-50 disabled:opacity-30"
                          title={account.id === user.id ? 'Tidak dapat menghapus akun sendiri' : 'Hapus akun'}
                          disabled={account.id === user.id}
                          onclick={() => (deletingUser = account)}
                        >
                          <Trash2 class="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {/if}
  </div>
{/if}

<!-- User form modal -->
<Modal
  open={showUserForm}
  title={editingUser ? 'Edit Akun' : 'Tambah Akun'}
  description={editingUser ? 'Perbarui data akun pengguna.' : 'Buat akun admin atau petugas scanner baru.'}
  onclose={() => (showUserForm = false)}
  size="sm"
>
  <form class="space-y-4" onsubmit={saveUser}>
    {#if userMessage}
      <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{userMessage}</div>
    {/if}

    <div>
      <label class="label" for="user-name">Nama lengkap <span class="text-rose-500">*</span></label>
      <input id="user-name" class="input" bind:value={formName} required />
      {#if fieldError(userErrors, 'name')}<p class="text-xs text-rose-600 mt-1">{fieldError(userErrors, 'name')}</p>{/if}
    </div>

    <div>
      <label class="label" for="user-email">Email <span class="text-rose-500">*</span></label>
      <input id="user-email" type="email" class="input" bind:value={formEmail} required />
      {#if fieldError(userErrors, 'email')}<p class="text-xs text-rose-600 mt-1">{fieldError(userErrors, 'email')}</p>{/if}
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="label" for="user-role">Role <span class="text-rose-500">*</span></label>
        <select id="user-role" class="select" bind:value={formRole}>
          <option value="scanner">Petugas Scanner</option>
          <option value="admin">Administrator</option>
        </select>
      </div>
      <div>
        <label class="label" for="user-phone">Telepon</label>
        <input id="user-phone" class="input" bind:value={formPhone} placeholder="08xxxxxxxxxx" />
      </div>
    </div>

    <div>
      <label class="label" for="user-password">
        Password {#if !editingUser}<span class="text-rose-500">*</span>{/if}
      </label>
      <input
        id="user-password"
        type="password"
        class="input"
        bind:value={formPassword}
        autocomplete="new-password"
        minlength="8"
        required={!editingUser}
        placeholder={editingUser ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'}
      />
      {#if fieldError(userErrors, 'password')}<p class="text-xs text-rose-600 mt-1">{fieldError(userErrors, 'password')}</p>{/if}
    </div>

    <div>
      <label class="label" for="user-password-confirm">Konfirmasi password</label>
      <input
        id="user-password-confirm"
        type="password"
        class="input"
        bind:value={formPasswordConfirm}
        autocomplete="new-password"
        required={!editingUser || !!formPassword}
      />
    </div>

    <div class="flex items-center justify-end gap-2 pt-2">
      <button type="button" class="btn-secondary text-sm" onclick={() => (showUserForm = false)}>Batal</button>
      <button type="submit" class="btn-primary text-sm" disabled={savingUser}>
        {#if savingUser}
          <Loader2 class="w-4 h-4 animate-spin" /> Menyimpan...
        {:else}
          {editingUser ? 'Simpan Perubahan' : 'Buat Akun'}
        {/if}
      </button>
    </div>
  </form>
</Modal>

<ConfirmModal
  open={!!deletingUser}
  title="Hapus akun?"
  message={`Akun "${deletingUser?.name ?? ''}" akan dihapus dan tidak dapat mengakses aplikasi lagi.`}
  confirmText={removingUser ? 'Menghapus...' : 'Ya, hapus'}
  type="danger"
  onConfirm={confirmDeleteUser}
  onCancel={() => (deletingUser = null)}
/>
