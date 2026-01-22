<script lang="ts">
    import ActionButton from "$lib/components/buttons/ActionButton/ActionButton.svelte";
    import ActionButtonParams from "$lib/components/buttons/ActionButton/types/action-button-params";
    import { Metadata } from '$lib/core/metadata';
    import {UserData} from "../../../../utils/UserData";
    import {driver, Driver, DriveStep} from "driver.js";
    import {onMount} from "svelte";

    export let scope: string;
    export let mode: string;
    export let style: string | null = null;

    let steps: DriveStep[] = [];
    let params: ActionButtonParams = {
        name: "tour",
        action: "showTour",
        html: `<i class="ph ph-question-mark"></i>`
    } as ActionButtonParams;

    if (style) {
        params.style = style;
    }

    function getDriverSteps(): DriveStep[] {
        const result: DriveStep[] = [];

        const tourData = Metadata.get(['tourData', scope, mode]);

        if (tourData) {
            const language = UserData.get()?.preferences.language ?? 'en_US';

            Object.entries(tourData).forEach(([selector, value]: [string, any]) => {
                if (!('description' in value)) {
                    return;
                }

                const step: DriveStep = {
                    element: selector,
                    popover: {},
                } as DriveStep;

                ['title', 'description'].forEach((key: string) => {
                    if ((key in value) && (language in value[key])) {
                        /** @ts-ignore */
                        step.popover[key] = value[key][language] ?? value[key]['en_US'];
                    } else if ((key in value) && ('en_US' in value[key])) {
                        /** @ts-ignore */
                        step.popover[key] = value[key]['en_US'];
                    } else {
                        if (key === 'description') {
                            return;
                        }
                    }
                });

                result.push(step);
            });
        }

        return result;
    }

    function getPreparedDriverSteps(): DriveStep[] {
        const result: DriveStep[] = [];

        getDriverSteps().forEach((step: DriveStep) => {
            if (typeof step.element !== 'string') {
                return;
            }

            try {
                const element = document.querySelector(step.element);
                if (element && window.getComputedStyle(element).display !== 'none') {
                    step.element = element;
                    result.push(step);
                }
            } catch (e) {
            }
        })

        return result;
    }

    function execute(): void {
        if (steps.length > 0) {
            const driverObj: Driver = driver({
                showProgress: true,
                steps: steps,
            });

            driverObj.drive();
        }
    }

    onMount(() => {
        if (getDriverSteps().length > 0) {
            steps = getPreparedDriverSteps();
            const observer = new MutationObserver(() => {
                steps = getPreparedDriverSteps();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });

            return () => {
                observer.disconnect();
            }
        }
    });
</script>

{#if steps.length > 0}
    <ActionButton {params} on:execute={execute}/>
{/if}