<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios\Concerns;

use Spatie\Permission\Models\Role;

trait TieneRolesDeUsuario
{
    protected function esSoloAdmin(): bool
    {
        $adminId = self::idDelRolAdmin();
        $roles = array_map(static fn (mixed $id): int => (int) $id, $this->rolesSeleccionados);

        return $adminId > 0 && $roles !== [] && array_diff($roles, [$adminId]) === [];
    }

    protected function incluyeAdmin(): bool
    {
        $adminId = self::idDelRolAdmin();
        $roles = array_map(static fn (mixed $id): int => (int) $id, $this->rolesSeleccionados);

        return $adminId > 0 && in_array($adminId, $roles, true);
    }

    private static function idDelRolAdmin(): int
    {
        $id = Role::where('name', 'admin')->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    protected function cambioDeRegimen(): bool
    {
        return $this->usuario !== null
            && ($this->usuario->regimen ?? '') !== ''
            && $this->regimen !== (string) $this->usuario->regimen;
    }

    public function updatedRegimen(): void
    {
        if ($this->cambioDeRegimen()) {
            $this->turno = '';
        }
    }
}
