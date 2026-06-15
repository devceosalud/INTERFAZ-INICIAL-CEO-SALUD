@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')

{{--
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
--}}
<img src="https://ceosalud.pe/wp-content/uploads/2026/01/ceosalud.pe_pequeno-01.png" class="logo" alt="Laravel Logo">

@else
{{ $slot }}
@endif
</a>
</td>
</tr>
