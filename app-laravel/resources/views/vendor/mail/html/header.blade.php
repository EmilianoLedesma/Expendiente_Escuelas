@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('img/layout_set_logo.png') }}" class="logo" width="460" alt="Gobierno del Estado de Querétaro">
</a>
<p class="header-titulo">{!! $slot !!}</p>
</td>
</tr>
