<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

const query = ref('');
const suggestions = ref({ db: [], steam: [] });
const isOpen = ref(false);
const selectedIndex = ref(-1);
const loading = ref(false);
const onDemandLoading = ref(false);
const onDemandError = ref('');
const containerRef = ref(null);
let debounceTimer = null;

const allSuggestions = computed(() => {
    const items = [];
    suggestions.value.db.forEach((g) => {
        items.push({ type: 'db', ...g });
    });
    suggestions.value.steam.forEach((g) => {
        items.push({ type: 'steam', ...g });
    });
    return items;
});

const hasResults = computed(() => allSuggestions.value.length > 0);

function fetchSuggestions(term) {
    if (!term || term.length < 3) {
        suggestions.value = { db: [], steam: [] };
        isOpen.value = false;
        return;
    }

    loading.value = true;
    onDemandError.value = '';

    fetch(`/api/search/suggestions?query=${encodeURIComponent(term)}`)
        .then((res) => res.json())
        .then((data) => {
            suggestions.value = { db: data.db || [], steam: data.steam || [] };
            isOpen.value = true;
            selectedIndex.value = -1;
        })
        .catch(() => {
            suggestions.value = { db: [], steam: [] };
        })
        .finally(() => {
            loading.value = false;
        });
}

function onInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchSuggestions(query.value.trim());
    }, 300);
}

function selectItem(item) {
    if (item.type === 'db') {
        isOpen.value = false;
        router.get(route('game.show', item.slug));
    } else if (item.type === 'steam') {
        triggerOnDemand(item.name);
    }
}

function triggerOnDemand(searchQuery) {
    if (!searchQuery || searchQuery.length < 2) return;

    isOpen.value = false;
    onDemandLoading.value = true;
    onDemandError.value = '';

    router.post(route('search.steam'), { query: searchQuery }, {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => {
            onDemandError.value = errors.query || `No encontramos '${searchQuery}' ni en nuestra base de datos ni en Steam`;
            onDemandLoading.value = false;
        },
        onFinish: () => {
            onDemandLoading.value = false;
        },
    });
}

function onKeydown(e) {
    if (!isOpen.value || !hasResults.value) {
        if (e.key === 'Enter' && query.value.trim().length >= 2) {
            e.preventDefault();
            triggerOnDemand(query.value.trim());
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, allSuggestions.value.length - 1);
        scrollToSelected();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, -1);
        scrollToSelected();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex.value >= 0 && selectedIndex.value < allSuggestions.value.length) {
            selectItem(allSuggestions.value[selectedIndex.value]);
        } else {
            triggerOnDemand(query.value.trim());
        }
    } else if (e.key === 'Escape') {
        isOpen.value = false;
    }
}

function scrollToSelected() {
    nextTick(() => {
        const el = containerRef.value?.querySelector(`[data-index="${selectedIndex.value}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    });
}

function handleClickOutside(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    clearTimeout(debounceTimer);
});

function onFocus() {
    if (query.value.trim().length >= 3 && hasResults.value) {
        isOpen.value = true;
    }
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg v-if="!onDemandLoading && !loading" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <svg v-else class="h-5 w-5 animate-spin text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <input
                v-model="query"
                type="text"
                @input="onInput"
                @keydown="onKeydown"
                @focus="onFocus"
                placeholder="Buscar juegos..."
                class="w-full rounded-lg border border-gray-600 bg-gray-800 py-2 pl-10 pr-4 text-sm text-white placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </div>

        <div v-if="onDemandLoading" class="mt-1 text-xs text-blue-400 animate-pulse">
            Buscando en Steam...
        </div>
        <div v-if="onDemandError" class="mt-1 text-xs text-red-400">
            {{ onDemandError }}
        </div>

        <div
            v-if="isOpen && query.trim().length >= 3"
            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-80 overflow-y-auto rounded-lg border border-gray-600 bg-gray-800 shadow-xl"
        >
            <template v-if="hasResults">
                <div
                    v-for="(item, index) in allSuggestions"
                    :key="item.type === 'db' ? item.slug : item.appid"
                    :data-index="index"
                    @click="selectItem(item)"
                    @mouseenter="selectedIndex = index"
                    :class="[
                        'flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors',
                        selectedIndex === index ? 'bg-gray-700' : 'hover:bg-gray-700',
                    ]"
                >
                    <img
                        v-if="item.type === 'db' && item.cover_image"
                        :src="item.cover_image"
                        :alt="item.title"
                        class="h-10 w-10 flex-shrink-0 rounded object-cover"
                    />
                    <div
                        v-else-if="item.type === 'steam' && item.tiny_image"
                        class="h-10 w-10 flex-shrink-0 overflow-hidden rounded bg-gray-700"
                    >
                        <img :src="item.tiny_image" :alt="item.name" class="h-full w-full object-cover" />
                    </div>
                    <div
                        v-else
                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded bg-gray-700 text-xs text-gray-400"
                    >
                        ?
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">
                            {{ item.type === 'db' ? item.title : item.name }}
                        </p>
                        <p v-if="item.type === 'db' && item.release_date" class="text-xs text-gray-400">
                            {{ formatDate(item.release_date) }}
                        </p>
                    </div>
                    <span
                        v-if="item.type === 'steam'"
                        class="flex-shrink-0 rounded bg-blue-600/20 px-2 py-0.5 text-xs font-medium text-blue-300"
                    >
                        🌐 Steam
                    </span>
                </div>
            </template>
            <div v-else-if="!loading" class="px-4 py-3 text-center text-sm text-gray-400">
                No se encontraron resultados
            </div>
        </div>
    </div>
</template>
