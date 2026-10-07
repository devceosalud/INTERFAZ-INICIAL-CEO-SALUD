@php
    $moduleActive = collect($activePatterns)->contains(fn ($pattern) => request()->routeIs($pattern));
@endphp

<li class="erp-nav__module {{ $moduleActive ? 'is-active' : '' }}" data-erp-nav-module>
    <button type="button" class="erp-nav__trigger" id="erp-nav-{{ $key }}"
        data-erp-nav-trigger aria-expanded="false" aria-controls="erp-nav-menu-{{ $key }}">
        <span>{{ $label }}</span>
        <span class="erp-nav__chevron" aria-hidden="true"></span>
    </button>

    <ul class="erp-nav__dropdown" id="erp-nav-menu-{{ $key }}" aria-labelledby="erp-nav-{{ $key }}">
        @foreach ($items as $item)
            @php
                $itemActive = collect($item['patterns'])->contains(fn ($pattern) => request()->routeIs($pattern));
            @endphp
            <li>
                <a href="{{ route($item['route']) }}" class="{{ $itemActive ? 'is-active' : '' }}"
                    @if ($itemActive) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</li>
