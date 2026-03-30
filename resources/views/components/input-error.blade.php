@props(['messages'])

@php
    $flattenErrorMessages = static function (mixed $raw): array {
        if ($raw === null || $raw === false || $raw === '') {
            return [];
        }
        if ($raw instanceof \Illuminate\Support\MessageBag) {
            return array_values(array_filter(array_map('strval', $raw->all())));
        }
        if ($raw instanceof \Illuminate\Contracts\Support\MessageProvider) {
            return array_values(array_filter(array_map('strval', $raw->getMessageBag()->all())));
        }
        if ($raw instanceof \Illuminate\Support\Collection) {
            $raw = $raw->all();
        }
        if (! is_array($raw)) {
            return [ (string) $raw ];
        }
        $out = [];
        $walk = static function (mixed $item) use (&$out, &$walk): void {
            if ($item === null || $item === false || $item === '') {
                return;
            }
            if ($item instanceof \Illuminate\Support\MessageBag) {
                foreach ($item->all() as $m) {
                    $out[] = (string) $m;
                }

                return;
            }
            if (is_array($item)) {
                foreach ($item as $sub) {
                    $walk($sub);
                }

                return;
            }
            $out[] = (string) $item;
        };
        foreach ($raw as $item) {
            $walk($item);
        }

        return array_values(array_filter($out, static fn (string $s) => $s !== ''));
    };

    $errorLines = $flattenErrorMessages($messages ?? null);
@endphp

@if (count($errorLines) > 0)
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
        @foreach ($errorLines as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
