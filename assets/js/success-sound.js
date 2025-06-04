// Success sound generation utility
// Creates a pleasant "bling" sound for successful check-in/out operations

class SuccessSound {
    constructor() {
        this.audioContext = null;
        this.isAudioEnabled = false;
        this.initAudio();
    }

    initAudio() {
        try {
            // Create audio context
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContext();
            this.isAudioEnabled = true;
        } catch (error) {
            console.warn('Audio not supported:', error);
            this.isAudioEnabled = false;
        }
    }

    // Play a pleasant "bling" sound for success
    playSuccess() {
        if (!this.isAudioEnabled || !this.audioContext) {
            console.log('Audio not available - success sound skipped');
            return;
        }

        try {
            // Resume audio context if suspended (required for user interaction)
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }

            // Create a pleasant bell-like tone
            const oscillator1 = this.audioContext.createOscillator();
            const oscillator2 = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();

            // Connect the audio nodes
            oscillator1.connect(gainNode);
            oscillator2.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            // Set frequencies for a pleasant chord (C and E notes)
            oscillator1.frequency.setValueAtTime(523.25, this.audioContext.currentTime); // C5
            oscillator2.frequency.setValueAtTime(659.25, this.audioContext.currentTime); // E5

            // Set oscillator types for a bell-like sound
            oscillator1.type = 'sine';
            oscillator2.type = 'sine';

            // Set volume envelope (attack, decay, sustain, release)
            const now = this.audioContext.currentTime;
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(0.3, now + 0.01); // Quick attack
            gainNode.gain.exponentialRampToValueAtTime(0.1, now + 0.2); // Decay
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.8); // Release

            // Start and stop the oscillators
            oscillator1.start(now);
            oscillator2.start(now);
            oscillator1.stop(now + 0.8);
            oscillator2.stop(now + 0.8);

        } catch (error) {
            console.warn('Error playing success sound:', error);
        }
    }

    // Initialize audio context on user interaction (required by browsers)
    enableAudio() {
        if (this.audioContext && this.audioContext.state === 'suspended') {
            this.audioContext.resume().then(() => {
                console.log('Audio enabled');
            });
        }
    }
}

// Create global instance
window.successSound = new SuccessSound();

// Enable audio on first user interaction
document.addEventListener('click', () => {
    if (window.successSound) {
        window.successSound.enableAudio();
    }
}, { once: true });
