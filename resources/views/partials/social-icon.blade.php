<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
@if($platform==='youtube')<rect x="2" y="5" width="20" height="14" rx="5"/><path d="m10 9 6 3-6 3z" fill="currentColor"/>
@elseif($platform==='telegram')<path d="m2 11 20-8-5 18-6-6-4 3v-6l11-6-9 8z"/>
@elseif($platform==='whatsapp')<path d="M5 19 2 22l1-7a10 10 0 1 1 3 5"/><path d="M8 7c-2 4 3 9 7 9l2-3-3-2-1 2-3-3 1-1z"/>
@elseif($platform==='arattai')<path d="M4 4h16v13H9l-5 4z"/><path d="m8 14 4-7 4 7m-6-3h4"/>
@else<path d="M5 2h9l5 5v15H5zM14 2v6h5"/><text x="7" y="17" font-size="6" stroke="none" fill="currentColor">PDF</text>@endif
</svg>
