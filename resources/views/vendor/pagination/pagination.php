@if ($articles->count())
<p>{{ __('pagination.showing_entries', [
        'first' => $articles->firstItem(),
        'last' => $articles->lastItem(),
        'total' => $articles->total(),
    ]) }}</p>
@endif