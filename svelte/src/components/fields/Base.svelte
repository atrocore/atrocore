<script>
    import { onMount, createEventDispatcher } from 'svelte';

    const dispatch = createEventDispatcher();

    export let name = '';
    export let value = null;
    export let mode = 'detail';
    export let params = {};

    export let isRequired = false;
    export let isDisabled = false;
    export let isReadOnly = false;
    export let inlineEditDisabled = false;
    export let fieldActions = true;

    let element;
    let initialAttributes = {};
    let inlineEditModeIsOn = false;

    onMount(() => {
        if (mode === 'edit' || mode === 'search') {
            initElement();
        }
    });

    function initElement() {
        if (!element) return;

        if (mode === 'edit') {
            element.addEventListener('change', handleChange);
            element.addEventListener('focus', handleFocus);
        }

        return () => {
            if (element) {
                element.removeEventListener('change', handleChange);
                element.removeEventListener('focus', handleFocus);
            }
        };
    }

    function handleChange() {
        dispatch('change');
    }

    function handleFocus() {
        dispatch('focus', { name });
    }

    export function fetch() {
        return { [name]: value };
    }

    export function validate() {
        if (isRequired && (value === '' || value === null)) {
            showValidationMessage('This field is required');
            return true;
        }
        return false;
    }

    function showValidationMessage(message) {
        console.error(message);
    }
</script>

<slot></slot>