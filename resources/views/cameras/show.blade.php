@extends('layouts.app')
@section('title', 'Camera Dashboard - ' . $camera['name'])

@section('head')
    @vite(['resources/css/show.css'])
@endsection

@section('content')
    <div class="back-nav">
        <a class="back-link" href="{{ route('cameras.index') }}">← GRID</a>
        <span class="breadcrumb">/ <span>{{ strtoupper($camera['name']) }}</span></span>
    </div>

    <div class="single-layout">

        {{-- Feed column --}}
        <div class="feed-col">
            <div class="feed-wrap">
                <img class="feed-img" id="feed-a"
                    src="{{ route('cameras.snapshot', $camera['id']) }}?t={{ time() }}"
                    alt="{{ $camera['name'] }}"
                    style="z-index:2; position:relative;" />
                <img class="feed-img" id="feed-b"
                    alt="{{ $camera['name'] }}"
                    style="z-index:1; position:absolute; inset:0; width:100%; height:100%;" />
                <div class="feed-hud">
                    <span class="hud-tag">CAM {{ str_pad($camera['id'], 2, '0', STR_PAD_LEFT) }} · {{ strtoupper($camera['name']) }}</span>
                    <span class="hud-ts" id="main-ts">--:--:--</span>
                </div>
            </div>
            <div class="refresh-bar">
                <div class="refresh-bar-fill" id="refresh-bar"></div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="sidebar">

            <div class="info-panel">
                <div class="panel-header">STREAM</div>
                <div class="panel-body">
                    <div class="info-row">
                        <span class="info-label">Method</span>
                        <span class="info-value accent">JPEG Snapshot</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Refresh</span>
                        <span class="info-value" id="refresh-rate-label">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last frame</span>
                        <span class="info-value" id="last-frame">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value ok" id="stream-status">LOADING</span>
                    </div>
                </div>
            </div>

            <div class="info-panel">
                <div class="panel-header">CAMERA</div>
                <div class="panel-body">
                    <div class="info-row"><span class="info-label">Name</span><span class="info-value">{{ $camera['name'] }}</span></div>
                    <div class="info-row"><span class="info-label">IP</span><span class="info-value accent">{{ $camera['ip'] }}</span></div>
                    <div class="info-row"><span class="info-label">HTTP Port</span><span class="info-value">{{ $camera['http_port'] }}</span></div>
                    <div class="info-row"><span class="info-label">RTSP Port</span><span class="info-value">{{ $camera['rtsp_port'] }}</span></div>
                    <div class="info-row"><span class="info-label">Channel</span><span class="info-value">{{ $camera['channel'] }}</span></div>
                    <div class="info-row"><span class="info-label">Stream</span><span class="info-value">{{ strtoupper($camera['stream'] ?? 'SUB') }}</span></div>
                    @if(!empty($camera['location']))
                        <div class="info-row"><span class="info-label">Location</span><span class="info-value">{{ $camera['location'] }}</span></div>
                    @endif
                </div>
            </div>

            <div class="info-panel" id="device-panel" style="display:none">
                <div class="panel-header">DEVICE INFO</div>
                <div class="panel-body" id="device-body"></div>
            </div>

            <div class="info-panel">
                <div class="panel-header">CONTROLS</div>
                <div class="controls-grid">
                    <button class="ctrl-btn" onclick="refreshNow()">↻ Refresh</button>
                    <button class="ctrl-btn" id="save-btn" onclick="saveSnapshot()">⤓ Save</button>
                    <button class="ctrl-btn" onclick="togglePause()" id="pause-btn">⏸ Pause</button>
                    <button class="ctrl-btn" onclick="loadDeviceInfo()">ℹ Device</button>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('scripts')
    <script>
        window.HIK = {
            camId:     {{ $camera['id'] }},
            refreshMs: {{ $refresh }},
            httpPort:  {{ $camera['http_port'] }},
        };
    </script>

    @vite(['resources/js/show.js'])
@endsection
