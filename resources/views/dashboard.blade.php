@extends('layouts.app')

@section('content')
    <!-- 01. TOP SYSTEM TELEMETRY BAR -->
    <header class="top-telemetry-bar">
        <div class="brand-lockup">
            <span class="brand-badge">GS//AI v1.1</span>
            <h1 class="brand-title">GAMESENSE AI // TACTICAL COMMAND</h1>
        </div>

        <div></div>

        <div class="telemetry-strip">
            <div class="telemetry-pill" title="Optimized Hardware Profile">
                <span class="dot-online"></span>
                <span>VM: <strong>{{ $telemetry['vm_profile'] }}</strong></span>
            </div>
            <div class="telemetry-pill" title="Configured AI Model in config/services.php">
                <span class="{{ $telemetry['api_configured'] ? 'dot-online' : 'dot-standby' }}"></span>
                <span>MODEL: <strong>{{ $telemetry['ai_model'] }}</strong></span>
            </div>
            <div class="telemetry-pill">
                <span>CACHE RATIO: <strong id="metric-cache-ratio">{{ $telemetry['cache_hit_ratio'] }}%</strong></span>
            </div>
            <div class="telemetry-pill">
                <span>API CALLS SAVED: <strong id="metric-saved-calls">{{ $telemetry['cache_saved_calls'] }}</strong></span>
            </div>
            <div class="telemetry-pill">
                <span>PHP RAM: <strong>{{ $telemetry['memory_usage_mb'] }} MB</strong></span>
            </div>
        </div>
    </header>

    <!-- 02. 12-COLUMN PURE CSS3 GRID COMMAND DECK -->
    <main class="command-deck-wrapper">
        <div class="command-grid-12">

            <!-- LEFT COLUMN (5 COLUMNS): TARGET GAME SELECTION + CURRENT STATE TELEMETRY -->
            <section class="deck-col-left">

                <!-- MODULE 01: HIGH-CONTRAST SQUARE GAME CARDS GRID -->
                <article class="brutal-module">
                    <div class="brutal-module-header">
                        <h2 class="brutal-module-title">01 // SELECT TARGET GAME</h2>
                        <span class="brutal-module-tag">GRID 2x2</span>
                    </div>
                    <div class="brutal-module-body">
                        <div class="games-card-grid" id="games-grid">
                            @foreach($games as $index => $game)
                                <button
                                    type="button"
                                    class="game-card-btn {{ $index === 0 ? 'is-selected' : '' }}"
                                    data-game-id="{{ $game->id }}"
                                    data-game-slug="{{ $game->slug }}"
                                    data-game-title="{{ $game->title }}"
                                    data-game-code="{{ $game->short_code }}"
                                    data-game-genre="{{ $game->genre }}"
                                    data-game-resources='@json($game->default_resources ?? [])'
                                >
                                    <div class="game-card-top">
                                        <span class="game-short-code">{{ $game->short_code }}</span>
                                        <span class="game-engine-badge">{{ $game->engine_name }}</span>
                                    </div>
                                    <div>
                                        <div class="game-card-name">{{ $game->title }}</div>
                                        <div class="game-card-genre">{{ $game->genre }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </article>

                <!-- MODULE 02: TACTICAL TELEMETRY PARAMETERS & QUEUE/CACHE DISPATCH -->
                <article class="brutal-module">
                    <div class="brutal-module-header">
                        <h2 class="brutal-module-title">02 // CURRENT STATE TELEMETRY</h2>
                        <span class="brutal-module-tag" id="selected-game-indicator">
                            {{ $games->first()?->short_code ?? 'VIC-03' }}
                        </span>
                    </div>
                    <div class="brutal-module-body">
                        <form id="tactical-form" class="tactical-form" action="{{ route('api.tactics.analyze') }}" method="POST">
                            @csrf
                            <input type="hidden" name="game_id" id="input-game-id" value="{{ $games->first()?->id ?? 1 }}">

                            <div class="form-row-grid-2">
                                <div class="field-group">
                                    <label class="field-label" for="input-category-id">
                                        <span>TACTICAL CATEGORY</span>
                                        <span class="code-hint">MYSQL FK</span>
                                    </label>
                                    <select name="category_id" id="input-category-id" class="brutal-select" required>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" data-code="{{ $category->code_tag }}">
                                                [{{ $category->code_tag }}] {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field-group">
                                    <label class="field-label" for="input-game-phase">
                                        <span>GAME PHASE</span>
                                        <span class="code-hint">STATE</span>
                                    </label>
                                    <select name="game_phase" id="input-game-phase" class="brutal-select" required>
                                        <option value="Early Game (Opening / Year 1)">Early Game (Opening / Year 1)</option>
                                        <option value="Mid Game (Industrialization / Act 2)" selected>Mid Game (Industrialization / Act 2)</option>
                                        <option value="Late Game (Endgame / Pantheon / Honour)">Late Game (Endgame / Pantheon / Honour)</option>
                                        <option value="Acute Crisis / Wipe Risk">Acute Crisis / Wipe Risk</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="input-missing-resource">
                                    <span>MISSING RESOURCE / CRITICAL BOTTLENECK</span>
                                    <span class="code-hint">BOTTLENECK</span>
                                </label>
                                <input
                                    type="text"
                                    name="missing_resource"
                                    id="input-missing-resource"
                                    class="brutal-input"
                                    value="Coal & Iron (+58% above base price)"
                                    placeholder="e.g., Coal & Iron, Soul / Geo, Cash Liquidity ($), Spell Slots..."
                                    required
                                    maxlength="120"
                                >
                                <div class="resource-chips-bar" id="resource-chips-container">
                                    @foreach(($games->first()?->default_resources ?? []) as $resChip)
                                        <button type="button" class="resource-chip-btn" data-resource="{{ $resChip }}">
                                            + {{ $resChip }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="input-user-query">
                                    <span>SITUATION REPORT (TACTICAL PROMPT)</span>
                                    <span class="code-hint">SHA-256 HASHED</span>
                                </label>
                                <textarea
                                    name="user_query"
                                    id="input-user-query"
                                    class="brutal-textarea"
                                    required
                                    placeholder="Describe your current bottleneck, build, boss pattern, or economic deficit..."
                                >My Construction Sectors entered a severe deficit after switching to Iron-Frame Buildings. Coal and Iron prices skyrocketed and my gold reserves will hit zero in 10 weeks.</textarea>
                            </div>

                            <div class="form-row-grid-2">
                                <div class="field-group">
                                    <label class="field-label" for="input-dispatch-mode">
                                        <span>EXECUTION MODE</span>
                                        <span class="code-hint">QUEUE</span>
                                    </label>
                                    <select name="dispatch_mode" id="input-dispatch-mode" class="brutal-select">
                                        <option value="async" selected>Laravel Queue (Async + Polling)</option>
                                        <option value="sync">Direct Execution (Immediate Sync)</option>
                                    </select>
                                </div>

                                <div class="field-group" style="justify-content: flex-end;">
                                    <button type="submit" id="btn-submit-tactic" class="brutal-btn brutal-btn-primary">
                                        <span>RUN TACTICAL ANALYSIS</span>
                                        <span>[ENTER]</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </article>
            </section>

            <!-- RIGHT COLUMN (7 COLUMNS): AI RESPONSE BOX (#FFDE59) + MYSQL TACTICAL LOGS -->
            <section class="deck-col-right">

                <!-- QUEUE PROCESSING BANNER -->
                <div id="queue-loading-banner" class="queue-loading-banner">
                    <span id="queue-status-text">[QUEUE WORKER] PROCESSING TELEMETRY ON GEMINI-3.5-FLASH-LITE...</span>
                    <span id="queue-job-uuid">UUID: --</span>
                </div>

                <!-- MODULE 03: AI RESPONSE BOX (ELECTRIC YELLOW HIGHLIGHT #FFDE59) -->
                <article class="ai-response-box" id="ai-response-box">
                    <div class="ai-response-header">
                        <div class="ai-header-left">
                            <span class="ai-terminal-title">03 // AI RESPONSE TERMINAL</span>
                        </div>
                        <div class="ai-badges-group">
                            <span id="badge-cache-status" class="status-badge cache-hit">
                                {{ ($featuredPayload['cached'] ?? true) ? 'CACHE HIT // ' . ($featuredPayload['response_time_ms'] ?? 2) . 'ms' : 'AI GENERATED' }}
                            </span>
                            <span id="badge-threat-level" class="status-badge threat-critical">
                                THREAT: {{ $featuredPayload['threat_level'] ?? 'CRITICAL' }}
                            </span>
                            <span id="badge-hit-counter" class="status-badge queue-live">
                                HITS: {{ $featuredPayload['hit_count'] ?? 14 }}
                            </span>
                        </div>
                    </div>

                    <div class="ai-response-content">
                        <!-- Metadata Strip -->
                        <div class="ai-meta-strip">
                            <div class="ai-meta-cell">
                                <div class="ai-meta-label">GAME // ENGINE</div>
                                <div class="ai-meta-value" id="out-game-title">
                                    {{ $featuredPayload['game']['title'] ?? 'Victoria 3' }} [{{ $featuredPayload['game']['short_code'] ?? 'VIC-03' }}]
                                </div>
                            </div>
                            <div class="ai-meta-cell">
                                <div class="ai-meta-label">CATEGORY</div>
                                <div class="ai-meta-value" id="out-category-name">
                                    [{{ $featuredPayload['category']['code_tag'] ?? 'ECO-01' }}] {{ $featuredPayload['category']['name'] ?? 'Economy & Resources' }}
                                </div>
                            </div>
                            <div class="ai-meta-cell">
                                <div class="ai-meta-label">MATCH PHASE</div>
                                <div class="ai-meta-value" id="out-game-phase">
                                    {{ $featuredPayload['telemetry']['game_phase'] ?? 'Mid Game (Industrialization / Act 2)' }}
                                </div>
                            </div>
                            <div class="ai-meta-cell">
                                <div class="ai-meta-label">TARGET BOTTLENECK</div>
                                <div class="ai-meta-value" id="out-missing-resource">
                                    {{ $featuredPayload['telemetry']['missing_resource'] ?? 'Coal & Iron (+58%)' }}
                                </div>
                            </div>
                        </div>

                        <!-- Directive Heading -->
                        <div class="ai-directive-banner">
                            <div class="ai-directive-label" id="out-ai-model">
                                ENGINE: {{ $featuredPayload['ai_model'] ?? 'gemini-3.5-flash-lite' }}
                            </div>
                            <h3 class="ai-directive-heading" id="out-tactical-title">
                                {{ $featuredPayload['tactical_title'] ?? 'INDUSTRIAL SHOCK DIRECTIVE // DE-BOTTLENECKING COAL & IRON' }}
                            </h3>
                        </div>

                        <!-- Numbered Tactical Steps -->
                        <ul class="ai-steps-list" id="out-steps-list">
                            @foreach(($featuredPayload['action_steps'] ?? []) as $idx => $step)
                                <li class="ai-step-item">
                                    <span class="ai-step-number">{{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="ai-step-text">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <!-- Loadout / Metrics Grid -->
                        <div class="ai-metrics-grid" id="out-metrics-grid">
                            @foreach(($featuredPayload['loadout_metrics'] ?? []) as $metric)
                                <div class="ai-metric-card">
                                    <div class="ai-metric-card-label">{{ $metric['label'] ?? 'METRIC' }}</div>
                                    <div class="ai-metric-card-value">{{ $metric['value'] ?? 'N/A' }}</div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Projected Outcome -->
                        <div class="ai-outcome-bar">
                            <span class="ai-outcome-tag">PROJECTED OUTCOME</span>
                            <span id="out-expected-outcome">
                                {{ $featuredPayload['expected_outcome'] ?? 'Normalizes input cost below +10% base price and restores GDP growth within 12–18 weeks.' }}
                            </span>
                        </div>

                        <!-- Hidden Raw Markdown Buffer for Export / Clipboard -->
                        <textarea id="raw-markdown-buffer" style="display:none;" readonly>{{ $featuredPayload['raw_markdown_response'] ?? '' }}</textarea>

                        <!-- Export Toolbar & SHA-256 Hash Signature -->
                        <div class="ai-actions-toolbar">
                            <div class="ai-hash-signature" id="out-prompt-hash">
                                SHA-256: {{ isset($featuredPayload['prompt_hash']) ? substr($featuredPayload['prompt_hash'], 0, 20) . '...' : '8f9d2a7c4e1b09f3a6d2...' }}
                            </div>

                            <div class="ai-export-buttons">
                                <button type="button" id="btn-copy-terminal" class="brutal-btn brutal-btn-dark">
                                    <span id="copy-btn-label">COPY DIRECTIVE (.TXT)</span>
                                </button>
                                <button type="button" id="btn-export-file" class="brutal-btn brutal-btn-white">
                                    <span>EXPORT REPORT</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- MODULE 04: CACHED TACTICAL LOGS HISTORY (MYSQL `tactics_logs`) -->
                <article class="brutal-module">
                    <div class="brutal-module-header">
                        <h2 class="brutal-module-title">04 // CACHED TACTICAL LOGS (MYSQL + SHA-256)</h2>
                        <span class="brutal-module-tag">INSTANT REPLAY (&lt; 5ms)</span>
                    </div>
                    <div class="brutal-module-body">
                        <div class="logs-table-wrapper">
                            <table class="brutal-table" id="tactics-logs-table">
                                <thead>
                                    <tr>
                                        <th>GAME</th>
                                        <th>CAT.</th>
                                        <th>BOTTLENECK / DIRECTIVE</th>
                                        <th>HITS</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="tactics-logs-tbody">
                                    @foreach($recentLogs as $log)
                                        <tr data-log-id="{{ $log->id }}">
                                            <td>
                                                <span class="game-short-code">{{ $log->game?->short_code }}</span>
                                            </td>
                                            <td>{{ $log->category?->code_tag }}</td>
                                            <td>
                                                <div><strong>{{ $log->promptHistory?->missing_resource }}</strong></div>
                                                <div style="font-size: 11px; color: #333;">{{ $log->tactical_title }}</div>
                                            </td>
                                            <td>{{ $log->promptHistory?->hit_count ?? 1 }}x</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn-load-log"
                                                    data-load-url="{{ route('api.tactics.show', ['id' => $log->id]) }}"
                                                >
                                                    LOAD
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>

            </section>
        </div>
    </main>
@endsection
