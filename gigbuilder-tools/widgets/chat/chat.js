// ===============================
// Gigbuilder Chat Widget (WordPress)
// Supports multiple independent instances per page
// ===============================
(function() {
    'use strict';

    var config = window.gigbuilderChat || {};
    var AJAX_URL      = config.ajaxUrl || '';
    var NONCE         = config.nonce || '';
    var COMPANY_NAME  = config.companyName || 'Chat';
    var AVATAR        = config.avatar || '\uD83C\uDFB5';
    var LAUNCHER_TEXT = config.launcherText || '';
    var WELCOME_MSG   = config.welcomeMessage || '';

    function generateToken(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        var values = new Uint8Array(length);
        crypto.getRandomValues(values);
        for (var i = 0; i < length; i++) {
            result += chars[values[i] % chars.length];
        }
        return result;
    }

    // Token is handled server-side in the PHP proxy

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getInitialMessages() {
        if (WELCOME_MSG) return [WELCOME_MSG];
        return [
            'Hello! \uD83D\uDC4B<p>I\'m an AI Agent for:</p><p><b>' + escapeHtml(COMPANY_NAME) + '</b>.</p><p>How can I assist you today?</p>',
            'You may type your question below, or press the microphone button for a voice conversation.'
        ];
    }

    // ===============================
    // WIDGET INSTANCE
    // Each call creates an independent, self-contained widget
    // ===============================
    function createWidget(container) {
        var mode = container.dataset.mode || 'inline';
        var uid = 'gb' + generateToken(6);
        var voiceEnabled = false;
        var typingBubble = null;
        var recognition = null;
        var isListening = false;
        var silenceTimer = null;
        var firstWordHeard = false;
        var autoStopTriggered = false;

        var sessionId = sessionStorage.getItem('gbAiChatSessionId_' + mode);
        if (!sessionId) {
            sessionId = generateToken(36);
            sessionStorage.setItem('gbAiChatSessionId_' + mode, sessionId);
        }

        // Scoped element access
        function $(selector) {
            return container.querySelector(selector);
        }

        // ---- DOM Injection ----
        function injectHTML() {
            if (mode === 'popup') {
                container.innerHTML =
                    '<div class="gb-launcher" data-uid="' + uid + '">' +
                        '<span class="gb-launcher-icon">\uD83D\uDDE8\uFE0F</span>' +
                        (LAUNCHER_TEXT ? '<span class="gb-launcher-text">' + escapeHtml(LAUNCHER_TEXT) + '</span>' : '') +
                    '</div>' +
                    '<div class="gb-popup hidden" data-uid="' + uid + '">' +
                        '<div class="gb-popup-header">' +
                            '<div class="gb-avatar">' + AVATAR + '</div>' +
                            '<div class="gb-header-text">' +
                                '<span class="gb-header-title">' + escapeHtml(COMPANY_NAME) + '</span>' +
                                '<span class="gb-header-subtitle">AI Assistant</span>' +
                            '</div>' +
                            '<div class="gb-status-dot"></div>' +
                            '<button class="gb-close-btn">\u2715</button>' +
                        '</div>' +
                        '<div class="gb-widget-body">' +
                            '<div class="gb-messages"></div>' +
                            '<div class="gb-input-bar">' +
                                '<button class="gb-mic-btn"></button>' +
                                '<textarea class="gb-input" placeholder="Type a message..."></textarea>' +
                                '<button type="button" class="gb-send-btn"></button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            } else {
                container.innerHTML =
                    '<div class="gb-widget-body gb-widget-inline">' +
                        '<div class="gb-inline-header">' +
                            '<div class="gb-avatar">' + AVATAR + '</div>' +
                            '<div class="gb-header-text">' +
                                '<span class="gb-header-title">' + escapeHtml(COMPANY_NAME) + '</span>' +
                                '<span class="gb-header-subtitle">AI Assistant</span>' +
                            '</div>' +
                            '<div class="gb-status-dot"></div>' +
                        '</div>' +
                        '<div class="gb-messages"></div>' +
                        '<div class="gb-input-bar">' +
                            '<button class="gb-mic-btn"></button>' +
                            '<textarea class="gb-input" placeholder="Type a message..."></textarea>' +
                            '<button type="button" class="gb-send-btn"></button>' +
                        '</div>' +
                    '</div>';
            }
        }

        // ---- Messaging ----
        function addMessage(text, type) {
            var messages = $('.gb-messages');
            if (!messages) return;
            var div = document.createElement('div');
            div.className = 'gb-msg gb-' + type;
            div.innerHTML = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }

        function showTyping() {
            var messages = $('.gb-messages');
            if (!messages) return;
            typingBubble = document.createElement('div');
            typingBubble.className = 'gb-typing-bubble';
            typingBubble.innerHTML = '<span></span><span></span><span></span>';
            messages.appendChild(typingBubble);
            messages.scrollTop = messages.scrollHeight;
        }

        function hideTyping() {
            if (typingBubble) typingBubble.remove();
            typingBubble = null;
        }

        function autoExpand(el) {
            el.style.height = '32px';
            el.style.height = el.scrollHeight + 'px';
        }

        function sendMessage() {
            var input = $('.gb-input');
            if (!input) return;
            var text = input.value.trim();
            if (!text) return;

            addMessage(escapeHtml(text), 'user');
            input.value = '';
            autoExpand(input);
            showTyping();

            var fd = new FormData();
            fd.append('action', 'gigbuilder_chat_send');
            fd.append('nonce', NONCE);
            fd.append('sessionId', sessionId);
            fd.append('chatInput', text);
            fd.append('voiceEnabled', voiceEnabled ? 'true' : 'false');
            fd.append('pageUrl', window.location.href);

            var statusMessages = [
                'Still working on it...',
                'Searching for the best answer...',
                'Almost there, hang tight...',
                'Digging a little deeper...',
                'Pulling together the details...',
                'Running a thorough search...',
                'Compiling everything for you...',
                'Just a bit longer...'
            ];
            var statusIndex = 0;
            var statusInterval = setInterval(function() {
                if (statusIndex < statusMessages.length) {
                    hideTyping();
                    addMessage(statusMessages[statusIndex], 'bot');
                    showTyping();
                    statusIndex++;
                }
            }, 10000);

            var controller = new AbortController();
            var timeout = setTimeout(function() { controller.abort(); }, 90000);

            fetch(AJAX_URL, { method: 'POST', body: fd, signal: controller.signal })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                clearTimeout(timeout);
                clearInterval(statusInterval);

                var data = res.success ? res.data : null;
                var reply = '';

                if (data) {
                    reply = data.output || data.text || data.reply || data.response || data.message || '';
                    if (data.messages && data.messages[0] && data.messages[0].text) {
                        reply = data.messages[0].text;
                    }
                }
                if (!reply && res.data && res.data.message) {
                    reply = res.data.message;
                }
                if (!reply) reply = 'Sorry, I didn\'t get a response. Please try again.';

                hideTyping();
                addMessage(reply, 'bot');
                speakText(reply);

                if (data && data.sessionId) {
                    sessionId = data.sessionId;
                    sessionStorage.setItem('gbAiChatSessionId_' + mode, sessionId);
                }
            })
            .catch(function() {
                clearTimeout(timeout);
                clearInterval(statusInterval);
                hideTyping();
                addMessage('Agent didn\'t respond. Please try again.', 'bot');
            });
        }

        // ---- Speech ----
        var AUTO_SEND_DELAY = 2500;

        function clearSilenceTimer() {
            if (silenceTimer) clearTimeout(silenceTimer);
            silenceTimer = null;
        }

        function resetSilenceTimer() {
            clearSilenceTimer();
            silenceTimer = setTimeout(function() {
                var input = $('.gb-input');
                if (!input || !input.value.trim()) return;
                autoStopTriggered = true;
                isListening = false;
                try { recognition.stop(); } catch (e) {}
                sendMessage();
            }, AUTO_SEND_DELAY);
        }

        function initSpeech() {
            var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) return null;
            var r = new SR();
            r.lang = 'en-US'; r.continuous = true; r.interimResults = false; r.maxAlternatives = 1;
            return r;
        }

        function attachRecognitionHandlers() {
            var input = $('.gb-input');
            recognition.onresult = function(event) {
                var final = '', interim = '';
                for (var i = 0; i < event.results.length; i++) {
                    if (event.results[i].isFinal) final += event.results[i][0].transcript;
                    else interim += event.results[i][0].transcript;
                }
                var combined = (final + ' ' + interim)
                    .replace(/\bperiod\b/gi, '.')
                    .replace(/\bquestion mark\b/gi, '?')
                    .replace(/\bexclamation point\b/gi, '!')
                    .replace(/\bcomma\b/gi, ',')
                    .replace(/\bcolon\b/gi, ':')
                    .replace(/\bsemicolon\b/gi, ';')
                    .replace(/\bnew line\b/gi, '\n')
                    .replace(/\bline break\b/gi, '\n')
                    .replace(/\s+/g, ' ').trim();
                if (input) { input.value = combined; autoExpand(input); }
                if (combined && !firstWordHeard) firstWordHeard = true;
                if (firstWordHeard) resetSilenceTimer();
            };
            recognition.onerror = function() {
                isListening = false;
                clearSilenceTimer();
                if (voiceEnabled) updateMicUI();
            };
            recognition.onend = function() {
                if (!voiceEnabled) return;
                if (autoStopTriggered) return;
                // Restart on same instance — no user gesture needed for continuous restart
                if (isListening) {
                    try { recognition.start(); } catch (e) {}
                }
            };
        }

        function updateMicUI() {
            var btn = $('.gb-mic-btn');
            if (!btn) return;
            if (voiceEnabled) btn.classList.add('listening');
            else btn.classList.remove('listening');
        }

        function startMic() {
            autoStopTriggered = false;
            firstWordHeard = false;
            clearSilenceTimer();

            // Stop any existing recognition first
            if (recognition) {
                try { recognition.abort(); } catch (e) {}
            }

            // Create fresh instance and attach handlers
            recognition = initSpeech();
            if (!recognition) return;
            attachRecognitionHandlers();

            isListening = true;
            updateMicUI();

            try { recognition.start(); } catch (e) {}

            // Play chime AFTER recognition.start to not consume the user gesture
            playMicChime();
        }

        function stopMic() {
            updateMicUI(); autoStopTriggered = false; isListening = false;
            try { recognition.stop(); } catch (e) {}
            clearSilenceTimer();
        }

        function toggleMic() {
            if (!voiceEnabled) {
                // Request mic permission explicitly — triggers Chrome prompt if not yet granted
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
                        // Permission granted — stop the stream (SpeechRecognition manages its own)
                        stream.getTracks().forEach(function(t) { t.stop(); });
                        voiceEnabled = true;
                        var input = $('.gb-input');
                        if (input) { input.value = ''; autoExpand(input); }
                        startMic();
                    }).catch(function(err) {
                        voiceEnabled = false;
                        updateMicUI();
                    });
                } else {
                    // Fallback — try directly
                    voiceEnabled = true;
                    var input = $('.gb-input');
                    if (input) { input.value = ''; autoExpand(input); }
                    startMic();
                }
            } else {
                voiceEnabled = false;
                stopMic();
            }
        }

        function stripHTML(html) {
            var d = document.createElement('div'); d.innerHTML = html;
            return d.textContent || d.innerText || '';
        }

        function speakText(text) {
            if (!voiceEnabled) return;
            var clean = stripHTML(text).replace(/[\u2013\u2014\u2212-]/g, ', ')
                .replace(/:/g, ', ').replace(/[\n\r]+/g, ' ')
                .replace(/[^a-zA-Z0-9 .,!?]/g, '').replace(/\s+/g, ' ').trim();
            if (!clean) return;
            var u = new SpeechSynthesisUtterance(clean);
            u.lang = 'en-US'; u.rate = 1.0;
            try { window.speechSynthesis.cancel(); } catch (e) {}
            window.speechSynthesis.speak(u);
            u.onend = function() {
                if (!voiceEnabled) return;
                // Restart listening after TTS finishes — reuse existing recognition instance
                setTimeout(function() {
                    autoStopTriggered = false;
                    firstWordHeard = false;
                    clearSilenceTimer();
                    isListening = true;
                    updateMicUI();
                    if (!recognition) {
                        recognition = initSpeech();
                        if (recognition) attachRecognitionHandlers();
                    }
                    if (recognition) {
                        try { recognition.start(); } catch (e) {}
                    }
                }, 300);
            };
        }

        function playMicChime() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator(); var gain = ctx.createGain();
                osc.type = 'sine'; osc.frequency.value = 880; gain.gain.value = 0.15;
                osc.connect(gain); gain.connect(ctx.destination); osc.start();
                setTimeout(function() { osc.stop(); ctx.close(); }, 120);
            } catch (e) {}
        }

        // ---- Events ----
        function setupEvents() {
            var sendBtn = $('.gb-send-btn');
            var micBtn  = $('.gb-mic-btn');
            var input   = $('.gb-input');
            if (!input || !sendBtn) return;

            input.addEventListener('input', function() { autoExpand(input); });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault(); voiceEnabled = false; stopMic(); sendMessage();
                }
            });
            sendBtn.addEventListener('click', function() {
                voiceEnabled = false; stopMic(); sendMessage();
            });

            recognition = initSpeech();
            if (recognition && micBtn) {
                micBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleMic();
                });
                attachRecognitionHandlers();
            } else if (micBtn && !recognition) {
                micBtn.style.display = 'none';
            }
            updateMicUI();

            if (mode === 'popup') {
                var launcher = $('.gb-launcher');
                var popup    = $('.gb-popup');
                var closeBtn = $('.gb-close-btn');

                if (launcher) launcher.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    launcher.classList.add('gb-launcher-hiding');
                    launcher.addEventListener('animationend', function h() {
                        launcher.removeEventListener('animationend', h);
                        launcher.style.display = 'none';
                        launcher.classList.remove('gb-launcher-hiding');
                    });
                    popup.classList.remove('hidden');
                    requestAnimationFrame(function() { popup.classList.add('show'); });
                });

                if (closeBtn) closeBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    popup.classList.remove('show');
                    setTimeout(function() { popup.classList.add('hidden'); }, 250);
                    launcher.style.display = 'flex';
                    launcher.classList.add('gb-launcher-showing');
                    launcher.addEventListener('animationend', function h() {
                        launcher.removeEventListener('animationend', h);
                        launcher.classList.remove('gb-launcher-showing');
                    });
                });
            }
        }

        // ---- Init ----
        injectHTML();
        setupEvents();
        getInitialMessages().forEach(function(msg) { addMessage(msg, 'bot'); });
    }

    // ===============================
    // BOOTSTRAP — initialize each container independently
    // ===============================
    function init() {
        var containers = document.querySelectorAll('.gbAiChat');
        for (var i = 0; i < containers.length; i++) {
            createWidget(containers[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
