<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    game: {
        type: Object,
        required: true,
    },
});

const lowestPrice = (products) => {
    if (!products?.length) return null;
    return Math.min(...products.map((p) => Number(p.current_price)));
};

const maxDiscount = (products) => {
    if (!products?.length) return 0;
    return Math.max(...products.map((p) => Number(p.discount_percent)));
};
</script>

<template>
    <Link
        :href="route('game.show', game.slug)"
        class="group block overflow-hidden rounded-lg bg-gray-800 transition-all duration-200 hover:ring-2 hover:ring-blue-500"
    >
        <img
            :src="game.cover_image"
            :alt="game.title"
            class="h-40 w-full object-cover"
        />
        <div class="p-4">
            <h3 class="truncate text-sm font-semibold text-white">
                {{ game.title }}
            </h3>
            <p class="mt-1 text-xs text-gray-400">{{ game.developer }}</p>
            <div class="mt-2 flex items-center justify-between">
                <span v-if="lowestPrice(game.products) !== null" class="text-sm font-bold text-green-400">
                    desde {{ lowestPrice(game.products).toFixed(2) }}&euro;
                </span>
                <span v-else class="text-xs text-gray-500">Sin precios</span>
                <span
                    v-if="maxDiscount(game.products) > 0"
                    class="rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white"
                >
                    -{{ maxDiscount(game.products) }}%
                </span>
            </div>
        </div>
    </Link>
</template>
