<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import ReviewList from '@/Components/ReviewList.vue';
import ReviewForm from '@/Components/ReviewForm.vue';

const props = defineProps({
    game: {
        type: Object,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
    reviews: {
        type: Array,
        default: () => [],
    },
    priceHistories: {
        type: Object,
        default: () => ({}),
    },
    vouchers: {
        type: Object,
        default: () => ({}),
    },
});

const regionBadge = (region) => {
    const r = (region || '').trim().toLowerCase();
    if (!r || r === 'global') return { emoji: '🌍', label: 'Global', cls: 'bg-green-600/30 text-green-300' };
    if (['eu', 'europe'].includes(r)) return { emoji: '🇪🇺', label: 'Europa', cls: 'bg-blue-600/30 text-blue-300' };
    if (['us', 'usa', 'na', 'north america'].includes(r)) return { emoji: '🇺🇸', label: 'Norteamérica', cls: 'bg-blue-600/30 text-blue-300' };
    if (['latam', 'latin america'].includes(r)) return { emoji: '🌎', label: 'LATAM', cls: 'bg-yellow-600/30 text-yellow-300' };
    if (['ru', 'russia', 'cis'].includes(r)) return { emoji: '🇷🇺', label: 'Rusia/CIS', cls: 'bg-red-600/30 text-red-300' };
    if (['asia', 'apac'].includes(r)) return { emoji: '🌏', label: 'Asia', cls: 'bg-purple-600/30 text-purple-300' };
    return { emoji: null, label: region, cls: 'bg-gray-600/30 text-gray-300' };
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

const genres = computed(() => {
    const g = props.game.genres;
    if (Array.isArray(g)) return g;
    if (typeof g === 'string') {
        try {
            const parsed = JSON.parse(g);
            return Array.isArray(parsed) ? parsed : [g];
        } catch {
            return [g];
        }
    }
    return [];
});

const schemaScript = computed(() => {
    if (!props.seo.schema) return null;
    return JSON.stringify(props.seo.schema);
});

const chartColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

const hasPriceHistory = computed(() => {
    return Object.values(props.priceHistories || {}).some(arr => arr && arr.length > 0);
});

const lowestRealPrice = computed(() => {
    const realProducts = props.products.filter(p => p.is_real_price);
    if (realProducts.length === 0) return null;
    return Math.min(...realProducts.map(p => Number(p.current_price)));
});

const suggestedTargetPrice = computed(() => {
    if (lowestRealPrice.value === null) return '';
    return (lowestRealPrice.value * 0.9).toFixed(2);
});

const page = usePage();

const alertForm = useForm({
    game_id: props.game.id,
    email: '',
    target_price: '',
});

const submitAlert = () => {
    alertForm.target_price = Number(alertForm.target_price);
    alertForm.post(route('alerts.store'), {
        onSuccess: () => {
            alertForm.reset('email', 'target_price');
        },
    });
};

const formatReviewCount = (count) => {
    if (!count) return '';
    if (count >= 1000000) return (count / 1000000).toFixed(count % 1000000 === 0 ? 0 : 1) + 'M';
    if (count >= 1000) return Math.round(count / 1000) + 'K';
    return count.toString();
};

const storeStars = (rating) => {
    if (!rating) return '';
    const full = Math.round(rating);
    return '★'.repeat(full) + '☆'.repeat(5 - full);
};

const copiedCode = ref('');

const getStoreVoucher = (storeId) => {
    return props.vouchers[storeId] || null;
};

const getVoucherPrice = (product) => {
    const voucher = getStoreVoucher(product.store?.id);
    if (!voucher) return Number(product.current_price);
    const price = Number(product.current_price);
    if (voucher.discount_type === 'percentage') {
        return Math.max(0, price - (price * Number(voucher.discount_value) / 100));
    }
    return Math.max(0, price - Number(voucher.discount_value));
};

const copyCode = (code) => {
    navigator.clipboard.writeText(code);
    copiedCode.value = code;
    setTimeout(() => {
        copiedCode.value = '';
    }, 2000);
};

const chartData = computed(() => {
    if (!hasPriceHistory.value) return null;

    const allPoints = [];
    const lines = [];
    let colorIdx = 0;

    for (const product of props.products) {
        const history = props.priceHistories[product.id];
        if (!history || history.length === 0) continue;

        const color = chartColors[colorIdx % chartColors.length];
        colorIdx++;

        const points = history.map(h => ({
            date: new Date(h.recorded_at),
            price: parseFloat(h.price),
        }));

        allPoints.push(...points);
        lines.push({
            storeName: product.store?.name || 'Tienda',
            color,
            points,
        });
    }

    if (allPoints.length === 0) return null;

    const minDate = Math.min(...allPoints.map(p => p.date.getTime()));
    const maxDate = Math.max(...allPoints.map(p => p.date.getTime()));
    const minPrice = Math.min(...allPoints.map(p => p.price));
    const maxPrice = Math.max(...allPoints.map(p => p.price));

    const width = 700;
    const height = 300;
    const padTop = 20;
    const padRight = 20;
    const padBottom = 40;
    const padLeft = 60;

    const chartW = width - padLeft - padRight;
    const chartH = height - padTop - padBottom;

    const xScale = (date) => {
        if (maxDate === minDate) return padLeft + chartW / 2;
        return padLeft + ((date.getTime() - minDate) / (maxDate - minDate)) * chartW;
    };

    const priceRange = maxPrice - minPrice || 1;
    const yScale = (price) => {
        return padTop + chartH - ((price - minPrice) / priceRange) * chartH;
    };

    for (const line of lines) {
        line.path = line.points.map((p, i) => {
            const x = xScale(p.date);
            const y = yScale(p.price);
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');
    }

    const xTicks = [];
    const numXTicks = 6;
    const dateRange = maxDate - minDate || 1;
    for (let i = 0; i <= numXTicks; i++) {
        const t = minDate + (dateRange * i) / numXTicks;
        const date = new Date(t);
        xTicks.push({
            x: xScale(date),
            label: date.toLocaleDateString('es-ES', { month: 'short', year: '2-digit' }),
        });
    }

    const yTicks = [];
    const numYTicks = 5;
    for (let i = 0; i <= numYTicks; i++) {
        const price = minPrice + (priceRange * i) / numYTicks;
        yTicks.push({
            y: yScale(price),
            label: price.toFixed(2) + '\u20AC',
        });
    }

    return {
        width,
        height,
        padLeft,
        padTop,
        chartW,
        chartH,
        lines,
        xTicks,
        yTicks,
    };
});
</script>

<template>
    <Head>
        <title>{{ seo.title || game.title }}</title>
        <meta name="description" :content="seo.description" />
        <link rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.og?.title" />
        <meta property="og:description" :content="seo.og?.description" />
        <meta property="og:image" :content="seo.og?.image" />
        <meta property="og:type" :content="seo.og?.type" />
        <meta property="og:url" :content="seo.canonical" />
        <script v-if="schemaScript" type="application/ld+json">{{ schemaScript }}</script>
    </Head>

    <div class="min-h-screen bg-gray-900 text-white">
        <header class="border-b border-gray-700 bg-gray-800">
            <div class="mx-auto flex max-w-7xl items-center px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="text-2xl font-bold text-white">
                    Game<span class="text-blue-500">Price</span><span class="text-gray-400">.es</span>
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <Breadcrumbs :items="[
                { label: 'Inicio', href: '/' },
                { label: game.title },
            ]" />

            <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <img
                        :src="game.cover_image"
                        :alt="game.title"
                        class="w-full rounded-lg shadow-lg"
                    />
                </div>

                <div class="lg:col-span-2">
                    <div class="flex items-start gap-3">
                        <h1 class="text-4xl font-bold">{{ game.title }}</h1>
                        <span
                            v-if="game.release_date && new Date(game.release_date) > new Date()"
                            class="mt-2 rounded bg-amber-600 px-3 py-1 text-sm font-bold text-white shadow-md"
                        >
                            Próximamente
                        </span>
                    </div>
                    <p class="mt-2 text-gray-400">
                        {{ game.developer }}
                        <span v-if="game.release_date" class="ml-2">&middot; {{ formatDate(game.release_date) }}</span>
                    </p>

                    <div v-if="genres.length" class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-for="genre in genres"
                            :key="genre"
                            :href="route('genre.show', genre)"
                            class="rounded-full bg-gray-700 px-3 py-1 text-xs text-gray-300 transition hover:bg-gray-600 hover:text-white"
                        >
                            {{ genre }}
                        </Link>
                    </div>

                    <p v-if="game.description" class="mt-6 leading-relaxed text-gray-300">
                        {{ game.description }}
                    </p>

                    <div v-if="game.metacritic_score" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-700 px-3 py-1">
                        <span class="text-xs text-gray-400">Metacritic</span>
                        <span class="text-sm font-bold" :class="game.metacritic_score >= 75 ? 'text-green-400' : game.metacritic_score >= 50 ? 'text-yellow-400' : 'text-red-400'">
                            {{ game.metacritic_score }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <h2 class="mb-4 text-2xl font-bold">Comparar precios</h2>

                <div v-if="products.length > 0 && products.some(p => p.is_real_price)" class="overflow-x-auto rounded-lg border border-gray-700">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Tienda</th>
                                <th class="px-4 py-3">Plataforma</th>
                                <th class="px-4 py-3">Regi&oacute;n</th>
                                <th class="px-4 py-3">
                                    <div class="inline-flex items-center gap-1">
                                        Precio
                                        <svg class="h-3 w-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-3">Descuento</th>
                                <th class="px-4 py-3">Ahorro</th>
                                <th class="px-4 py-3">Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <tr v-for="(product, index) in products.filter(p => p.is_real_price)" :key="product.id" class="bg-gray-800 hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="product.store?.logo_url"
                                            :src="product.store.logo_url"
                                            :alt="product.store.name"
                                            class="h-5 w-5 rounded object-contain"
                                        />
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1">
                                                <span class="font-medium">{{ product.store?.name }}</span>
                                                <span
                                                    v-if="product.store?.is_official"
                                                    class="ml-1 rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                                >
                                                    Oficial
                                                </span>
                                                <span
                                                    v-else
                                                    class="ml-1 rounded bg-orange-500 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                                >
                                                    Key Reseller
                                                </span>
                                                <span
                                                    v-if="index === 0"
                                                    class="ml-1 rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                                >
                                                    Mejor oferta
                                                </span>
                                                <span
                                                    v-if="product.is_real_price"
                                                    class="ml-1 rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                                    title="Precio verificado de tienda oficial"
                                                >
                                                    Precio real
                                                </span>
                                                <span
                                                    v-else
                                                    class="ml-1 rounded bg-gray-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white"
                                                    title="Precio estimado, puede no ser exacto"
                                                >
                                                    Estimado
                                                </span>
                                            </div>
                                            <span v-if="product.store?.rating" class="text-xs text-yellow-400">
                                                {{ storeStars(product.store.rating) }} <span class="text-gray-400">({{ product.store.rating }} · {{ formatReviewCount(product.store.review_count) }} reseñas)</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ product.platform || 'Steam' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium"
                                        :class="regionBadge(product.region).cls"
                                    >
                                        <span v-if="regionBadge(product.region).emoji">{{ regionBadge(product.region).emoji }}</span>
                                        {{ regionBadge(product.region).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-bold text-green-400">{{ Number(product.current_price).toFixed(2) }}&euro;</span>
                                    <span v-if="product.original_price && Number(product.original_price) > Number(product.current_price)" class="ml-2 text-xs text-gray-500 line-through">
                                        {{ Number(product.original_price).toFixed(2) }}&euro;
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="Number(product.discount_percentage) > 0" class="font-bold text-red-400">
                                        -{{ product.discount_percentage }}%
                                    </span>
                                    <span v-else class="text-gray-500">&mdash;</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="product.original_price && Number(product.original_price) > Number(product.current_price)" class="font-semibold text-blue-400">
                                        {{ (Number(product.original_price) - Number(product.current_price)).toFixed(2) }}&euro;
                                    </span>
                                    <span v-else class="text-gray-500">&mdash;</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        :href="product.affiliate_url || product.url"
                                        target="_blank"
                                        rel="nofollow"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                                    >
                                        Ver oferta
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="rounded-lg bg-gray-800 p-8 text-center text-gray-400">
                    No hay precios disponibles para este juego.
                </p>
            </div>

            <div class="mt-10">
                <h2 class="mb-4 text-2xl font-bold">&#x1F4C8; Historial de precios</h2>

                <div v-if="hasPriceHistory && chartData" class="rounded-lg border border-gray-700 bg-gray-800 p-4">
                    <div class="mb-4 flex flex-wrap gap-3">
                        <span v-for="line in chartData.lines" :key="line.storeName" class="flex items-center gap-1.5 text-sm text-gray-300">
                            <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: line.color }"></span>
                            {{ line.storeName }}
                        </span>
                    </div>
                    <div class="w-full overflow-x-auto">
                        <svg :viewBox="`0 0 ${chartData.width} ${chartData.height}`" class="w-full min-w-[600px]" preserveAspectRatio="xMidYMid meet">
                            <g v-for="tick in chartData.yTicks" :key="'y'+tick.y">
                                <line :x1="chartData.padLeft" :y1="tick.y" :x2="chartData.padLeft + chartData.chartW" :y2="tick.y" stroke="#374151" stroke-width="0.5" />
                                <text :x="chartData.padLeft - 8" :y="tick.y" text-anchor="end" dominant-baseline="middle" fill="#9ca3af" font-size="11">{{ tick.label }}</text>
                            </g>
                            <g v-for="tick in chartData.xTicks" :key="'x'+tick.x">
                                <line :x1="tick.x" :y1="chartData.padTop" :x2="tick.x" :y2="chartData.padTop + chartData.chartH" stroke="#374151" stroke-width="0.5" />
                                <text :x="tick.x" :y="chartData.height - 8" text-anchor="middle" fill="#9ca3af" font-size="11">{{ tick.label }}</text>
                            </g>
                            <line :x1="chartData.padLeft" :y1="chartData.padTop + chartData.chartH" :x2="chartData.padLeft + chartData.chartW" :y2="chartData.padTop + chartData.chartH" stroke="#4b5563" stroke-width="1" />
                            <line :x1="chartData.padLeft" :y1="chartData.padTop" :x2="chartData.padLeft" :y2="chartData.padTop + chartData.chartH" stroke="#4b5563" stroke-width="1" />
                            <path v-for="line in chartData.lines" :key="line.storeName" :d="line.path" fill="none" :stroke="line.color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>

                <p v-else class="rounded-lg bg-gray-800 p-8 text-center text-gray-400">
                    No hay datos hist\u00f3ricos disponibles
                </p>
            </div>

            <div class="mt-10">
                <h2 class="mb-4 text-2xl font-bold">&#x1F514; Alertas de precio</h2>

                <div class="rounded-lg border border-gray-700 bg-gray-800 p-6">
                    <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-green-900/50 border border-green-700 p-3 text-sm text-green-300">
                        {{ page.props.flash.success }}
                    </div>
                    <div v-if="page.props.flash?.error" class="mb-4 rounded-lg bg-red-900/50 border border-red-700 p-3 text-sm text-red-300">
                        {{ page.props.flash.error }}
                    </div>

                    <form v-if="lowestRealPrice !== null" @submit.prevent="submitAlert" class="space-y-4">
                        <p v-if="lowestRealPrice !== null" class="text-sm text-gray-400">
                            Precio actual: <span class="font-bold text-green-400">{{ lowestRealPrice.toFixed(2) }}&euro;</span>
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm text-gray-400">Email</label>
                                <input
                                    v-model="alertForm.email"
                                    type="email"
                                    required
                                    class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                                    placeholder="tu@email.com"
                                />
                                <p v-if="alertForm.errors.email" class="mt-1 text-sm text-red-400">{{ alertForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm text-gray-400">Precio objetivo (&euro;)</label>
                                <input
                                    v-model="alertForm.target_price"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                                    :placeholder="suggestedTargetPrice"
                                />
                                <p v-if="alertForm.errors.target_price" class="mt-1 text-sm text-red-400">{{ alertForm.errors.target_price }}</p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="alertForm.processing"
                            class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            Crear alerta
                        </button>
                    </form>

                    <p v-else class="text-gray-400">No hay precios disponibles para crear una alerta.</p>
                </div>
            </div>

            <div class="mt-10 space-y-6">
                <ReviewList :reviews="reviews" />
                <ReviewForm :game-slug="game.slug" />
            </div>
        </main>
    </div>
</template>
