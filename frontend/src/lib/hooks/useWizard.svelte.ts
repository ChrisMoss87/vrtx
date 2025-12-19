/**
 * Wizard State Management Hook
 *
 * Manages multi-step wizard state including:
 * - Current step tracking
 * - Navigation between steps
 * - Step validation state
 * - Completion tracking
 * - Draft saving/loading (local and API-based)
 * - Conditional step logic
 */

import type { ConditionalRule } from '$lib/wizard/conditionalLogic';
import {
	getNextVisibleStepIndex,
	shouldSkipStep,
	getVisibleStepIndices
} from '$lib/wizard/conditionalLogic';
import {
	saveDraft as apiSaveDraft,
	autoSaveDraft,
	getDraft,
	deleteDraft,
	type WizardDraft,
	type CreateDraftPayload
} from '$lib/api/wizard-drafts';

export interface WizardStep {
	id: string;
	title: string;
	description?: string;
	isValid?: boolean;
	isComplete?: boolean;
	isSkipped?: boolean;
	canSkip?: boolean;
	conditionalLogic?: ConditionalRule;
}

export interface WizardState {
	steps: WizardStep[];
	currentStepIndex: number;
	isComplete: boolean;
	formData: Record<string, any>;
}

export interface WizardOptions {
	/** Wizard type identifier for API-based drafts */
	wizardType?: string;
	/** Reference ID (e.g., module ID being edited) */
	referenceId?: string;
	/** Use API-based draft storage instead of localStorage */
	useApiDrafts?: boolean;
	/** Auto-save interval in milliseconds (default: 30000 = 30 seconds) */
	autoSaveInterval?: number;
	/** Debounce delay for auto-save on data changes (default: 2000 = 2 seconds) */
	autoSaveDebounce?: number;
	/** Callback when draft is saved */
	onDraftSaved?: (draftId: number) => void;
	/** Callback when draft save fails */
	onDraftSaveError?: (error: Error) => void;
}

