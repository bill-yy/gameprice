<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    gameSlug: {
        type: String,
        required: true,
    },
});

const selectedRating = ref(0);
const hoverRating = ref(0);

const form = useForm({
    rating: 0,
    comment: '',
    user_name: '',
    user_email: '',
});

const setRating = (rating) => {
    selectedRating.value = rating;
    form.rating = rating;
};

const submit = () => {
    form.post(route('reviews.store', props.gameSlug), {
        onSuccess: () => {
            form.reset();
            selectedRating.value = 0;
            hoverRating.value = 0;
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="rounded-lg border border-gray-700 bg-gray-800 p-6">
        <h3 class="mb-4 text-lg font-bold text-white">Deja tu reseña</h3>

        <div class="mb-4">
            <label class="mb-1 block text-sm text-gray-400">Puntuación *</label>
            <div class="flex gap-1">
                <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    class="text-2xl transition"
                    :class="(hoverRating || selectedRating) >= star ? 'text-yellow-400' : 'text-gray-600'"
                    @mouseenter="hoverRating = star"
                    @mouseleave="hoverRating = 0"
                    @click="setRating(star)"
                >
                &#9733;
                </button>
            </div>
            <p v-if="form.errors.rating" class="mt-1 text-sm text-red-400">{{ form.errors.rating }}</p>
        </div>

        <div class="mb-4">
            <label class="mb-1 block text-sm text-gray-400">Nombre (opcional)</label>
            <input
                v-model="form.user_name"
                type="text"
                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                placeholder="Tu nombre"
            />
            <p v-if="form.errors.user_name" class="mt-1 text-sm text-red-400">{{ form.errors.user_name }}</p>
        </div>

        <div class="mb-4">
            <label class="mb-1 block text-sm text-gray-400">Email (opcional)</label>
            <input
                v-model="form.user_email"
                type="email"
                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                placeholder="tu@email.com"
            />
            <p v-if="form.errors.user_email" class="mt-1 text-sm text-red-400">{{ form.errors.user_email }}</p>
        </div>

        <div class="mb-4">
            <label class="mb-1 block text-sm text-gray-400">Comentario (opcional)</label>
            <textarea
                v-model="form.comment"
                rows="4"
                class="w-full rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none"
                placeholder="¿Qué opinas de este juego?"
            ></textarea>
            <p v-if="form.errors.comment" class="mt-1 text-sm text-red-400">{{ form.errors.comment }}</p>
        </div>

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
        >
            Publicar reseña
        </button>
    </form>
</template>
