<svg class="fc-ui-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@if(($direction ?? 'up-right') === 'down')<path d="M12 4v16m-6-6 6 6 6-6"/>@elseif(($direction ?? '') === 'right')<path d="M4 12h16m-6-6 6 6-6 6"/>@else<path d="M6 18 18 6M6 6h12v12"/>@endif
</svg>
