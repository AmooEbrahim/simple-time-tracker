<script setup>
import { computed, ref } from 'vue';
import { Search, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: [Number, String],
    projects: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'No project',
    },
    allowEmpty: {
        type: Boolean,
        default: true,
    },
    class: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const isOpen = ref(false);

const selectedValue = computed({
    get: () => props.modelValue ?? '',
    set: (val) => emit('update:modelValue', val === '' ? null : Number(val)),
});

const flatOptions = computed(() => {
    const options = [];
    for (const project of props.projects) {
        options.push({
            id: project.id,
            name: project.name,
            color: project.color,
            isSub: false,
            searchText: project.name.toLowerCase(),
        });
        if (project.children && project.children.length > 0) {
            for (const child of project.children) {
                options.push({
                    id: child.id,
                    name: child.name,
                    color: child.color,
                    isSub: true,
                    parentName: project.name,
                    searchText: `${project.name} ${child.name}`.toLowerCase(),
                });
            }
        }
    }
    return options;
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) return flatOptions.value;
    const query = searchQuery.value.toLowerCase();
    return flatOptions.value.filter((opt) => opt.searchText.includes(query));
});

const selectedProject = computed(() => {
    if (!selectedValue.value) return null;
    return flatOptions.value.find((o) => o.id === selectedValue.value) || null;
});

const selectOption = (opt) => {
    selectedValue.value = opt.id;
    isOpen.value = false;
    searchQuery.value = '';
};

const clearSelection = () => {
    selectedValue.value = '';
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        searchQuery.value = '';
    }
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};
</script>

<template>
    <div class="relative" :class="class">
        <div
            class="flex cursor-pointer items-center rounded-xl border-0 bg-slate-100 py-3 pl-8 pr-9 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200 focus:bg-slate-200 focus:ring-2 focus:ring-indigo-500/30 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:bg-slate-700"
            @click="toggleDropdown"
        >
            <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2">
                <span
                    class="block h-2.5 w-2.5 rounded-full"
                    :style="{ backgroundColor: selectedProject ? selectedProject.color : '#cbd5e1' }"
                />
            </div>
            <span class="flex-1 truncate">
                {{ selectedProject ? (selectedProject.isSub ? `${selectedProject.parentName} / ${selectedProject.name}` : selectedProject.name) : placeholder }}
            </span>
            <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="sticky top-0 border-b border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-800">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search..."
                            class="w-full rounded-lg border-0 bg-slate-100 py-1.5 pl-8 pr-8 text-sm text-slate-700 placeholder:text-slate-400 focus:bg-slate-200 focus:ring-0 dark:bg-slate-700 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:bg-slate-600"
                            @click.stop
                        />
                        <button
                            v-if="searchQuery"
                            @click.stop="searchQuery = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <div class="max-h-44 overflow-y-auto">
                    <button
                        v-if="allowEmpty"
                        @click.stop="clearSelection"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                        {{ placeholder }}
                    </button>

                    <button
                        v-for="opt in filteredOptions"
                        :key="opt.id"
                        @click.stop="selectOption(opt)"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                        :class="[
                            selectedValue === opt.id ? 'bg-slate-100 font-medium dark:bg-slate-700' : 'text-slate-700 dark:text-slate-200',
                            opt.isSub ? 'pl-6' : ''
                        ]"
                    >
                        <span
                            class="rounded-full"
                            :class="opt.isSub ? 'h-2 w-2' : 'h-2.5 w-2.5'"
                            :style="{ backgroundColor: opt.color }"
                        ></span>
                        <template v-if="opt.isSub">
                            <span class="text-slate-400 dark:text-slate-500">{{ opt.parentName }} /</span>
                            {{ opt.name }}
                        </template>
                        <template v-else>
                            {{ opt.name }}
                        </template>
                    </button>

                    <div
                        v-if="filteredOptions.length === 0"
                        class="px-3 py-4 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No results found
                    </div>
                </div>
            </div>
        </transition>

        <div v-if="isOpen" class="fixed inset-0 z-40" @click="closeDropdown"></div>
    </div>
</template>