export function createWizardStore(
	initialSteps: WizardStep[],
	initialData: Record<string, any> = {},
	options: WizardOptions = {}
) {
	const {
		wizardType = 'default',
		referenceId,
		useApiDrafts = false,
		autoSaveInterval = 30000,
		autoSaveDebounce = 2000,
		onDraftSaved,
		onDraftSaveError
	} = options;

	let state = $state<WizardState>({
		steps: initialSteps.map((step) => ({ ...step, isValid: false, isComplete: false })),
		currentStepIndex: 0,
		isComplete: false,
		formData: { ...initialData }
	});

	// Draft management state
	let draftId = $state<number | null>(null);
	let isSaving = $state(false);
	let lastSaved = $state<Date | null>(null);
	let saveError = $state<string | null>(null);
	let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;
	let debounceTimer: ReturnType<typeof setTimeout> | null = null;

	const currentStep = $derived(state.steps[state.currentStepIndex]);
	const isFirstStep = $derived(state.currentStepIndex === 0);
	const isLastStep = $derived(state.currentStepIndex === state.steps.length - 1);
	const canGoNext = $derived(currentStep?.isValid || currentStep?.canSkip || false);
	const canGoPrevious = $derived(state.currentStepIndex > 0);
	const completedSteps = $derived(state.steps.filter((s) => s.isComplete).length);
	const totalSteps = $derived(state.steps.length);
	const progress = $derived((completedSteps / totalSteps) * 100);

	// Visible steps (accounting for conditional logic)
	const visibleStepIndices = $derived(getVisibleStepIndices(state.steps, state.formData));
	const visibleSteps = $derived(visibleStepIndices.map((i) => state.steps[i]));
	const visibleProgress = $derived(() => {
		const visibleCompleted = visibleStepIndices.filter((i) => state.steps[i].isComplete).length;
		return (visibleCompleted / visibleStepIndices.length) * 100;
	});

	function goToStep(index: number) {
		if (index >= 0 && index < state.steps.length) {
			state.currentStepIndex = index;
		}
	}

	function goNext() {
		if (canGoNext && !isLastStep) {
			// Mark current step as complete
			state.steps[state.currentStepIndex].isComplete = true;

			// Get next visible step (skip conditionally hidden steps)
			const nextIndex = getNextVisibleStepIndex(
				state.currentStepIndex,
				state.steps,
				state.formData,
				'forward'
			);

			state.currentStepIndex = nextIndex;
			scheduleDraftSave();
		}
	}

	function goPrevious() {
		if (canGoPrevious) {
			// Get previous visible step (skip conditionally hidden steps)
			const prevIndex = getNextVisibleStepIndex(
				state.currentStepIndex,
				state.steps,
				state.formData,
				'backward'
			);

			state.currentStepIndex = prevIndex;
		}
	}

	function skipStep() {
		if (currentStep?.canSkip) {
			state.steps[state.currentStepIndex].isSkipped = true;
			state.steps[state.currentStepIndex].isComplete = true;
			if (!isLastStep) {
				state.currentStepIndex++;
			}
			scheduleDraftSave();
		}
	}

	function setStepValid(stepId: string, isValid: boolean) {
		const step = state.steps.find((s) => s.id === stepId);
		if (step) {
			step.isValid = isValid;
		}
	}

	function updateFormData(data: Record<string, any>) {
		state.formData = { ...state.formData, ...data };
		scheduleDraftSave();
	}

	function complete() {
		state.steps[state.currentStepIndex].isComplete = true;
		state.isComplete = true;
		clearDraft();
	}

	function reset() {
		state.currentStepIndex = 0;
		state.isComplete = false;
		state.formData = {};
		state.steps = state.steps.map((step) => ({
			...step,
			isValid: false,
			isComplete: false,
			isSkipped: false
		}));
		clearDraft();
	}

	// Draft management - schedule save with debouncing
	function scheduleDraftSave() {
		if (debounceTimer) {
			clearTimeout(debounceTimer);
		}
		debounceTimer = setTimeout(() => {
			saveDraft();
		}, autoSaveDebounce);
	}

	// Save draft to localStorage or API
	async function saveDraft(name?: string): Promise<number | null> {
		if (typeof window === 'undefined') return null;

		if (useApiDrafts) {
			return saveApiDraft(name);
		} else {
			saveLocalDraft();
			return null;
		}
	}

	// Save draft to localStorage
	function saveLocalDraft() {
		const localKey = `wizard_draft_${wizardType}${referenceId ? `_${referenceId}` : ''}`;
		localStorage.setItem(
			localKey,
			JSON.stringify({
				steps: state.steps,
				currentStepIndex: state.currentStepIndex,
				formData: state.formData,
				timestamp: Date.now()
			})
		);
		lastSaved = new Date();
	}

	// Save draft to API
	async function saveApiDraft(name?: string): Promise<number | null> {
		if (isSaving) return draftId;

		isSaving = true;
		saveError = null;

		try {
			// If we have an existing draft, use auto-save for quick updates
			if (draftId) {
				const result = await autoSaveDraft({
					draft_id: draftId,
					form_data: state.formData,
					steps_state: state.steps.map((s) => ({
						id: s.id,
						title: s.title,
						isValid: s.isValid,
						isComplete: s.isComplete,
						isSkipped: s.isSkipped
					})),
					current_step_index: state.currentStepIndex
				});
				lastSaved = new Date(result.updated_at);
				onDraftSaved?.(draftId);
				return draftId;
			}

			// Create new draft
			const payload: CreateDraftPayload = {
				wizard_type: wizardType,
				reference_id: referenceId,
				name,
				form_data: state.formData,
				steps_state: state.steps.map((s) => ({
					id: s.id,
					title: s.title,
					isValid: s.isValid,
					isComplete: s.isComplete,
					isSkipped: s.isSkipped
				})),
				current_step_index: state.currentStepIndex
			};

			const result = await apiSaveDraft(payload);
			draftId = result.id;
			lastSaved = new Date(result.updated_at);
			onDraftSaved?.(result.id);
			return result.id;
		} catch (error) {
			const err = error instanceof Error ? error : new Error('Failed to save draft');
			saveError = err.message;
			onDraftSaveError?.(err);
			// Fall back to localStorage on API error
			saveLocalDraft();
			return null;
		} finally {
			isSaving = false;
		}
	}

	// Load draft from localStorage or API
	async function loadDraft(loadDraftId?: number): Promise<boolean> {
		if (typeof window === 'undefined') return false;

		if (useApiDrafts && loadDraftId) {
			return loadApiDraft(loadDraftId);
		} else {
			return loadLocalDraft();
		}
	}

	// Load draft from localStorage
	function loadLocalDraft(): boolean {
		const localKey = `wizard_draft_${wizardType}${referenceId ? `_${referenceId}` : ''}`;
		const draftData = localStorage.getItem(localKey);

		if (draftData) {
			try {
				const draft = JSON.parse(draftData);
				state.steps = draft.steps;
				state.currentStepIndex = draft.currentStepIndex;
				state.formData = draft.formData;
				return true;
			} catch (e) {
				console.error('Failed to load local draft:', e);
			}
		}
		return false;
	}

	// Load draft from API
	async function loadApiDraft(loadDraftId: number): Promise<boolean> {
		try {
			const draft = await getDraft(loadDraftId);
			draftId = draft.id;
			state.formData = draft.form_data as Record<string, any>;
			state.currentStepIndex = draft.current_step_index;

			// Merge loaded step states with initial steps
			state.steps = state.steps.map((step) => {
				const savedStep = draft.steps_state.find((s) => s.id === step.id);
				if (savedStep) {
					return {
						...step,
						isValid: savedStep.isValid ?? false,
						isComplete: savedStep.isComplete ?? false,
						isSkipped: savedStep.isSkipped ?? false
					};
				}
				return step;
			});

			return true;
		} catch (error) {
			console.error('Failed to load API draft:', error);
			// Try loading from localStorage as fallback
			return loadLocalDraft();
		}
	}

	// Clear draft from storage
	async function clearDraft() {
		if (typeof window === 'undefined') return;

		// Clear timers
		if (autoSaveTimer) {
			clearTimeout(autoSaveTimer);
			autoSaveTimer = null;
		}
		if (debounceTimer) {
			clearTimeout(debounceTimer);
			debounceTimer = null;
		}

		// Clear API draft
		if (useApiDrafts && draftId) {
			try {
				await deleteDraft(draftId);
			} catch (error) {
				console.error('Failed to delete API draft:', error);
			}
			draftId = null;
		}

		// Clear localStorage draft
		const localKey = `wizard_draft_${wizardType}${referenceId ? `_${referenceId}` : ''}`;
		localStorage.removeItem(localKey);

		lastSaved = null;
	}

	// Check if a draft exists
	async function hasDraft(checkDraftId?: number): Promise<boolean> {
		if (typeof window === 'undefined') return false;

		if (useApiDrafts && checkDraftId) {
			try {
				await getDraft(checkDraftId);
				return true;
			} catch {
				return false;
			}
		}

		const localKey = `wizard_draft_${wizardType}${referenceId ? `_${referenceId}` : ''}`;
		return localStorage.getItem(localKey) !== null;
	}

	// Start auto-save interval
	function startAutoSave() {
		if (autoSaveTimer) return;

		autoSaveTimer = setInterval(() => {
			if (!state.isComplete) {
				saveDraft();
			}
		}, autoSaveInterval);
	}

	// Stop auto-save interval
	function stopAutoSave() {
		if (autoSaveTimer) {
			clearInterval(autoSaveTimer);
			autoSaveTimer = null;
		}
	}

	// Cleanup on destroy
	function destroy() {
		stopAutoSave();
		if (debounceTimer) {
			clearTimeout(debounceTimer);
		}
	}

	return {
		// State
		get steps() {
			return state.steps;
		},
		get currentStepIndex() {
			return state.currentStepIndex;
		},
		get currentStep() {
			return currentStep;
		},
		get isComplete() {
			return state.isComplete;
		},
		get formData() {
			return state.formData;
		},

		// Derived state
		get isFirstStep() {
			return isFirstStep;
		},
		get isLastStep() {
			return isLastStep;
		},
		get canGoNext() {
			return canGoNext;
		},
		get canGoPrevious() {
			return canGoPrevious;
		},
		get progress() {
			return progress;
		},
		get completedSteps() {
			return completedSteps;
		},
		get totalSteps() {
			return totalSteps;
		},
		get visibleSteps() {
			return visibleSteps;
		},
		get visibleStepIndices() {
			return visibleStepIndices;
		},

		// Draft state
		get draftId() {
			return draftId;
		},
		get isSaving() {
			return isSaving;
		},
		get lastSaved() {
			return lastSaved;
		},
		get saveError() {
			return saveError;
		},

		// Actions
		goToStep,
		goNext,
		goPrevious,
		skipStep,
		setStepValid,
		updateFormData,
		complete,
		reset,

		// Draft actions
		saveDraft,
		loadDraft,
		clearDraft,
		hasDraft,
		startAutoSave,
		stopAutoSave,
		destroy
	};
}

export type WizardStore = ReturnType<typeof createWizardStore>;
