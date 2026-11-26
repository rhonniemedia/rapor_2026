{{-- File: resources/views/pages/academics/relations/curriculum/_actions.blade.php --}}
{{-- Default x-ui.action-menu: method=get, target=#modal-container, swap=innerHTML (cocok untuk Edit & Hapus). --}}
<x-ui.action-menu :actions="[
    [
        'label'   => 'Edit',
        'icon'    => 'pencil',
        'url'     => route('rapor.curricula.edit', $c->id),
        'variant' => 'primary',
    ],
    [
        'label'   => $c->isActive() ? 'Arsipkan' : 'Aktifkan',
        'icon'    => $c->isActive() ? 'archive' : 'archive-restore',
        'url'     => route('rapor.curricula.toggle-status', $c->id),
        'method'  => 'patch',
        'target'  => '#curricula-container',
        'swap'    => 'outerHTML',
        'variant' => 'warning',
    ],
    [
        'label'   => 'Hapus',
        'icon'    => 'trash-2',
        'url'     => route('rapor.curricula.confirm-delete', $c->id),
        'variant' => 'danger',
        'divider' => true,
    ],
]" />