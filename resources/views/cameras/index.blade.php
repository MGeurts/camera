@extends('layouts.app')
@section('title', 'Camera Dashboard')

@section('head')
    @vite(['resources/css/index.css'])

    {{-- Fullscreen overlay — direct child of

    <body> --}}
        <div id="fs-overlay">
            <div id="fs-hud">
                <div class="fs-hud-left">
                    <span class="fs-hud-rec"><span class="fs-hud-dot"></span>LIVE</span>
                    <span id="fs-hud-count"></span>
                </div>
                <span id="fs-hud-clock"></span>
            </div>
            <div id="fs-grid"></div>
        </div>
        <button id="fs-quit" onclick="exitFullscreen()">✕ QUIT FULLSCREEN</button>
@endsection

    @section('content')
        <div class="page-header" id="page-header">
            <div class="page-title">
                LIVE FEEDS
                <small>{{ $cameras->count() }} CAMERA{{ $cameras->count() !== 1 ? 'S' : '' }} CONFIGURED</small>
            </div>
            <div class="page-actions">
                <button class="btn" id="refresh-all-btn" onclick="refreshAll()">↻ REFRESH ALL</button>
                <button class="btn" onclick="enterFullscreen()" title="Fullscreen">⛶ FULLSCREEN</button>
            </div>
        </div>

        <div class="toolbar" id="toolbar">
            <span class="toolbar-label">COLS:</span>
            @for ($c = 1; $c <= 6; $c++)
                <button class="col-btn" id="col-btn-{{ $c }}" onclick="setColumns({{ $c }}, true)">{{ $c }}</button>
            @endfor
            <button class="col-btn" id="col-btn-auto" onclick="setColumns('auto', true)">AUTO</button>
            <div class="tb-divider"></div>
            <div class="refresh-toggle">
                <span class="toolbar-label">REFRESH:</span>
                <label class="toggle-sw">
                    <input type="checkbox" checked onchange="toggleAutoRefresh(this.checked)">
                    <div class="toggle-track"></div>
                    <div class="toggle-thumb"></div>
                </label>
                <select class="rate-select" id="rate-select" onchange="setRefreshRate(parseInt(this.value))">
                    <option value="1000">1s</option>
                    <option value="2000">2s</option>
                    <option value="3000" selected>3s</option>
                    <option value="5000">5s</option>
                    <option value="10000">10s</option>
                    <option value="30000">30s</option>
                    <option value="60000">1m</option>
                </select>
                <button class="rate-reset-btn" id="rate-reset-btn" onclick="resetRefreshRate()" disabled>↺ RESET</button>
            </div>
        </div>

        <div class="camera-grid" id="camera-grid">
            @forelse ($cameras as $camera)
                <div class="camera-card" id="card-{{ $camera['id'] }}" data-cam-id="{{ $camera['id'] }}">

                    <div class="camera-feed">
                        <div class="feed-spinner" id="spinner-{{ $camera['id'] }}">
                            <div class="spinner-ring"></div>
                        </div>
                        <img id="feed-a-{{ $camera['id'] }}" src="{{ route('cameras.snapshot', $camera['id']) }}?t={{ time() }}" alt="{{ $camera['name'] }}" style="z-index:2"
                            onload="onFirstLoad({{ $camera['id'] }})" />
                        <img id="feed-b-{{ $camera['id'] }}" alt="{{ $camera['name'] }}" style="z-index:1" />
                        <div class="feed-overlay">
                            <span class="cam-id-tag">{{ strtoupper($camera['name']) }}</span>
                            <span class="cam-ts-tag" id="ts-{{ $camera['id'] }}">--:--:--</span>
                            <a class="expand-btn" href="{{ route('cameras.show', $camera['id']) }}" onclick="event.stopPropagation()">⤢</a>
                        </div>
                    </div>

                    <div class="camera-info">
                        <div style="overflow:hidden;min-width:0;">
                            <div class="cam-name">{{ $camera['name'] }}</div>
                            <div class="cam-meta">{{ $camera['location'] ?? '' }}{{ !empty($camera['location']) ? ' · ' : '' }}{{ $camera['ip'] }}</div>
                        </div>
                        <span class="badge badge-unknown" id="status-{{ $camera['id'] }}">● CHECK</span>
                    </div>

                </div>
            @empty
                <div class="empty-state">
                    <h2>NO CAMERAS CONFIGURED</h2>
                    <p>Add your Hikvision cameras in <code>config/cameras.php</code><br>
                        or set environment variables in your <code>.env</code> file.</p>
                </div>
            @endforelse
        </div>
    @endsection

    @section('scripts')
        <script>
            window.HIK = {
                cameras: @json($cameras),
                refreshMs: {{ $refresh }},
            };
        </script>
        @vite(['resources/js/index.js'])
    @endsection
