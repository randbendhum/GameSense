/**
 * ==========================================================================
 * GAMESENSE AI — REACTIVE FRONTEND CONTROLLER (VANILLA JS / ZERO FRAMEWORKS)
 * Handles:
 * 1. Square Game Card Grid selection & Quick-Fill Resource Chips
 * 2. Asynchronous Dispatch to Laravel API Controller (/api/tactics/analyze)
 * 3. Laravel Database Queue Polling (/api/tactics/status/{uuid})
 * 4. AI Response Box (#FFDE59) rendering + Clipboard Copy & .TXT Export
 * ==========================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    const gameCards = document.querySelectorAll('.game-card-btn');
    const inputGameId = document.getElementById('input-game-id');
    const selectedGameIndicator = document.getElementById('selected-game-indicator');
    const resourceChipsContainer = document.getElementById('resource-chips-container');
    const inputMissingResource = document.getElementById('input-missing-resource');
    const inputUserQuery = document.getElementById('input-user-query');
    const tacticalForm = document.getElementById('tactical-form');
    const btnSubmit = document.getElementById('btn-submit-tactic');
    const queueBanner = document.getElementById('queue-loading-banner');
    const queueStatusText = document.getElementById('queue-status-text');
    const queueJobUuid = document.getElementById('queue-job-uuid');

    const btnCopyTerminal = document.getElementById('btn-copy-terminal');
    const copyBtnLabel = document.getElementById('copy-btn-label');
    const btnExportFile = document.getElementById('btn-export-file');
    const rawMarkdownBuffer = document.getElementById('raw-markdown-buffer');

    // Quick preset queries per game for instant testing
    const defaultQueriesBySlug = {
        'victoria-3': 'My Construction Sectors entered a severe deficit after switching to Iron-Frame Buildings. Coal and Iron prices skyrocketed and my gold reserves will hit zero in 10 weeks.',
        'hollow-knight': 'I keep losing masks against Watcher Knights and Pure Vessel because I cannot find safe windows to cast Focus without taking double damage.',
        'farming-simulator-25': 'I own 3 medium fields and very little working capital. Should I plant soybeans for direct sale or invest in fermented corn chaff silage in the bunker?',
        'baldurs-gate-3': 'I need to defeat the Apostle of Myrkul in Honour Mode without burning all my Spell Slots in Phase 1 against Ketheric Thorm and prevent him from consuming necromites.'
    };

    // 1. Game Selection in Square Cards Grid
    gameCards.forEach((card) => {
        card.addEventListener('click', () => {
            gameCards.forEach((c) => c.classList.remove('is-selected'));
            card.classList.add('is-selected');

            const gameId = card.getAttribute('data-game-id');
            const gameCode = card.getAttribute('data-game-code');
            const gameSlug = card.getAttribute('data-game-slug');
            const resourcesRaw = card.getAttribute('data-game-resources');

            if (inputGameId && gameId) {
                inputGameId.value = gameId;
            }
            if (selectedGameIndicator && gameCode) {
                selectedGameIndicator.textContent = gameCode;
            }

            try {
                const resources = JSON.parse(resourcesRaw || '[]');
                renderResourceChips(resources);
                if (resources.length > 0 && inputMissingResource) {
                    inputMissingResource.value = resources[0];
                }
            } catch (e) {
                // Ignore parse error
            }

            if (gameSlug && defaultQueriesBySlug[gameSlug] && inputUserQuery) {
                inputUserQuery.value = defaultQueriesBySlug[gameSlug];
            }
        });
    });

    function renderResourceChips(resources) {
        if (!resourceChipsContainer) return;
        resourceChipsContainer.innerHTML = '';

        resources.forEach((res) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'resource-chip-btn';
            btn.setAttribute('data-resource', res);
            btn.textContent = `+ ${res}`;
            btn.addEventListener('click', () => {
                if (inputMissingResource) {
                    inputMissingResource.value = res;
                    inputMissingResource.focus();
                }
            });
            resourceChipsContainer.appendChild(btn);
        });
    }

    document.querySelectorAll('.resource-chip-btn').forEach((chip) => {
        chip.addEventListener('click', () => {
            const res = chip.getAttribute('data-resource');
            if (res && inputMissingResource) {
                inputMissingResource.value = res;
            }
        });
    });

    // 2. Async Tactical Form Submission
    if (tacticalForm) {
        tacticalForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(tacticalForm);
            const payload = Object.fromEntries(formData.entries());
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            setLoadingState(true, 'CHECKING SHA-256 HASH IN MYSQL CACHE...');

            try {
                const response = await fetch(tacticalForm.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.status === 200 && result.status === 'completed' && result.data) {
                    setLoadingState(false);
                    renderTerminalResponse(result.data, Boolean(result.cached), result.response_time_ms || 2);
                    return;
                }

                if (response.status === 202 && result.status === 'queued' && result.poll_url) {
                    if (queueJobUuid) {
                        queueJobUuid.textContent = `UUID: ${result.uuid.slice(0, 8)}...`;
                    }
                    await pollQueueStatus(result.poll_url);
                    return;
                }

                throw new Error(result.message || 'Failed to process tactical telemetry.');
            } catch (error) {
                setLoadingState(false);
                alert(`[TACTICAL ALERT]: ${error.message}`);
            }
        });
    }

    // 3. Laravel Database Queue Polling
    async function pollQueueStatus(pollUrl) {
        const maxAttempts = 20;
        let attempt = 0;

        while (attempt < maxAttempts) {
            attempt++;
            setLoadingState(
                true,
                `[LARAVEL QUEUE WORKER] QUERYING GEMINI-3.5-FLASH-LITE (CYCLE ${attempt}/${maxAttempts})...`
            );

            await new Promise((resolve) => setTimeout(resolve, 900));

            const response = await fetch(pollUrl, {
                headers: { 'Accept': 'application/json' }
            });
            const statusData = await response.json();

            if (statusData.status === 'completed' && statusData.data) {
                setLoadingState(false);
                renderTerminalResponse(statusData.data, false, statusData.data.response_time_ms || 120);
                return;
            }

            if (statusData.status === 'failed') {
                setLoadingState(false);
                throw new Error(statusData.message || 'Job failed in the processing queue.');
            }
        }

        setLoadingState(false);
    }

    function setLoadingState(isLoading, message = '') {
        if (btnSubmit) {
            btnSubmit.disabled = isLoading;
            btnSubmit.style.opacity = isLoading ? '0.7' : '1';
        }
        if (queueBanner) {
            queueBanner.classList.toggle('is-visible', isLoading);
        }
        if (queueStatusText && message) {
            queueStatusText.textContent = message;
        }
    }

    // 4. Render Result into Electric Yellow AI Box (#FFDE59)
    function renderTerminalResponse(data, isCached, elapsedMs) {
        const badgeCache = document.getElementById('badge-cache-status');
        const badgeThreat = document.getElementById('badge-threat-level');
        const badgeHits = document.getElementById('badge-hit-counter');

        if (badgeCache) {
            badgeCache.className = `status-badge ${isCached ? 'cache-hit' : 'queue-live'}`;
            badgeCache.textContent = isCached
                ? `CACHE HIT // ${elapsedMs}ms`
                : `QUEUE PROCESSED // ${elapsedMs}ms`;
        }

        if (badgeThreat) {
            const threat = (data.threat_level || 'MEDIUM').toUpperCase();
            badgeThreat.className = `status-badge threat-${threat.toLowerCase()}`;
            badgeThreat.textContent = `THREAT: ${threat}`;
        }

        if (badgeHits) {
            badgeHits.textContent = `HITS: ${data.hit_count || 1}`;
        }

        setText('out-game-title', `${data.game?.title || ''} [${data.game?.short_code || ''}]`);
        setText('out-category-name', `[${data.category?.code_tag || ''}] ${data.category?.name || ''}`);
        setText('out-game-phase', data.telemetry?.game_phase || '');
        setText('out-missing-resource', data.telemetry?.missing_resource || '');
        setText('out-ai-model', `ENGINE: ${data.ai_model || 'gemini-3.5-flash-lite'}`);
        setText('out-tactical-title', data.tactical_title || '');
        setText('out-expected-outcome', data.expected_outcome || '');

        if (data.prompt_hash) {
            setText('out-prompt-hash', `SHA-256: ${data.prompt_hash.slice(0, 20)}...`);
        }

        const stepsList = document.getElementById('out-steps-list');
        if (stepsList && Array.isArray(data.action_steps)) {
            stepsList.innerHTML = '';
            data.action_steps.forEach((step, idx) => {
                const li = document.createElement('li');
                li.className = 'ai-step-item';

                const numSpan = document.createElement('span');
                numSpan.className = 'ai-step-number';
                numSpan.textContent = String(idx + 1).padStart(2, '0');

                const textSpan = document.createElement('span');
                textSpan.className = 'ai-step-text';
                textSpan.textContent = step;

                li.appendChild(numSpan);
                li.appendChild(textSpan);
                stepsList.appendChild(li);
            });
        }

        const metricsGrid = document.getElementById('out-metrics-grid');
        if (metricsGrid && Array.isArray(data.loadout_metrics)) {
            metricsGrid.innerHTML = '';
            data.loadout_metrics.forEach((metric) => {
                const card = document.createElement('div');
                card.className = 'ai-metric-card';

                const labelDiv = document.createElement('div');
                labelDiv.className = 'ai-metric-card-label';
                labelDiv.textContent = metric.label || 'METRIC';

                const valDiv = document.createElement('div');
                valDiv.className = 'ai-metric-card-value';
                valDiv.textContent = metric.value || 'N/A';

                card.appendChild(labelDiv);
                card.appendChild(valDiv);
                metricsGrid.appendChild(card);
            });
        }

        if (rawMarkdownBuffer && data.raw_markdown_response) {
            rawMarkdownBuffer.value = data.raw_markdown_response;
        }

        if (isCached) {
            const savedEl = document.getElementById('metric-saved-calls');
            if (savedEl) {
                const current = parseInt(savedEl.textContent || '0', 10);
                if (!Number.isNaN(current)) {
                    savedEl.textContent = String(current + 1);
                }
            }
        }
    }

    function setText(elementId, value) {
        const el = document.getElementById(elementId);
        if (el) {
            el.textContent = value;
        }
    }

    // 5. Load Cached Log Buttons
    document.querySelectorAll('.btn-load-log').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-load-url');
            if (!url) return;

            try {
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (result.data) {
                    renderTerminalResponse(result.data, true, result.response_time_ms || 2);
                }
            } catch (e) {
                console.error('Failed to load cached log:', e);
            }
        });
    });

    // 6. Copy Directive & Export Report (.TXT)
    if (btnCopyTerminal) {
        btnCopyTerminal.addEventListener('click', async () => {
            const content = rawMarkdownBuffer?.value || '';
            if (!content) return;

            try {
                await navigator.clipboard.writeText(content);
                if (copyBtnLabel) {
                    const original = copyBtnLabel.textContent;
                    copyBtnLabel.textContent = 'COPIED TO CLIPBOARD! [OK]';
                    setTimeout(() => {
                        copyBtnLabel.textContent = original;
                    }, 1800);
                }
            } catch (e) {
                alert('Copied to clipboard!');
            }
        });
    }

    if (btnExportFile) {
        btnExportFile.addEventListener('click', () => {
            const content = rawMarkdownBuffer?.value || '';
            if (!content) return;

            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const code = selectedGameIndicator?.textContent?.trim() || 'TACTIC';
            a.href = url;
            a.download = `GAMESENSE-AI-${code}-${Date.now()}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
});
