<script setup>
defineProps({
    reviews: {
        type: Array,
        default: () => [],
    },
});

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <div>
        <h3 class="mb-4 text-lg font-bold text-white">Reseñas</h3>

        <div v-if="reviews.length === 0" class="rounded-lg border border-gray-700 bg-gray-800 p-6 text-center text-gray-400">
            Aún no hay reseñas. ¡Sé el primero en opinar!
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="review in reviews"
                :key="review.id"
                class="rounded-lg border border-gray-700 bg-gray-800 p-4"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex">
                            <span
                                v-for="star in 5"
                                :key="star"
                                class="text-lg"
                                :class="star <= review.rating ? 'text-yellow-400' : 'text-gray-600'"
                            >
                            &#9733;
                            </span>
                        </div>
                        <span v-if="review.user_name" class="text-sm font-medium text-white">
                            {{ review.user_name }}
                        </span>
                        <span v-else class="text-sm text-gray-500">Anónimo</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ formatDate(review.created_at) }}</span>
                </div>

                <p v-if="review.comment" class="mt-2 text-sm leading-relaxed text-gray-300">
                    {{ review.comment }}
                </p>
            </div>
        </div>
    </div>
</template>
