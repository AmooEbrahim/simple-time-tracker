<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, X, Pencil, Trash2, Folder, Archive, ArchiveRestore, ChevronDown, ChevronRight } from 'lucide-vue-next';

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

const subProjectParent = ref(null);
const subProjectForm = useForm({
    parent_id: null,
    name: '',
    color: '#6366f1',
    description: '',
});

const expandedProjects = ref(new Set());

const toggleExpand = (projectId) => {
    if (expandedProjects.value.has(projectId)) {
        expandedProjects.value.delete(projectId);
    } else {
        expandedProjects.value.add(projectId);
    }
};

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
    const msg = project.parent_id
        ? `Delete sub-project "${project.name}"? Time entries will be moved to the parent project.`
        : `Delete "${project.name}" and all its sub-projects? Time entries will be unassigned from sub-projects.`;
    if (confirm(msg)) {
        router.delete(route('projects.destroy', project.id), {
            preserveScroll: true,
        });
    }
};

const archiveProject = (project) => {
    router.post(route('projects.archive', project.id), {}, {
        preserveScroll: true,
    });
};

const unarchiveProject = (project) => {
    router.post(route('projects.unarchive', project.id), {}, {
        preserveScroll: true,
    });
};

const openSubProjectForm = (parent) => {
    subProjectParent.value = parent;
    subProjectForm.parent_id = parent.id;
    subProjectForm.name = '';
    subProjectForm.color = parent.color || '#6366f1';
    subProjectForm.description = '';
};

const closeSubProjectForm = () => {
    subProjectParent.value = null;
    subProjectForm.reset();
};

