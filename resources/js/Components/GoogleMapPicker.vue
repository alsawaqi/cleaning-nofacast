<script setup lang="ts">
import { AlertCircle, MapPin, Search } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from '../lib/i18n';

type LatLngLiteral = {
    lat: number;
    lng: number;
};

declare global {
    interface Window {
        google?: any;
        __cleanopsGoogleMapsReady?: () => void;
    }
}

let googleMapsLoader: Promise<void> | null = null;

const props = withDefaults(defineProps<{
    latitude: string;
    longitude: string;
    formattedAddress: string;
    placeId: string;
    countryCode?: string;
    countryLabel?: string;
    city?: string;
    district?: string;
    address?: string;
    areaFocusRequest?: number;
    coordinateFocusRequest?: number;
}>(), {
    countryCode: 'SA',
    countryLabel: 'Saudi Arabia',
    city: 'Riyadh',
    district: '',
    address: '',
    areaFocusRequest: 0,
    coordinateFocusRequest: 0,
});

const emit = defineEmits<{
    'update:latitude': [value: string];
    'update:longitude': [value: string];
    'update:formattedAddress': [value: string];
    'update:placeId': [value: string];
}>();

const { t } = useI18n();
const apiKey = String(import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '').trim();
const hasApiKey = computed(() => apiKey.length > 0);
const status = ref<'idle' | 'loading' | 'ready' | 'missing-key' | 'error'>(apiKey ? 'idle' : 'missing-key');
const mapElement = ref<HTMLDivElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const map = ref<any | null>(null);
const marker = ref<any | null>(null);
const autocomplete = ref<any | null>(null);
const geocoder = ref<any | null>(null);

const defaultCenter: LatLngLiteral = {
    lat: 24.7136,
    lng: 46.6753,
};

const selectedCoordinates = computed(() => {
    const position = readPosition();

    if (!position) {
        return t('customersAdmin.noMapPinSelected', 'No map pin selected yet');
    }

    return `${position.lat.toFixed(7)}, ${position.lng.toFixed(7)}`;
});

