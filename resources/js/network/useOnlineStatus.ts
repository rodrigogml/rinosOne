import { onBeforeUnmount, onMounted, ref } from "vue";

/** Exposes browser connectivity without retaining or transmitting user data. */
export function useOnlineStatus() {
    const online = ref(typeof navigator === "undefined" || navigator.onLine);
    const markOnline = (): void => {
        online.value = true;
    };
    const markOffline = (): void => {
        online.value = false;
    };

    onMounted(() => {
        window.addEventListener("online", markOnline);
        window.addEventListener("offline", markOffline);
    });
    onBeforeUnmount(() => {
        window.removeEventListener("online", markOnline);
        window.removeEventListener("offline", markOffline);
    });

    return { online };
}