const createSubProject = () => {
    subProjectForm.post(route('projects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeSubProjectForm();
            if (subProjectParent.value) {
                expandedProjects.value.add(subProjectParent.value.id);
            }
        },
    });
};
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Projects</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Group your work by project and sub-project to see where time goes.
                    </p>
                </div>
                <button
                    @click="showCreateForm = !showCreateForm"
                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-3 py-2 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg"
                >
                    <Plus v-if="!showCreateForm" class="h-4 w-4" />
                    <X v-else class="h-4 w-4" />
                    {{ showCreateForm ? 'Cancel' : 'New project' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-if="showCreateForm"
                        class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Create project</h3>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input
                                v-model="createForm.color"
                                type="color"
                                class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                            />
                            <input
                                v-model="createForm.name"
                                type="text"
                                placeholder="Project name"
                                class="flex-1 rounded-lg border-slate-200 bg-slate-50 text-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500"
                            />
                            <input
                                v-model="createForm.description"
                                type="text"
                                placeholder="Description (optional)"
                                class="flex-1 rounded-lg border-slate-200 bg-slate-50 text-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500"
                            />
                            <button
                                @click="createProject"
                                :disabled="createForm.processing"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                            >
                                Create
                            </button>
                        </div>
                    </div>
                </transition>

                <transition
                    enter-active-class="transition duration-150"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition duration-100"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="editProject"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
                        @click.self="editProject = null"
                    >
                        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                            <div class="mb-5 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Edit project</h3>
                                <button
                                    @click="editProject = null"
                                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                                    <input
                                        v-model="editForm.name"
                                        type="text"
                                        class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Color</label>
                                    <input
                                        v-model="editForm.color"
                                        type="color"
                                        class="h-10 w-full rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Description</label>
                                    <input
                                        v-model="editForm.description"
                                        type="text"
                                        class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    />
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <button
                                    @click="editProject = null"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="updateProject"
                                    :disabled="editForm.processing"
                                    class="rounded-lg bg-gradient-to-br from-indigo-500 to-fuchsia-500 px-5 py-2 text-sm font-medium text-white shadow-md shadow-indigo-500/25 transition hover:shadow-lg disabled:opacity-50"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <div class="space-y-2">
                    <template v-for="project in projects" :key="project.id">
                        <div
                            class="group overflow-hidden rounded-xl border transition hover:shadow-md"
                            :class="project.is_archived
                                ? 'border-slate-200/50 bg-slate-50 opacity-75 hover:border-slate-300 dark:border-slate-800/50 dark:bg-slate-900/50 dark:hover:border-slate-700'
                                : 'border-slate-200/70 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700'"
                        >
                            <div class="flex items-center justify-between p-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <button
                                        v-if="project.children && project.children.length > 0"
                                        @click="toggleExpand(project.id)"
                                        class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                    >
                                        <ChevronDown v-if="expandedProjects.has(project.id)" class="h-4 w-4" />
                                        <ChevronRight v-else class="h-4 w-4" />
                                    </button>
                                    <div v-else class="w-6"></div>
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                        :class="project.is_archived ? 'opacity-50' : ''"
                                        :style="{ backgroundColor: `${project.color}20`, color: project.color }"
                                    >
                                        <Folder class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="truncate text-sm font-medium" :class="project.is_archived ? 'text-slate-500 dark:text-slate-400' : 'text-slate-900 dark:text-white'">{{ project.name }}</p>
                                            <span v-if="project.is_archived" class="inline-flex items-center gap-1 rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                                <Archive class="h-2.5 w-2.5" />
                                                Archived
                                            </span>
                                        </div>
                                        <p v-if="project.description" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ project.description }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="hidden text-xs text-slate-500 dark:text-slate-400 sm:inline">
                                        {{ project.time_entries_count }} {{ project.time_entries_count === 1 ? 'entry' : 'entries' }}
                                    </span>
                                    <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            v-if="!project.is_archived"
                                            @click="openSubProjectForm(project)"
                                            class="rounded-md p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400"
                                            title="Add sub-project"
                                        >
                                            <Plus class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="openEdit(project)"
                                            class="rounded-md p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="project.is_archived"
                                            @click="unarchiveProject(project)"
                                            class="rounded-md p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-400"
                                            title="Unarchive"
                                        >
                                            <ArchiveRestore class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            @click="archiveProject(project)"
                                            class="rounded-md p-1.5 text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/15 dark:hover:text-amber-400"
                                            title="Archive"
                                        >
                                            <Archive class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="deleteProject(project)"
                                            class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-project form -->
                            <transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div
                                    v-if="subProjectParent && subProjectParent.id === project.id"
                                    class="border-t border-slate-200/70 bg-slate-50/50 px-3 py-3 dark:border-slate-800 dark:bg-slate-800/30"
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <input
                                            v-model="subProjectForm.color"
                                            type="color"
                                            class="h-8 w-10 shrink-0 cursor-pointer rounded border-slate-200 dark:border-slate-700 dark:bg-slate-800"
                                        />
                                        <input
                                            v-model="subProjectForm.name"
                                            type="text"
                                            placeholder="Sub-project name"
                                            class="flex-1 rounded-lg border-slate-200 bg-white text-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500"
                                        />
                                        <button
                                            @click="createSubProject"
                                            :disabled="subProjectForm.processing"
                                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                                        >
                                            Add
                                        </button>
                                        <button
                                            @click="closeSubProjectForm"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </transition>

                            <!-- Children -->
                            <transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0"
                                enter-to-class="opacity-100"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0"
                            >
                                <div
                                    v-if="expandedProjects.has(project.id) && project.children && project.children.length > 0"
                                    class="border-t border-slate-200/70 bg-slate-50/30 dark:border-slate-800 dark:bg-slate-800/20"
                                >
                                    <div
                                        v-for="child in project.children"
                                        :key="child.id"
                                        class="group/child flex items-center justify-between border-b border-slate-200/50 px-3 py-2 pl-12 last:border-b-0 dark:border-slate-800/50"
                                        :class="child.is_archived ? 'opacity-60' : ''"
                                    >
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                                :class="child.is_archived ? 'opacity-50' : ''"
                                                :style="{ backgroundColor: `${child.color}20`, color: child.color }"
                                            >
                                                <Folder class="h-3 w-3" />
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <p class="truncate text-xs font-medium" :class="child.is_archived ? 'text-slate-500 dark:text-slate-400' : 'text-slate-700 dark:text-slate-300'">{{ child.name }}</p>
                                                    <span v-if="child.is_archived" class="inline-flex items-center gap-0.5 rounded bg-slate-200 px-1 py-px text-[9px] font-medium uppercase tracking-wider text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                                        <Archive class="h-2 w-2" />
                                                        Archived
                                                    </span>
                                                </div>
                                                <p v-if="child.description" class="truncate text-[11px] text-slate-400 dark:text-slate-500">{{ child.description }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                                {{ child.time_entries_count }} {{ child.time_entries_count === 1 ? 'entry' : 'entries' }}
                                            </span>
                                            <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover/child:opacity-100">
                                                <button
                                                    @click="openEdit(child)"
                                                    class="rounded p-1 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-400"
                                                >
                                                    <Pencil class="h-3 w-3" />
                                                </button>
                                                <button
                                                    v-if="child.is_archived"
                                                    @click="unarchiveProject(child)"
                                                    class="rounded p-1 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/15 dark:hover:text-emerald-400"
                                                    title="Unarchive"
                                                >
                                                    <ArchiveRestore class="h-3 w-3" />
                                                </button>
                                                <button
                                                    v-else
                                                    @click="archiveProject(child)"
                                                    class="rounded p-1 text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/15 dark:hover:text-amber-400"
                                                    title="Archive"
                                                >
                                                    <Archive class="h-3 w-3" />
                                                </button>
                                                <button
                                                    @click="deleteProject(child)"
                                                    class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                                >
                                                    <Trash2 class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </template>

                    <div
                        v-if="projects.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-100 to-fuchsia-100 text-indigo-600 dark:from-indigo-500/15 dark:to-fuchsia-500/15 dark:text-indigo-300">
                            <Folder class="h-5 w-5" />
                        </div>
                        <p class="font-medium text-slate-900 dark:text-white">No projects yet</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Create your first project to start tracking time.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