function loadGoogleMaps(key: string): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    if (window.google?.maps) {
        return Promise.resolve();
    }

    if (googleMapsLoader) {
        return googleMapsLoader;
    }

    googleMapsLoader = new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>('script[data-cleanops-google-maps]');

        window.__cleanopsGoogleMapsReady = () => resolve();

        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Google Maps failed to load')), { once: true });

            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places&loading=async&callback=__cleanopsGoogleMapsReady`;
        script.async = true;
        script.defer = true;
        script.dataset.cleanopsGoogleMaps = 'true';
        script.onerror = () => reject(new Error('Google Maps failed to load'));

        document.head.appendChild(script);
    });

    return googleMapsLoader;
}

function parseCoordinate(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const coordinate = Number(value);

    return Number.isFinite(coordinate) ? coordinate : null;
}

function readPosition(): LatLngLiteral | null {
    const lat = parseCoordinate(props.latitude);
    const lng = parseCoordinate(props.longitude);

    if (lat === null || lng === null) {
        return null;
    }

    return { lat, lng };
}

function latLngToLiteral(latLng: any): LatLngLiteral | null {
    if (!latLng) {
        return null;
    }

    if (typeof latLng.lat === 'function' && typeof latLng.lng === 'function') {
        return {
            lat: latLng.lat(),
            lng: latLng.lng(),
        };
    }

    if (typeof latLng.lat === 'number' && typeof latLng.lng === 'number') {
        return latLng;
    }

    return null;
}

function setMarker(position: LatLngLiteral, shouldEmit = true): void {
    marker.value?.setPosition(position);
    marker.value?.setVisible(true);
    map.value?.panTo(position);

    if (!shouldEmit) {
        return;
    }

    emit('update:latitude', position.lat.toFixed(7));
    emit('update:longitude', position.lng.toFixed(7));
}

function updateFromLatLng(latLng: any): void {
    const position = latLngToLiteral(latLng);

    if (position) {
        setMarker(position);
    }
}

function setMapView(position: LatLngLiteral, zoom: number): void {
    map.value?.panTo(position);
    map.value?.setZoom(zoom);
}

function updateFromPlace(): void {
    const place = autocomplete.value?.getPlace();
    const position = latLngToLiteral(place?.geometry?.location);

    if (!position) {
        return;
    }

    setMarker(position);

    if (place.geometry?.viewport) {
        map.value?.fitBounds(place.geometry.viewport);
    }

    emit('update:formattedAddress', place.formatted_address ?? place.name ?? '');
    emit('update:placeId', place.place_id ?? '');
}

function focusCoordinates(): void {
    const position = readPosition();

    if (!position || status.value !== 'ready') {
        return;
    }

    setMarker(position, false);
    setMapView(position, 16);
}

function areaQuery(): string {
    return [props.district, props.city, props.countryLabel].filter(Boolean).join(', ');
}

function focusArea(): void {
    if (status.value !== 'ready' || !geocoder.value || !props.city) {
        return;
    }

    geocoder.value.geocode(
        {
            address: areaQuery(),
            componentRestrictions: { country: props.countryCode },
        },
        (results: any[] | null, geocoderStatus: string) => {
            const result = results?.[0];
            const position = latLngToLiteral(result?.geometry?.location);

            if (geocoderStatus !== 'OK' || !position) {
                return;
            }

            if (result.geometry?.viewport) {
                map.value?.fitBounds(result.geometry.viewport);
                return;
            }

            setMapView(position, props.district ? 14 : 11);
        },
    );
}

async function initializeMap(): Promise<void> {
    if (!hasApiKey.value) {
        status.value = 'missing-key';

        return;
    }

    status.value = 'loading';

    try {
        await loadGoogleMaps(apiKey);
        await nextTick();

        const maps = window.google?.maps;
        const initialPosition = readPosition();
        const center = initialPosition ?? defaultCenter;

        if (!maps || !mapElement.value) {
            throw new Error('Google Maps is unavailable');
        }

        map.value = new maps.Map(mapElement.value, {
            center,
            zoom: readPosition() ? 16 : 11,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });
        geocoder.value = new maps.Geocoder();

        marker.value = new maps.Marker({
            map: map.value,
            position: center,
            draggable: true,
            visible: Boolean(initialPosition),
            title: t('customersAdmin.sitePin', 'Site pin'),
        });

        map.value.addListener('click', (event: any) => updateFromLatLng(event.latLng));
        marker.value.addListener('dragend', (event: any) => updateFromLatLng(event.latLng));

        if (searchInput.value && maps.places?.Autocomplete) {
            autocomplete.value = new maps.places.Autocomplete(searchInput.value, {
                componentRestrictions: { country: props.countryCode.toLowerCase() },
                fields: ['formatted_address', 'geometry', 'name', 'place_id'],
            });
            autocomplete.value.addListener('place_changed', updateFromPlace);
        }

        status.value = 'ready';
    } catch {
        status.value = 'error';
    }
}

watch(
    () => [props.latitude, props.longitude],
    () => {
        if (status.value !== 'ready') {
            return;
        }

        const position = readPosition();

        if (position) {
            setMarker(position, false);
        }
    },
);

watch(
    () => props.coordinateFocusRequest,
    () => focusCoordinates(),
);

watch(
    () => props.areaFocusRequest,
    () => focusArea(),
);

onMounted(() => {
    void initializeMap();
});
</script>

<template>
    <div class="space-y-3">
        <div v-if="hasApiKey" class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
                ref="searchInput"
                type="text"
                class="ta-input h-11 w-full ps-10 pe-4 text-sm"
                :placeholder="t('customersAdmin.searchMap', 'Search Saudi locations, then select a pin')"
            >
        </div>

        <div v-if="status === 'missing-key'" class="flex gap-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
            <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">{{ t('customersAdmin.mapKeyMissing', 'Google Maps key is not configured') }}</p>
                <p class="mt-1 leading-6">
                    {{ t('customersAdmin.mapKeyMissingHint', 'You can still enter latitude and longitude manually, then add VITE_GOOGLE_MAPS_API_KEY to enable the pin picker.') }}
                </p>
            </div>
        </div>

        <div v-else-if="status === 'error'" class="flex gap-3 rounded-lg border border-error-200 bg-error-50 p-3 text-sm text-error-700">
            <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">{{ t('customersAdmin.mapLoadFailed', 'Map could not be loaded') }}</p>
                <p class="mt-1 leading-6">
                    {{ t('customersAdmin.mapLoadFailedHint', 'Check the Google Maps key, billing, and allowed domains, or use manual coordinates for now.') }}
                </p>
            </div>
        </div>

        <div v-show="hasApiKey" class="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-100">
            <div ref="mapElement" class="h-72 w-full sm:h-80" />
            <div v-if="status === 'loading' || status === 'idle'" class="absolute inset-0 flex items-center justify-center bg-white/80 text-sm font-semibold text-gray-600">
                {{ t('customersAdmin.loadingMap', 'Loading map...') }}
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <MapPin class="h-4 w-4" />
            </span>
            <span>{{ selectedCoordinates }}</span>
        </div>
    </div>
</template>
