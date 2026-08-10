import jsQR from 'jsqr';
import { ref } from 'vue';
import { classifyCameraError } from './useFoundryDiagnostics';

/**
 * Drives the device camera and decodes QR codes with jsQR.
 * Just the camera plumbing; payload validation lives in useFoundryDiagnostics.
 */
export function useQrScanner() {
    const scanning = ref(false);

    let stream = null;
    let rafId = null;
    let videoEl = null;
    let canvas = null;
    let ctx = null;

    /**
     * @param {HTMLVideoElement} video
     * @param {(text: string) => void} onDecode  Called once with the decoded text.
     * @param {(code: string) => void} onError   Called with a diagnostic code.
     */
    async function start(video, onDecode, onError) {
        if (!window.isSecureContext) return onError('insecure_context');
        if (!navigator.mediaDevices?.getUserMedia) return onError('no_camera_api');

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
        } catch (e) {
            return onError(classifyCameraError(e));
        }

        videoEl = video;
        videoEl.srcObject = stream;
        videoEl.setAttribute('playsinline', 'true');
        try {
            await videoEl.play();
        } catch {
            // Some browsers require a user gesture; the visible video usually still plays.
        }

        canvas = document.createElement('canvas');
        ctx = canvas.getContext('2d', { willReadFrequently: true });
        scanning.value = true;

        const tick = () => {
            if (!scanning.value) return;
            if (videoEl.readyState === videoEl.HAVE_ENOUGH_DATA) {
                canvas.width = videoEl.videoWidth;
                canvas.height = videoEl.videoHeight;
                ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
                const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
                if (code && code.data) {
                    onDecode(code.data);
                    return; // Stop the loop; caller decides whether to stop the camera.
                }
            }
            rafId = requestAnimationFrame(tick);
        };
        rafId = requestAnimationFrame(tick);
    }

    function stop() {
        scanning.value = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        if (videoEl) {
            videoEl.srcObject = null;
            videoEl = null;
        }
    }

    return { scanning, start, stop };
}
