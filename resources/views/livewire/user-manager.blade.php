@php
    $roleBadge = [
        'developer' => 'bg-purple-100 text-purple-700 ring-purple-200',
        'hq_admin' => 'bg-indigo-100 text-indigo-700 ring-indigo-200',
        'manager' => 'bg-blue-100 text-blue-700 ring-blue-200',
        'askep' => 'bg-cyan-100 text-cyan-700 ring-cyan-200',
        'asisten' => 'bg-amber-100 text-amber-700 ring-amber-200',
        'operator' => 'bg-gray-100 text-gray-600 ring-gray-200',
    ];
    $deptLabel = ['proses' => 'Proses', 'maintenance' => 'Maintenance', 'lab' => 'Lab'];
    $roleCounts = $roleCounts ?? collect();
@endphp

<div class="space-y-6"
     x-data="{ copied: false }"
     @keydown.escape.window="$wire.closeModal()">

    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-users-cog text-green-600"></i> Manajemen User
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Tambah, ubah role, reset password, aktif/nonaktifkan, dan hapus akun pengguna.
            </p>
        </div>
        <button type="button" wire:click="openCreate" class="btn-primary">
            <i class="fas fa-plus"></i> Tambah User
        </button>
    </div>

    {{-- Banner password hasil reset / generate --}}
    @if($generatedPassword)
    <div class="glass-subtle rounded-2xl border-green-300/60 p-4" role="status">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <i class="fas fa-key mt-0.5 text-green-600"></i>
                <div>
                    <p class="text-sm font-semibold text-green-800">Password baru (tampil sekali — salin sekarang)</p>
                    <code class="mt-1 inline-block rounded-lg bg-white px-3 py-1 text-base font-mono font-bold tracking-wider text-green-700 ring-1 ring-green-200"
                          x-ref="genpw">{{ $generatedPassword }}</code>
                </div>
            </div>
            <button type="button"
                    @click="navigator.clipboard.writeText($refs.genpw.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)"
                    class="btn-primary">
                <i class="fas" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
            </button>
        </div>
    </div>
    @endif

    {{-- Ringkasan role --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach($roles as $r)
        <button type="button" wire:click="$set('roleFilter', '{{ $roleFilter === $r ? '' : $r }}')"
                class="glass-subtle rounded-2xl p-3 text-left transition hover:-translate-y-0.5 hover:shadow-glass
                       {{ $roleFilter === $r ? 'bg-white/85 ring-1 ring-green-500/50' : '' }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $r }}</p>
            <p class="text-xl font-bold text-gray-800">{{ $roleCounts[$r] ?? 0 }}</p>
        </button>
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="card card-pad">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="relative">
                <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Cari nama atau nomor telepon..."
                       class="input pl-9">
            </div>
            <select wire:model.live="roleFilter" class="input">
                <option value="">Semua Role</option>
                @foreach($roles as $r)
                <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="input">
                <option value="">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="glass-table min-w-full divide-y divide-white/40 text-sm">
                <thead>
                    <tr class="text-left">
                        <th scope="col" class="px-4 py-3">Pengguna</th>
                        <th scope="col" class="px-4 py-3">Role</th>
                        <th scope="col" class="px-4 py-3">Departemen</th>
                        <th scope="col" class="px-4 py-3">Plant</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/40">
                    @forelse($users as $u)
                    <tr class="transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-green-600 to-green-700 text-xs font-bold text-white">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-800 truncate">
                                        {{ $u->name }}
                                        @if($u->id === auth()->id())
                                        <span class="ml-1 text-[10px] font-normal text-gray-400">(Anda)</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $u->phone_number }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge ring-1 {{ $roleBadge[$u->role] ?? 'bg-gray-100 text-gray-600 ring-gray-200' }}">
                                {{ ucfirst($u->role) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $u->department ? ($deptLabel[$u->department] ?? ucfirst($u->department)) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $u->plant_id }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $u->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $u->status === 'active' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                {{ ucfirst($u->status) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" wire:click="openEdit({{ $u->id }})"
                                        title="Ubah" class="btn-icon hover:text-teal-600">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" wire:click="resetPassword({{ $u->id }})"
                                        wire:confirm="Reset password {{ $u->name }}? Password lama tidak akan berlaku lagi."
                                        title="Reset password" class="btn-icon hover:text-amber-600">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button type="button" wire:click="toggleStatus({{ $u->id }})"
                                        title="{{ $u->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}"
                                        class="btn-icon">
                                    <i class="fas {{ $u->status === 'active' ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                </button>
                                <button type="button" wire:click="delete({{ $u->id }})"
                                        wire:confirm="Hapus user {{ $u->name }}? Tindakan ini permanen."
                                        title="Hapus" class="btn-icon hover:text-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" wire:click="delete({{ $u->id }})"
                                        wire:confirm="Hapus user {{ $u->name }}? Tindakan ini permanen."
                                        title="Hapus" class="btn-icon hover:text-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                            <i class="fas fa-user-slash text-2xl mb-2 text-gray-300"></i>
                            <p>Tidak ada user yang cocok dengan filter.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="border-t border-white/40 px-4 py-3">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Create / Edit --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="closeModal"></div>

        <div class="glass glass-sheen relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto shadow-glass-lg">
            <div class="flex items-center justify-between border-b border-white/50 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas {{ $editingId ? 'fa-user-pen' : 'fa-user-plus' }} text-green-600 mr-2"></i>
                    {{ $editingId ? 'Ubah User' : 'Tambah User Baru' }}
                </h3>
                <button type="button" wire:click="closeModal" class="rounded-xl p-1.5 text-gray-400 transition hover:bg-white/60 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4 p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="um-name" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                        <input id="um-name" type="text" wire:model="name" class="input @error('name') border-red-400 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="um-phone" class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon (login)</label>
                        <input id="um-phone" type="text" wire:model="phone_number" inputmode="numeric" placeholder="62812xxxxxxx"
                               class="input @error('phone_number') border-red-400 @enderror">
                        @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="um-role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="um-role" wire:model.live="role" class="input @error('role') border-red-400 @enderror">

                            @foreach($roles as $r)
                            <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="um-plant" class="block text-sm font-medium text-gray-700 mb-1">Plant ID</label>
                        <input id="um-plant" type="text" wire:model="plant_id" class="input @error('plant_id') border-red-400 @enderror">
                        @error('plant_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    @if(in_array($role, ['operator', 'asisten'], true))
                    <div>
                        <label for="um-dept" class="block text-sm font-medium text-gray-700 mb-1">Departemen</label>
                        <select id="um-dept" wire:model="department" class="input @error('department') border-red-400 @enderror">
                            <option value="">— Pilih —</option>
                            @foreach($departments as $d)
                            <option value="{{ $d }}">{{ ucfirst($d) }}</option>
                            @endforeach
                        </select>
                        @error('department')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @else
                    <div class="flex items-end">
                        <div class="w-full rounded-2xl bg-white/60 px-3 py-2.5 text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>Role ini tidak memakai departemen.
                        </div>
                    </div>
                    @endif

                    <div>
                        <label for="um-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="um-status" wire:model="status" class="input">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="um-password" class="block text-sm font-medium text-gray-700 mb-1">
                            Password {{ $editingId ? '(kosongkan bila tidak diubah)' : '' }}
                        </label>
                        <div class="flex gap-2" x-data="{ showPw: @js($showPassword) }">
                            <div class="relative flex-1">
                                <input id="um-password" :type="showPw ? 'text' : 'password'" wire:model="password"
                                       autocomplete="new-password"
                                       class="input font-mono pr-10 @error('password') border-red-400 @enderror">
                                <button type="button" @click="showPw = !showPw"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:text-gray-600"
                                        :aria-label="showPw ? 'Sembunyikan password' : 'Tampilkan password'">
                                    <i class="fas" :class="showPw ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                            <button type="button" wire:click="generatePassword"
                                    class="btn-ghost shrink-0 px-3"
                                    title="Generate password acak">
                                <i class="fas fa-dice"></i>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @if($editingId)
                        <p class="mt-1 text-xs text-gray-400">Biarkan kosong untuk mempertahankan password lama.</p>
                        @endif
                    </div>
                </div>                            <div class="flex items-center justify-end gap-2 border-t border-white/50 pt-4">
                                <button type="button" wire:click="closeModal" class="btn-ghost">
                    <button type="button" wire:click="closeModal" class="btn-ghost">
                        Batal
                    </button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <i class="fas fa-save" wire:loading.remove wire:target="save"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="save"></i>
                        {{ $editingId ? 'Simpan Perubahan' : 'Buat User' }}
                    </button>
                </div>            </form>
        </div>

    </div>
    @endif
</div>
