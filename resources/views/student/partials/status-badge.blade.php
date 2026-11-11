@php
    $statusClasses = [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
    ];
@endphp

<span class="badge {{ $statusClasses[$recording->status] ?? 'badge-neutral' }}">{{ $recording->statusLabel() }}</span>
