<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
});

const showCreateForm = ref(false);
const createForm = useForm({
    name: '',
    color: '#6366f1',
    description: '',
});

const editProject = ref(null);
const editForm = useForm({
    name: '',
    color: '',
    description: '',
});

const createProject = () => {
    createForm.post(route('projects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateForm.value = false;
            createForm.reset();
            createForm.color = '#6366f1';
        },
    });
};

const openEdit = (project) => {
    editProject.value = project;
    editForm.name = project.name;
    editForm.color = project.color;
    editForm.description = project.description || '';
};

const updateProject = () => {
    editForm.put(route('projects.update', editProject.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editProject.value = null;
        },
    });
};

const deleteProject = (project) => {
    if (confirm(`Are you sure you want to delete "${project.name}"?`)) {
        router.delete(route('projects.destroy', project.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Projects
                </h2>
                <button
                    @click="showCreateForm = !showCreateForm"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    {{ showCreateForm ? 'Cancel' : '+ New Project' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div v-if="showCreateForm" class="mb-6 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 dark:shadow-gray-900/20">
                    <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Create Project</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <input
                            v-model="createForm.name"
                            type="text"
                            placeholder="Project name"
                            class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                        <input
                            v-model="createForm.color"
                            type="color"
                            class="h-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                        />
                        <input
                            v-model="createForm.description"
                            type="text"
                            placeholder="Description (optional)"
                            class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                        <button
                            @click="createProject"
                            :disabled="createForm.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Create
                        </button>
                    </div>
                </div>

                <div v-if="editProject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-black/30">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Edit Project</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input
                                    v-model="editForm.name"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                                <input
                                    v-model="editForm.color"
                                    type="color"
                                    class="h-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                <input
                                    v-model="editForm.description"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                @click="editProject = null"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Cancel
                            </button>
                            <button
                                @click="updateProject"
                                :disabled="editForm.processing"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="group flex items-center justify-between rounded-lg bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800 dark:shadow-gray-900/20"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="h-4 w-4 rounded-full"
                                :style="{ backgroundColor: project.color }"
                            ></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ project.name }}</p>
                                <p v-if="project.description" class="text-xs text-gray-500 dark:text-gray-400">{{ project.description }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ project.time_entries_count }} entries</span>
                            <div class="hidden gap-1 group-hover:flex">
                                <button
                                    @click="openEdit(project)"
                                    class="rounded p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteProject(project)"
                                    class="rounded p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="projects.length === 0" class="rounded-lg bg-white p-8 text-center shadow-sm dark:bg-gray-800 dark:shadow-gray-900/20">
                        <p class="text-gray-500 dark:text-gray-400">No projects yet.</p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Create your first project to start tracking time.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
