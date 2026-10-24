<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class NavegacionComposer
{
    public function compose(View $view): void
    {

        $usuario = Auth::user();

        if (! $usuario) {
            $view->with([
                'usuario' => null,
                'esAdmin' => false,
                'esRrhh' => false,
                'esJefe' => false,
                'puedeAltaUsuarios' => false,
                'conSidebar' => false,
                'rolLabel' => '',
                'secciones' => [],
                'navItems' => [],
            ]);

            return;
        }

        $esAdmin = $usuario->hasRole('admin');
        $esRrhh = $usuario->hasRole('rrhh');
        $esJefe = $usuario->can('crearTrabajadorPropio', User::class);
        $puedeAltaUsuarios = $usuario->can('puedeCrearAlgo', User::class);

        $view->with([
            'usuario' => $usuario,
            'esAdmin' => $esAdmin,
            'esRrhh' => $esRrhh,
            'esJefe' => $esJefe,
            'puedeAltaUsuarios' => $puedeAltaUsuarios,

            'conSidebar' => $esAdmin || $esRrhh || $esJefe,
            'rolLabel' => $esAdmin ? 'Administración' : ($esRrhh ? 'Recursos Humanos' : 'Jefatura'),
            'secciones' => $this->secciones($usuario, $esAdmin, $esRrhh, $esJefe, $puedeAltaUsuarios),
            'navItems' => $this->navegacionDelTrabajador($esAdmin || $esRrhh || $esJefe),
        ]);
    }

    /**
     * @return array<int, array{label: ?string, items: array<int, array<string, mixed>>}>
     */
    private function secciones(User $usuario, bool $esAdmin, bool $esRrhh, bool $esJefe, bool $puedeAltaUsuarios): array
    {
        $secciones = [[
            'label' => null,
            'items' => [
                ['label' => 'Inicio', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
            ],
        ]];

        $bandejas = [];

        if ($esJefe) {
            $bandejas[] = ['label' => 'Papeletas de mi equipo', 'route' => route('jefe.papeletas.index'), 'active' => request()->routeIs('jefe.*'), 'icon' => 'inbox', 'bandeja' => 'jefe'];
        }

        if ($esRrhh) {
            $bandejas[] = ['label' => 'Papeletas de RR. HH.', 'route' => route('rrhh.papeletas.index'), 'active' => request()->routeIs('rrhh.papeletas.*'), 'icon' => 'clipboard', 'bandeja' => 'rrhh'];
        }

        if ($esAdmin) {
            $bandejas[] = ['label' => 'Todas las papeletas', 'route' => route('admin.papeletas.index'), 'active' => request()->routeIs('admin.papeletas.*'), 'icon' => 'document'];
        }

        if ($esRrhh || $esAdmin) {
            $bandejas[] = ['label' => 'Abandonos', 'route' => route('rrhh.abandonos.index'), 'active' => request()->routeIs('rrhh.abandonos.*'), 'icon' => 'inbox'];
        }

        if ($usuario->hasRole('trabajador')) {
            $bandejas[] = ['label' => 'Mis papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.*'), 'icon' => 'document'];
        }

        if ($bandejas) {
            $secciones[] = ['label' => 'Bandejas', 'items' => $bandejas];
        }

        $turnosJefe = [];

        if ($esAdmin) {
            $turnosJefe[] = ['label' => 'Programar por usuario', 'route' => route('turnos.administrar'), 'active' => request()->routeIs('turnos.administrar'), 'icon' => 'users'];
        }

        if ($esJefe && ! $esAdmin) {

            $turnosJefe[] = ['label' => 'Calendario del equipo', 'route' => route('turnos.calendario.equipo'), 'active' => request()->routeIs('turnos.calendario.equipo'), 'icon' => 'calendar'];
        }

        if ($turnosJefe) {
            $secciones[] = ['label' => 'Turnos', 'items' => $turnosJefe];
        }

        $reportes = [
            ['label' => 'Resumen de papeletas', 'route' => route('reportes.resumen-papeletas'), 'active' => request()->routeIs('reportes.resumen-papeletas'), 'icon' => 'chart'],
            ['label' => 'Sustentos', 'route' => route('reportes.sustentos'), 'active' => request()->routeIs('reportes.sustentos'), 'icon' => 'clipboard'],
        ];

        if ($esRrhh || $esAdmin) {
            $reportes[] = ['label' => 'Decisiones de RR. HH.', 'route' => route('reportes.decisiones-rrhh'), 'active' => request()->routeIs('reportes.decisiones-rrhh'), 'icon' => 'clipboard'];
        }

        $secciones[] = ['label' => 'Reportes', 'items' => $reportes];

        $personas = [];

        if ($puedeAltaUsuarios) {
            $personas[] = ['label' => 'Trabajadores', 'route' => route('usuarios.index'), 'active' => request()->routeIs('usuarios.*'), 'icon' => 'users'];
        }

        if ($esAdmin || $esJefe) {
            $personas[] = ['label' => 'Organigrama', 'route' => route('organigrama.index'), 'active' => request()->routeIs('organigrama.*'), 'icon' => 'share'];
        }

        if ($esAdmin) {
            $personas[] = ['label' => 'Cuentas de usuario', 'route' => route('usuarios-admin.index'), 'active' => request()->routeIs('usuarios-admin.*'), 'icon' => 'user-circle'];
        }

        if ($personas) {
            $secciones[] = ['label' => 'Personas', 'items' => $personas];
        }

        if ($esAdmin) {
            $secciones[] = ['label' => 'Catálogos', 'items' => [
                ['label' => 'Sedes', 'route' => route('sedes.index'), 'active' => request()->routeIs('sedes.*'), 'icon' => 'map-pin'],
                ['label' => 'Unidades orgánicas', 'route' => route('unidades-organicas.index'), 'active' => request()->routeIs('unidades-organicas.*'), 'icon' => 'building'],
                ['label' => 'Motivos', 'route' => route('motivos.index'), 'active' => request()->routeIs('motivos.*'), 'icon' => 'tag'],
                ['label' => 'Turnos', 'route' => route('turnos.index'), 'active' => request()->routeIs('turnos.index', 'turnos.crear', 'turnos.editar'), 'icon' => 'clock'],
            ]];

            $secciones[] = ['label' => 'Sistema', 'items' => [
                ['label' => 'Configuraciones', 'route' => route('configuraciones.index'), 'active' => request()->routeIs('configuraciones.*'), 'icon' => 'cog'],
            ]];
        }

        return $secciones;
    }

    private function navegacionDelTrabajador(bool $conSidebar = false): array
    {
        $panel = $conSidebar
            ? [['label' => 'Panel', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home']]
            : [];

        return [
            ...$panel,
            ['label' => 'Papeletas', 'route' => route('trabajador.papeletas.index'), 'active' => request()->routeIs('trabajador.papeletas.index', 'trabajador.papeletas.show'), 'icon' => 'document'],
            ['label' => 'Nueva', 'route' => route('trabajador.papeletas.create'), 'active' => request()->routeIs('trabajador.papeletas.create'), 'icon' => 'plus-circle'],
            ['label' => 'Calendario', 'route' => route('turnos.calendario.individual'), 'active' => request()->routeIs('turnos.calendario.individual'), 'icon' => 'calendar'],
            ['label' => 'Perfil', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.show'), 'icon' => 'user-circle'],
        ];
    }
}
