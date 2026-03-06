(function (global) {
    'use strict';

    var DEFAULT_BEEP_URL = 'assets/sounds/beep.mp3';
    var BEEP_DELAY_MS = 500;

    var DIGIT_WORDS_MR = {
        '0': '\u0936\u0942\u0928\u094d\u092f',
        '1': '\u090f\u0915',
        '2': '\u0926\u094b\u0928',
        '3': '\u0924\u0940\u0928',
        '4': '\u091a\u093e\u0930',
        '5': '\u092a\u093e\u091a',
        '6': '\u0938\u0939\u093e',
        '7': '\u0938\u093e\u0924',
        '8': '\u0906\u0920',
        '9': '\u0928\u090a'
    };

    // Range intentionally limited as requested; above range falls back to digit-wise speech.
    var NUMBER_WORDS_MR = {
        0: '\u0936\u0942\u0928\u094d\u092f',
        1: '\u090f\u0915',
        2: '\u0926\u094b\u0928',
        3: '\u0924\u0940\u0928',
        4: '\u091a\u093e\u0930',
        5: '\u092a\u093e\u091a',
        6: '\u0938\u0939\u093e',
        7: '\u0938\u093e\u0924',
        8: '\u0906\u0920',
        9: '\u0928\u090a',
        10: '\u0926\u0939\u093e',
        11: '\u0905\u0915\u0930\u093e',
        12: '\u092c\u093e\u0930\u093e',
        13: '\u0924\u0947\u0930\u093e',
        14: '\u091a\u094c\u0926\u093e',
        15: '\u092a\u0902\u0927\u0930\u093e',
        16: '\u0938\u094b\u0933\u093e',
        17: '\u0938\u0924\u0930\u093e',
        18: '\u0905\u0920\u0930\u093e',
        19: '\u090f\u0915\u094b\u0923\u0940\u0938',
        20: '\u0935\u0940\u0938',
        21: '\u090f\u0915\u0935\u0940\u0938',
        22: '\u092c\u093e\u0935\u0940\u0938',
        23: '\u0924\u0947\u0935\u0940\u0938',
        24: '\u091a\u094b\u0935\u0940\u0938',
        25: '\u092a\u0902\u091a\u0935\u0940\u0938',
        26: '\u0938\u0935\u094d\u0935\u0940\u0938',
        27: '\u0938\u0924\u094d\u0924\u093e\u0935\u0940\u0938',
        28: '\u0905\u0920\u094d\u0920\u093e\u0935\u0940\u0938',
        29: '\u090f\u0915\u094b\u0923\u0924\u0940\u0938',
        30: '\u0924\u0940\u0938',
        31: '\u090f\u0915\u0924\u0940\u0938',
        32: '\u092c\u0924\u094d\u0924\u0940\u0938',
        33: '\u0924\u0947\u0939\u0924\u0940\u0938',
        34: '\u091a\u094c\u0924\u0940\u0938',
        35: '\u092a\u0938\u094d\u0924\u0940\u0938',
        36: '\u091b\u0924\u094d\u0924\u0940\u0938',
        37: '\u0938\u0926\u0924\u0940\u0938',
        38: '\u0905\u0921\u0924\u0940\u0938',
        39: '\u090f\u0915\u094b\u0923\u091a\u093e\u0933\u0940\u0938',
        40: '\u091a\u093e\u0933\u0940\u0938',
        41: '\u090f\u0915\u094d\u0915\u0947\u091a\u093e\u0933\u0940\u0938',
        42: '\u092c\u0947\u091a\u093e\u0933\u0940\u0938',
        43: '\u0924\u094d\u0930\u0947\u091a\u093e\u0933\u0940\u0938',
        44: '\u091a\u0935\u094d\u0935\u0947\u091a\u093e\u0933\u0940\u0938',
        45: '\u092a\u0902\u091a\u0947\u091a\u093e\u0933\u0940\u0938',
        46: '\u0938\u0947\u0939\u0947\u091a\u093e\u0933\u0940\u0938',
        47: '\u0938\u0924\u094d\u0924\u0947\u091a\u093e\u0933\u0940\u0938',
        48: '\u0905\u0920\u094d\u0920\u0947\u091a\u093e\u0933\u0940\u0938',
        49: '\u090f\u0915\u094b\u0923\u092a\u0928\u094d\u0928\u093e\u0938',
        50: '\u092a\u0928\u094d\u0928\u093e\u0938'
    };

    function normalizeLanguage(language) {
        var normalized = String(language || '').trim().toLowerCase();
        return normalized === 'english' ? 'english' : 'marathi';
    }

    function languageCode(language) {
        return normalizeLanguage(language) === 'english' ? 'en-IN' : 'mr-IN';
    }

    function wait(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function parseTokenNumber(tokenNumber) {
        var parsed = Number(tokenNumber);
        if (Number.isFinite(parsed)) {
            return Math.trunc(parsed);
        }
        var numericText = String(tokenNumber || '').replace(/\D+/g, '');
        return numericText ? parseInt(numericText, 10) : NaN;
    }

    function marathiDigitsSpeech(tokenNumber) {
        var raw = String(tokenNumber || '').replace(/\D+/g, '');
        if (!raw) {
            return String(tokenNumber || '');
        }
        return raw.split('').map(function (digit) {
            return DIGIT_WORDS_MR[digit] || digit;
        }).join(' ');
    }

    function numberToMarathiWords(tokenNumber) {
        var parsed = parseTokenNumber(tokenNumber);
        if (!Number.isFinite(parsed)) {
            return marathiDigitsSpeech(tokenNumber);
        }
        if (Object.prototype.hasOwnProperty.call(NUMBER_WORDS_MR, parsed)) {
            return NUMBER_WORDS_MR[parsed];
        }
        return marathiDigitsSpeech(parsed);
    }

    function selectPreferredVoice(voices, langCode) {
        if (!voices || !voices.length) {
            return null;
        }
        var femaleHints = ['female', 'woman', 'girl', 'heera', 'swara', 'kavya', 'sangeeta', 'zira'];
        var languageVoices = voices.filter(function (voice) {
            var voiceLang = String(voice.lang || '').toLowerCase();
            return voiceLang === langCode.toLowerCase() || voiceLang.indexOf(langCode.toLowerCase()) === 0;
        });

        var femaleVoice = languageVoices.find(function (voice) {
            var name = String(voice.name || '').toLowerCase();
            return femaleHints.some(function (hint) {
                return name.indexOf(hint) !== -1;
            });
        });
        if (femaleVoice) {
            return femaleVoice;
        }
        if (languageVoices.length) {
            return languageVoices[0];
        }
        return voices[0] || null;
    }

    function speakText(text, language, options) {
        var speechText = String(text || '').trim();
        if (!speechText || !('speechSynthesis' in window)) {
            return Promise.resolve();
        }

        var opts = options || {};
        var langCode = languageCode(language);

        return new Promise(function (resolve) {
            try {
                var synthesis = window.speechSynthesis;
                if (opts.cancelQueue) {
                    synthesis.cancel();
                }

                var utterance = new SpeechSynthesisUtterance(speechText);
                utterance.lang = langCode;
                utterance.rate = typeof opts.rate === 'number' ? opts.rate : 0.9;
                utterance.pitch = typeof opts.pitch === 'number' ? opts.pitch : 1.0;
                utterance.volume = typeof opts.volume === 'number' ? opts.volume : 1;

                var voices = synthesis.getVoices();
                var selected = selectPreferredVoice(voices, langCode);
                if (selected) {
                    utterance.voice = selected;
                }

                var settled = false;
                function finish() {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    resolve();
                }

                utterance.onend = finish;
                utterance.onerror = finish;

                // Safety timeout in case browser never fires onend/onerror.
                window.setTimeout(finish, 10000);
                synthesis.speak(utterance);
            } catch (error) {
                resolve();
            }
        });
    }

    function playBeep(beepUrl) {
        var url = beepUrl || DEFAULT_BEEP_URL;
        return new Promise(function (resolve) {
            try {
                var audio = new Audio(url);
                audio.preload = 'auto';
                audio.currentTime = 0;
                var settled = false;

                function finish() {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    resolve();
                }

                audio.onended = finish;
                audio.onerror = finish;
                var playPromise = audio.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(finish);
                }
                window.setTimeout(finish, 900);
            } catch (error) {
                resolve();
            }
        });
    }

    function generatedTokenMessage(tokenNumber, language) {
        if (normalizeLanguage(language) === 'english') {
            return 'Thank you. Your token number is ' + tokenNumber + '. Please watch the screen for your turn.';
        }
        var tokenSpeech = numberToMarathiWords(tokenNumber);
        return '\u0927\u0928\u094d\u092f\u0935\u093e\u0926. \u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928 \u0915\u094d\u0930\u092e\u093e\u0902\u0915 ' + tokenSpeech + ' \u0906\u0939\u0947. \u0915\u0943\u092a\u092f\u093e \u0938\u094d\u0915\u094d\u0930\u0940\u0928\u0935\u0930 \u091a\u093e\u0932\u0942 \u091f\u094b\u0915\u0928 \u092a\u093e\u0939\u0924 \u0930\u0939\u093e.';
    }

    function printingMessage(language) {
        if (normalizeLanguage(language) === 'english') {
            return 'Please wait. Your token is being generated.';
        }
        return '\u0915\u0943\u092a\u092f\u093e \u0925\u093e\u0902\u092c\u093e. \u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928 \u0924\u092f\u093e\u0930 \u0939\u094b\u0924 \u0906\u0939\u0947.';
    }

    function callTokenMessage(tokenNumber, language) {
        if (normalizeLanguage(language) === 'english') {
            return 'Token number ' + tokenNumber + ', please come in.';
        }
        var tokenSpeech = numberToMarathiWords(tokenNumber);
        return '\u091f\u094b\u0915\u0928 \u0915\u094d\u0930\u092e\u093e\u0902\u0915 ' + tokenSpeech + ', \u0915\u0943\u092a\u092f\u093e \u0906\u0924 \u092f\u093e.';
    }

    async function announceToken(tokenNumber, language, options) {
        var opts = options || {};
        await playBeep(opts.beepUrl);
        await wait(BEEP_DELAY_MS);
        return speakText(generatedTokenMessage(tokenNumber, language), language, {
            cancelQueue: true
        });
    }

    function announcePrintingStart(language, options) {
        var opts = options || {};
        return speakText(printingMessage(language), language, {
            cancelQueue: Boolean(opts.cancelQueue)
        });
    }

    async function announceTokenCall(tokenNumber, language, options) {
        var opts = options || {};
        await playBeep(opts.beepUrl);
        await wait(BEEP_DELAY_MS);
        return speakText(callTokenMessage(tokenNumber, language), language, {
            cancelQueue: true
        });
    }

    global.TokenAnnouncement = {
        normalizeLanguage: normalizeLanguage,
        languageCode: languageCode,
        numberToMarathiWords: numberToMarathiWords,
        speakText: speakText,
        playBeep: playBeep,
        announceToken: announceToken,
        announcePrintingStart: announcePrintingStart,
        announceTokenCall: announceTokenCall
    };
})(window);

