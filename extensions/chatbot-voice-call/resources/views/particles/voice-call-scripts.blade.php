{{-- Voice Call Alpine.js Store --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('voiceCall', {
            status: 'idle',
            enabled: false,
            provider: null,
            credentials: null,
            conversationId: null,
            connection: null,
            callStartedAt: null,
            demoTimeLimit: null,
            demoTimer: null,

            @if (!($is_editor ?? false) && isset($chatbot) && isset($session))
                voiceCallBaseUrl: '{{ url("api/v2/chatbot/{$chatbot->uuid}/session/{$session}/voice-call") }}',
                isEditor: false,
            @else
                voiceCallBaseUrl: '',
                isEditor: true,
            @endif

            getChatbot() {
                const el = document.querySelector('.lqd-ext-chatbot[x-data]');
                if (!el) return null;
                return Alpine.$data(el);
            },

            pushMessage(role) {
                const chatbot = this.getChatbot();
                if (chatbot && chatbot.messages) {
                    chatbot.messages.push({
                        id: role + '-' + Date.now(),
                        role: role,
                        created_at: new Date().toISOString(),
                    });
                    if (chatbot.scrollMessagesToBottom) {
                        chatbot.scrollMessagesToBottom(true);
                    }
                }
            },

            addTranscriptMessage(role, message) {
                const trimmed = (message || '').trim();
                if (!trimmed) return;

                const chatbot = this.getChatbot();
                if (chatbot && chatbot.messages) {
                    chatbot.messages.push({
                        id: 'voice-transcript-' + Date.now(),
                        role: role === 'user' ? 'voice-transcript-user' : 'voice-transcript-assistant',
                        message: trimmed,
                        created_at: new Date().toISOString(),
                    });
                    if (chatbot.scrollMessagesToBottom) {
                        chatbot.scrollMessagesToBottom(true);
                    }
                }
            },

            async start() {
                if (this.status === 'active' || this.status === 'connecting') {
                    return;
                }

                this.status = 'connecting';
                this.pushMessage('voice-call-started');

                if (this.isEditor) {
                    this.callStartedAt = Date.now();
                    setTimeout(() => {
                        this.status = 'active';
                        this.startWaveformAnimation();
                    }, 1000);
                    return;
                }

                try {
                    const res = await fetch(this.voiceCallBaseUrl + '/start', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                    });

                    if (!res.ok) {
                        const errorData = await res.json().catch(() => ({}));
                        console.error('Voice call start error:', errorData);

                        const chatbot = this.getChatbot();
                        if (chatbot?.setWidgetStatus) {
                            chatbot.setWidgetStatus({
                                type: 'error',
                                message: errorData.error || '{{ __('Voice call failed.') }}',
                            });
                        }

                        this.status = 'idle';
                        return;
                    }

                    const data = await res.json();
                    this.provider = data.provider;
                    this.credentials = data.credentials;
                    this.conversationId = data.conversation_id;
                    this.callStartedAt = Date.now();

                    // Demo mode: set auto-end timer
                    if (data.remaining_seconds) {
                        this.demoTimeLimit = data.remaining_seconds;
                        this.demoTimer = setTimeout(() => {
                            this.end();
                        }, data.remaining_seconds * 1000);
                    }

                    // Check if call was ended while we were awaiting the start response
                    if (this.status !== 'connecting') {
                        return;
                    }

                    if (this.provider === 'openai_realtime') {
                        await this.connectOpenAi(data.credentials, data.first_message);
                    } else if (this.provider === 'elevenlabs') {
                        await this.connectElevenLabs(data.credentials);
                    }

                    // Check again after awaiting the provider connection
                    if (this.status !== 'connecting') {
                        return;
                    }

                    this.status = 'active';
                    this.startWaveformAnimation();
                } catch (error) {
                    console.error('Voice call start error:', error);

                    const chatbot = this.getChatbot();
                    if (chatbot?.setWidgetStatus) {
                        chatbot.setWidgetStatus({
                            type: 'error',
                            message: '{{ __('Voice call failed. Please try again.') }}',
                        });
                    }

                    this.status = 'idle';
                }
            },

            async connectOpenAi(credentials, firstMessage) {
                const ephemeralKey = credentials.ephemeral_key;
                const model = credentials.model || 'gpt-4o-realtime-preview-2024-12-17';

                const wsUrl = `wss://api.openai.com/v1/realtime?model=${model}`;
                const ws = new WebSocket(wsUrl, [
                    'realtime',
                    `openai-insecure-api-key.${ephemeralKey}`,
                    'openai-beta.realtime-v1',
                ]);

                this.connection = ws;

                ws.onopen = () => {
                    ws.send(JSON.stringify({
                        type: 'session.update',
                        session: {
                            modalities: ['text', 'audio'],
                            instructions: firstMessage || 'You are a helpful assistant.',
                            input_audio_transcription: {
                                model: 'gpt-4o-mini-transcribe',
                            },
                            turn_detection: {
                                type: 'server_vad'
                            },
                        },
                    }));

                    this.startMicrophone(ws);
                };

                ws.onmessage = (event) => {
                    const msg = JSON.parse(event.data);
                    this.handleOpenAiMessage(msg);
                };

                ws.onerror = (error) => {
                    console.error('OpenAI WebSocket error:', error);
                };

                ws.onclose = () => {
                    // Only auto-end if this is still the active connection
                    if (this.connection === ws && this.status === 'active') {
                        this.end();
                    }
                };
            },

            handleOpenAiMessage(msg) {
                // User speech transcription completed
                if (msg.type === 'conversation.item.input_audio_transcription.completed') {
                    const transcript = (msg.transcript || '').trim();
                    if (transcript) {
                        this.addTranscriptMessage('user', transcript);
                        this.sendTranscript('user', transcript);
                    }
                }

                // User speech transcription failed
                if (msg.type === 'conversation.item.input_audio_transcription.failed') {
                    console.warn('[VoiceCall] transcription failed:', msg.error);
                }

                // Fallback: extract user transcript from response.done output
                if (msg.type === 'response.done' && msg.response?.output) {
                    for (const item of msg.response.output) {
                        if (item.type === 'message' && item.role === 'assistant') {
                            // Assistant final transcript also available here as fallback
                        }
                    }
                }

                // Fallback: extract user input transcript from conversation.item.created
                if (msg.type === 'conversation.item.created' && msg.item?.role === 'user') {
                    const content = msg.item.content;
                    if (Array.isArray(content)) {
                        for (const part of content) {
                            if (part.transcript) {
                                const transcript = part.transcript.trim();
                                if (transcript) {
                                    this.addTranscriptMessage('user', transcript);
                                    this.sendTranscript('user', transcript);
                                }
                            }
                        }
                    }
                }

                // Assistant speech transcription completed
                if (msg.type === 'response.audio_transcript.done') {
                    const transcript = (msg.transcript || '').trim();
                    if (transcript) {
                        this.addTranscriptMessage('assistant', transcript);
                        this.sendTranscript('assistant', transcript);
                    }
                }

                if (msg.type === 'response.audio.delta') {
                    this.playAudioDelta(msg.delta);
                }

                if (msg.type === 'error') {
                    console.error('[VoiceCall] OpenAI Realtime error:', msg.error);
                }

                // Log session confirmation to verify transcription config was accepted
                if (msg.type === 'session.updated') {
                    const hasTranscription = !!msg.session?.input_audio_transcription;
                    console.log('[VoiceCall] session updated, transcription enabled:', hasTranscription);
                }
            },

            // ── Audio properties ──
            micAudioContext: null,
            playbackAudioContext: null,
            mediaStream: null,
            audioProcessor: null,
            nextPlayTime: 0,

            // ── Waveform properties ──
            micAnalyser: null,
            playbackAnalyser: null,
            waveformAnimationId: null,
            waveformData: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            waveformSource: 'idle',

            async startMicrophone(ws) {
                try {
                    this.mediaStream = await navigator.mediaDevices.getUserMedia({
                        audio: true
                    });
                    this.micAudioContext = new AudioContext({
                        sampleRate: 24000
                    });
                    const source = this.micAudioContext.createMediaStreamSource(this.mediaStream);
                    this.audioProcessor = this.micAudioContext.createScriptProcessor(4096, 1, 1);

                    // Create analyser for waveform visualization
                    this.micAnalyser = this.micAudioContext.createAnalyser();
                    this.micAnalyser.fftSize = 64;
                    this.micAnalyser.smoothingTimeConstant = 0.7;

                    // Connect processor through a silent gain node so it keeps
                    // processing audio without playing mic input through speakers
                    const silentGain = this.micAudioContext.createGain();
                    silentGain.gain.value = 0;

                    // Chain: source -> analyser -> processor -> silentGain -> destination
                    source.connect(this.micAnalyser);
                    this.micAnalyser.connect(this.audioProcessor);
                    this.audioProcessor.connect(silentGain);
                    silentGain.connect(this.micAudioContext.destination);

                    this.audioProcessor.onaudioprocess = (e) => {
                        if (ws.readyState !== WebSocket.OPEN) return;
                        const inputData = e.inputBuffer.getChannelData(0);
                        const pcm16 = new Int16Array(inputData.length);
                        for (let i = 0; i < inputData.length; i++) {
                            pcm16[i] = Math.max(-32768, Math.min(32767, Math.floor(inputData[i] * 32768)));
                        }
                        const uint8 = new Uint8Array(pcm16.buffer);
                        let binary = '';
                        for (let i = 0; i < uint8.length; i++) {
                            binary += String.fromCharCode(uint8[i]);
                        }
                        const base64 = btoa(binary);
                        ws.send(JSON.stringify({
                            type: 'input_audio_buffer.append',
                            audio: base64,
                        }));
                    };
                } catch (error) {
                    console.error('Microphone access error:', error);
                }
            },

            playAudioDelta(base64Audio) {
                if (!this.playbackAudioContext) {
                    this.playbackAudioContext = new AudioContext({
                        sampleRate: 24000
                    });
                    this.nextPlayTime = 0;

                    // Create analyser for playback waveform
                    this.playbackAnalyser = this.playbackAudioContext.createAnalyser();
                    this.playbackAnalyser.fftSize = 64;
                    this.playbackAnalyser.smoothingTimeConstant = 0.7;
                    this.playbackAnalyser.connect(this.playbackAudioContext.destination);
                }

                const binary = atob(base64Audio);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                const pcm16 = new Int16Array(bytes.buffer);
                const float32 = new Float32Array(pcm16.length);
                for (let i = 0; i < pcm16.length; i++) {
                    float32[i] = pcm16[i] / 32768;
                }

                const buffer = this.playbackAudioContext.createBuffer(1, float32.length, 24000);
                buffer.getChannelData(0).set(float32);
                const source = this.playbackAudioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(this.playbackAnalyser);

                // Schedule playback sequentially to prevent overlapping audio
                const now = this.playbackAudioContext.currentTime;
                const startTime = Math.max(now, this.nextPlayTime);
                source.start(startTime);
                this.nextPlayTime = startTime + buffer.duration;
            },

            // ── Waveform animation ──
            startWaveformAnimation() {
                const update = () => {
                    let micEnergy = 0;
                    let playbackEnergy = 0;
                    const barCount = 15;
                    const newData = new Array(barCount).fill(0);

                    // ElevenLabs: read frequency data from SDK connection
                    if (this.provider === 'elevenlabs' && this.connection) {
                        const inputData = typeof this.connection.getInputByteFrequencyData === 'function' ?
                            this.connection.getInputByteFrequencyData() :
                            null;
                        const outputData = typeof this.connection.getOutputByteFrequencyData === 'function' ?
                            this.connection.getOutputByteFrequencyData() :
                            null;

                        if (inputData && inputData.length) {
                            for (let i = 0; i < inputData.length; i++) {
                                micEnergy += inputData[i];
                            }
                            micEnergy /= inputData.length;
                        }

                        if (outputData && outputData.length) {
                            for (let i = 0; i < outputData.length; i++) {
                                playbackEnergy += outputData[i];
                            }
                            playbackEnergy /= outputData.length;
                        }

                        const activeData = (playbackEnergy >= micEnergy && outputData?.length) ? outputData : inputData;
                        if (activeData && activeData.length) {
                            for (let i = 0; i < barCount; i++) {
                                const binIndex = Math.floor(i * (activeData.length / barCount));
                                newData[i] = activeData[binIndex] / 255;
                            }
                        }
                    } else {
                        // OpenAI Realtime: read from WebAudio AnalyserNodes
                        if (this.micAnalyser) {
                            const micData = new Uint8Array(this.micAnalyser.frequencyBinCount);
                            this.micAnalyser.getByteFrequencyData(micData);
                            for (let i = 0; i < micData.length; i++) {
                                micEnergy += micData[i];
                            }
                            micEnergy /= micData.length;

                            if (micEnergy > playbackEnergy) {
                                for (let i = 0; i < barCount; i++) {
                                    const binIndex = Math.floor(i * (micData.length / barCount));
                                    newData[i] = micData[binIndex] / 255;
                                }
                            }
                        }

                        if (this.playbackAnalyser) {
                            const playData = new Uint8Array(this.playbackAnalyser.frequencyBinCount);
                            this.playbackAnalyser.getByteFrequencyData(playData);
                            for (let i = 0; i < playData.length; i++) {
                                playbackEnergy += playData[i];
                            }
                            playbackEnergy /= playData.length;

                            if (playbackEnergy >= micEnergy) {
                                for (let i = 0; i < barCount; i++) {
                                    const binIndex = Math.floor(i * (playData.length / barCount));
                                    newData[i] = playData[binIndex] / 255;
                                }
                            }
                        }
                    }

                    // Determine who is speaking
                    const threshold = 5;
                    if (playbackEnergy >= threshold && playbackEnergy >= micEnergy) {
                        this.waveformSource = 'assistant';
                    } else if (micEnergy >= threshold) {
                        this.waveformSource = 'user';
                    } else {
                        this.waveformSource = 'idle';
                    }

                    // Apply min baseline and assign (new array for Alpine reactivity)
                    this.waveformData = newData.map(v => 0.15 + v * 0.85);

                    this.waveformAnimationId = requestAnimationFrame(update);
                };

                this.waveformAnimationId = requestAnimationFrame(update);
            },

            stopWaveformAnimation() {
                if (this.waveformAnimationId) {
                    cancelAnimationFrame(this.waveformAnimationId);
                    this.waveformAnimationId = null;
                }
                this.waveformData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
                this.waveformSource = 'idle';
                this.micAnalyser = null;
                this.playbackAnalyser = null;
            },

            async connectElevenLabs(credentials) {
                // Lazy-load ElevenLabs SDK from CDN if not already loaded
                if (!window._elevenLabsConversation) {
                    try {
                        const module = await import('https://esm.sh/@11labs/client@0.2.0');
                        window._elevenLabsConversation = module.Conversation;
                    } catch (e) {
                        console.error('Failed to load ElevenLabs Conversation SDK:', e);
                        this.status = 'idle';
                        return;
                    }
                }

                const ConversationClass = window._elevenLabsConversation;
                const agentId = credentials.agent_id;
                const signedUrl = credentials.signed_url;

                const sessionConfig = {
                    onConnect: () => {
                        console.log('ElevenLabs connected');
                    },
                    onDisconnect: () => {
                        // Only auto-end if this is still the active connection
                        if (this.connection && this.status === 'active') {
                            this.end();
                        }
                    },
                    onMessage: (message) => {
                        const text = (message.message || '').trim();
                        if (!text) return;

                        if (message.source === 'ai') {
                            this.addTranscriptMessage('assistant', text);
                            this.sendTranscript('assistant', text);
                        } else if (message.source === 'user') {
                            this.addTranscriptMessage('user', text);
                            this.sendTranscript('user', text);
                        }
                    },
                    onError: (error) => {
                        console.error('ElevenLabs error:', error);
                    },
                };

                if (signedUrl) {
                    sessionConfig.signedUrl = signedUrl;
                } else {
                    sessionConfig.agentId = agentId;
                }

                try {
                    this.connection = await ConversationClass.startSession(sessionConfig);
                } catch (error) {
                    console.error('ElevenLabs connection error:', error);
                    this.status = 'idle';
                }
            },

            async sendTranscript(role, message) {
                if (this.isEditor || !this.voiceCallBaseUrl) return;

                try {
                    await fetch(this.voiceCallBaseUrl + '/transcript', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            role,
                            message
                        }),
                    });
                } catch (error) {
                    console.error('Transcript save error:', error);
                }
            },

            async end() {
                if (this.status === 'idle' || this.status === 'ended') {
                    return;
                }

                this.status = 'ended';

                // Stop waveform animation
                this.stopWaveformAnimation();

                // Clear demo timer
                if (this.demoTimer) {
                    clearTimeout(this.demoTimer);
                    this.demoTimer = null;
                }

                // Calculate call duration
                const duration = this.callStartedAt ?
                    Math.floor((Date.now() - this.callStartedAt) / 1000) :
                    0;

                // Save and null the connection before closing to prevent
                // onclose/onDisconnect handlers from re-entering end()
                const connection = this.connection;
                const provider = this.provider;
                this.connection = null;

                if (connection) {
                    try {
                        if (provider === 'openai_realtime') {
                            connection.close();
                        } else if (provider === 'elevenlabs' && connection.endSession) {
                            await connection.endSession();
                        }
                    } catch (e) {
                        console.warn('Connection close error:', e);
                    }
                }

                if (this.mediaStream) {
                    this.mediaStream.getTracks().forEach(track => track.stop());
                    this.mediaStream = null;
                }

                if (this.audioProcessor) {
                    this.audioProcessor.disconnect();
                    this.audioProcessor = null;
                }

                if (this.micAudioContext) {
                    this.micAudioContext.close().catch(() => {});
                    this.micAudioContext = null;
                }

                if (this.playbackAudioContext) {
                    this.playbackAudioContext.close().catch(() => {});
                    this.playbackAudioContext = null;
                }

                this.nextPlayTime = 0;

                this.pushMessage('voice-call-ended');

                if (!this.isEditor && this.voiceCallBaseUrl) {
                    try {
                        await fetch(this.voiceCallBaseUrl + '/end', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                duration
                            }),
                        });
                    } catch (error) {
                        console.error('Voice call end error:', error);
                    }
                }

                this.credentials = null;
                this.conversationId = null;
                this.provider = null;
                this.callStartedAt = null;
                this.demoTimeLimit = null;

                setTimeout(() => {
                    this.status = 'idle';
                }, 500);
            },
        });
    });
</script>
