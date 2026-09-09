<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://pronosticup.es/logo.png" alt="PronostiCup Liga" width="180" style="display: block; margin: 0 auto;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>