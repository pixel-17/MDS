<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios\Concerns;

use Spatie\Permission\Models\Role;


trait TieneRolesDeUsuario
{
    
    protected function esSoloAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');
        $roles = array_map('intval', $this->rolesSeleccionados);

        return $adminId > 0 && $roles !== [] && array_diff($roles, [$adminId]) === [];
    }

    
    protected function incluyeAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');

        return $adminId > 0 && in_array($adminId, array_map('intval', $this->rolesSeleccionados), true);
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
