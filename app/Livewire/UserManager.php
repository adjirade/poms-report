<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manajemen user untuk role developer (superadmin).
 *
 * Fitur: daftar + cari + filter, tambah user, ubah (role/departemen/plant/
 * status), reset password (dibuat acak & ditampilkan sekali), aktif/nonaktif,
 * dan hapus. Semua aksi dilindungi gate `manage-users`.
 */
class UserManager extends Component
{
    use WithPagination;

    /** Role yang valid (samakan dengan enum kolom users.role). */
    public const ROLES = ['operator', 'asisten', 'askep', 'manager', 'hq_admin', 'developer'];

    /** Departemen yang valid (enum kolom users.department). */
    public const DEPARTMENTS = ['proses', 'maintenance', 'lab'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $roleFilter = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    // Field form
    public string $name = '';

    public string $phone_number = '';

    public string $role = 'operator';

    public string $department = '';

    public string $plant_id = '';

    public string $status = 'active';

    public string $password = '';

    public bool $showPassword = false;

    /** Password acak hasil reset / generate — ditampilkan sekali ke admin. */
    public ?string $generatedPassword = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $this->plant_id = (string) config('poms.plant_id', 'PKS_01');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        $phone = Rule::unique('users', 'phone_number');
        if ($this->editingId) {
            $phone = $phone->ignore($this->editingId);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/', $phone],
            'role' => ['required', Rule::in(self::ROLES)],
            'department' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->role, ['operator', 'asisten'], true)),
                Rule::in(array_merge(self::DEPARTMENTS, [''])),
            ],
            'plant_id' => ['required', 'string', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => $this->editingId
                ? ['nullable', 'string', Password::min(8)]
                : ['required', 'string', Password::min(8)],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone_number.required' => 'Nomor telepon wajib diisi.',
            'phone_number.unique' => 'Nomor telepon sudah dipakai user lain.',
            'phone_number.regex' => 'Nomor telepon hanya boleh berisi angka.',
            'department.required_if' => 'Departemen wajib dipilih untuk operator/asisten.',
            'password.required' => 'Password wajib diisi untuk user baru.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }

    public function render()
    {
        $users = User::query()
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term);
                });
            })
            ->when($this->roleFilter !== '', fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByRaw(
                "CASE role WHEN 'developer' THEN 0 WHEN 'hq_admin' THEN 1 WHEN 'manager' THEN 2"
                ." WHEN 'askep' THEN 3 WHEN 'asisten' THEN 4 ELSE 5 END"
            )
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.user-manager', [
            'users' => $users,
            'roles' => self::ROLES,
            'departments' => self::DEPARTMENTS,
            'roleCounts' => User::query()
                ->selectRaw('role, count(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role'),
        ]);
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->phone_number = '';
        $this->role = 'operator';
        $this->department = 'proses';
        $this->plant_id = (string) config('poms.plant_id', 'PKS_01');
        $this->status = 'active';
        $this->password = '';
        $this->showPassword = true;
        $this->generatedPassword = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetValidation();
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->phone_number = $user->phone_number;
        $this->role = $user->role;
        $this->department = (string) ($user->department ?? '');
        $this->plant_id = $user->plant_id;
        $this->status = $user->status;
        $this->password = '';
        $this->showPassword = false;
        $this->generatedPassword = null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->password = '';
        $this->generatedPassword = null;
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(12);
        $this->showPassword = true;
    }

    public function save(): void
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $validated = $this->validate();

        // Departemen hanya relevan untuk operator/asisten.
        if (! in_array($validated['role'], ['operator', 'asisten'], true)) {
            $validated['department'] = null;
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $this->guardLastDeveloperOnRoleChange($user, $validated['role']);

            if (empty($validated['password'])) {
                unset($validated['password']);
            }

            $user->update($validated);
            session()->flash('success', "User \"{$user->name}\" berhasil diperbarui.");
        } else {
            $user = User::create($validated);
            session()->flash('success', "User \"{$user->name}\" berhasil dibuat.");
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menghapus akun sendiri.');

            return;
        }

        if ($user->role === 'developer' && User::where('role', 'developer')->count() <= 1) {
            session()->flash('error', 'Developer terakhir tidak dapat dihapus.');

            return;
        }

        $name = $user->name;
        $user->delete();
        session()->flash('success', "User \"{$name}\" telah dihapus.");
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');

            return;
        }

        if ($user->status === 'active'
            && $user->role === 'developer'
            && User::where('role', 'developer')->where('status', 'active')->count() <= 1) {
            session()->flash('error', 'Developer aktif terakhir tidak dapat dinonaktifkan.');

            return;
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        session()->flash('success', "Status \"{$user->name}\" diubah menjadi ".ucfirst($user->status).'.');
    }

    public function resetPassword(int $id): void
    {
        abort_unless(Gate::allows('manage-users'), 403);

        $user = User::findOrFail($id);
        $plain = Str::password(12);

        $user->update(['password' => $plain]);

        $this->generatedPassword = $plain;
        session()->flash('success', "Password \"{$user->name}\" berhasil direset.");
    }

    /**
     * Cegah sistem kehilangan developer terakhir saat role diubah.
     */
    protected function guardLastDeveloperOnRoleChange(User $user, string $newRole): void
    {
        if ($user->role === 'developer'
            && $newRole !== 'developer'
            && User::where('role', 'developer')->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Developer terakhir tidak dapat diubah rolenya.',
            ]);
        }
    }
}
