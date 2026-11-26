<x-ui.action-menu :actions="[
    [
        'label'   => 'Edit',
        'icon'    => 'pencil',
        'url'     => route('academics.subjects.edit', $s->id),
        'variant' => 'primary',
    ],
    [
        'label'   => $s->isActive() ? 'Arsipkan' : 'Aktifkan',
        'icon'    => $s->isActive() ? 'archive' : 'archive-restore',
        'url'     => route('academics.subjects.toggle-status', $s->id),
        'method'  => 'patch',
        'target'  => '#subjects-container',
        'swap'    => 'outerHTML',
        'variant' => 'warning',
    ],
    [
        'label'   => 'Hapus',
        'icon'    => 'trash-2',
        'url'     => route('academics.subjects.confirm-delete', $s->id),
        'variant' => 'danger',
        'divider' => true,
    ],
]" />